<?php

/**
 * @package     JoomCookies
 * @subpackage  com_joomcookies
 */

namespace JoomCookies\Component\Joomcookies\Administrator\Service\Scan;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\DatabaseInterface;

final class ScanRunner
{
    public function __construct(
        private readonly DatabaseInterface $db,
        private readonly HttpCrawler $crawler,
        private readonly HttpTransport $transport,
        private readonly SsrfGuard $guard
    ) {
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function start(string $startUrl, array $options, int $userId): array
    {
        $active = $this->activeScan();

        if ($active !== null) {
            return $this->statusPayload((int) $active['id'], 'A scan is already running.');
        }

        $safeStart = $this->guard->assertSafe($startUrl);
        $now = Factory::getDate()->toSql();
        $query = $this->db->getQuery(true)
            ->insert($this->db->quoteName('#__joomcookies_scans'))
            ->columns(['started_at', 'status', 'mode', 'start_url', 'config', 'created_by'])
            ->values(implode(',', [
                $this->db->quote($now),
                $this->db->quote('running'),
                $this->db->quote('http'),
                $this->db->quote($safeStart),
                $this->db->quote(json_encode($options, JSON_UNESCAPED_SLASHES) ?: '{}'),
                $userId,
            ]));
        $this->db->setQuery($query)->execute();
        $scanId = (int) $this->db->insertid();

        $this->enqueue($scanId, $safeStart, 0);
        $this->seedSitemaps($scanId, $safeStart, $options);
        $this->seedMenus($scanId);

        $this->writeSummary($scanId, $this->progress($scanId, $options, 'running'));

        return $this->statusPayload($scanId);
    }

    /**
     * @return array<string, mixed>
     */
    public function processBatch(int $scanId): array
    {
        $scan = $this->loadScan($scanId);

        if ($scan === null) {
            return ['ok' => false, 'error' => 'Scan not found.'];
        }

        if ((string) $scan['status'] !== 'running') {
            return $this->statusPayload($scanId);
        }

        $options = json_decode((string) $scan['config'], true);
        $options = \is_array($options) ? $options : [];
        $timeout = ScanLimits::timeout($options);
        $maxBytes = ScanLimits::maxBytes($options);
        $maxDepth = ScanLimits::depth($options);
        $delayMs = ScanLimits::delayMs($options);
        $batchSize = ScanLimits::batchSize($options);
        $maxSeconds = ScanLimits::maxSeconds($options);
        $started = microtime(true);
        $processed = (int) ($this->summary($scan)['pages'] ?? 0);
        $handled = 0;

        while ($handled < $batchSize && (microtime(true) - $started) < $maxSeconds) {
            if (!ScanLimits::allowsMorePages($options, $processed)) {
                $this->finish($scanId, 'complete', $options);
                return $this->statusPayload($scanId);
            }

            $item = $this->nextPending($scanId);

            if ($item === null) {
                $this->finish($scanId, 'complete', $options);
                return $this->statusPayload($scanId);
            }

            $this->markQueue($item['id'], 'running');

            try {
                $result = $this->crawler->fetchPage(
                    (string) $item['url'],
                    (int) $item['depth'],
                    $maxDepth,
                    $this->transport,
                    $this->guard,
                    $timeout,
                    $maxBytes
                );
            } catch (\Throwable) {
                $this->markQueue($item['id'], 'error', 'Skip URL.');
                $handled++;
                continue;
            }

            foreach ($result['findings'] as $finding) {
                $this->storeFinding($scanId, $finding);
            }

            foreach ($result['links'] as $link) {
                $this->enqueue($scanId, $link, (int) $item['depth'] + 1);
            }

            $this->markQueue($item['id'], 'done');
            $processed++;
            $handled++;

            if ($delayMs > 0) {
                usleep($delayMs * 1000);
            }
        }

        $status = ScanLimits::allowsMorePages($options, $processed) && $this->pendingCount($scanId) > 0
            ? 'running'
            : 'complete';

        if ($status === 'complete') {
            $this->finish($scanId, 'complete', $options);
        } else {
            $this->writeSummary($scanId, $this->progress($scanId, $options, 'running'));
        }

        return $this->statusPayload($scanId);
    }

    /**
     * @return array<string, mixed>
     */
    public function pause(int $scanId): array
    {
        $this->setStatus($scanId, 'paused');
        $scan = $this->loadScan($scanId);
        $options = json_decode((string) ($scan['config'] ?? '{}'), true);

        $this->writeSummary($scanId, $this->progress($scanId, \is_array($options) ? $options : [], 'paused'));

        return $this->statusPayload($scanId);
    }

    /**
     * @return array<string, mixed>
     */
    public function resume(int $scanId): array
    {
        $scan = $this->loadScan($scanId);

        if ($scan === null || !\in_array((string) $scan['status'], ['paused', 'running'], true)) {
            return $this->statusPayload($scanId);
        }

        $this->setStatus($scanId, 'running');

        return $this->processBatch($scanId);
    }

    /**
     * @return array<string, mixed>
     */
    public function cancel(int $scanId): array
    {
        $this->setStatus($scanId, 'cancelled');
        $scan = $this->loadScan($scanId);
        $options = json_decode((string) ($scan['config'] ?? '{}'), true);
        $summary = $this->progress($scanId, \is_array($options) ? $options : [], 'cancelled');
        $summary['incomplete'] = true;
        $this->writeSummary($scanId, $summary, true);

        return $this->statusPayload($scanId);
    }

    /**
     * @return array<string, mixed>
     */
    public function statusPayload(int $scanId, string $message = ''): array
    {
        $scan = $this->loadScan($scanId);
        $options = json_decode((string) ($scan['config'] ?? '{}'), true);
        $options = \is_array($options) ? $options : [];
        $progress = $this->progress($scanId, $options, (string) ($scan['status'] ?? 'unknown'));

        return [
            'ok'        => true,
            'message'   => $message,
            'scan_id'   => $scanId,
            'status'    => $progress['status'],
            'progress'  => $progress,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function activeScan(): ?array
    {
        $query = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName('#__joomcookies_scans'))
            ->where($this->db->quoteName('status') . ' = ' . $this->db->quote('running'))
            ->order($this->db->quoteName('id') . ' DESC');
        $this->db->setQuery($query, 0, 1);
        $row = $this->db->loadAssoc();

        return \is_array($row) ? $row : null;
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    private function progress(int $scanId, array $options, string $status): array
    {
        $pending = $this->pendingCount($scanId);
        $done = $this->queueCount($scanId, 'done');
        $errors = $this->queueCount($scanId, 'error');
        $queued = $this->queueCount($scanId, '');
        $scan = $this->loadScan($scanId);
        $started = strtotime((string) ($scan['started_at'] ?? '')) ?: time();
        $elapsed = max(0, time() - $started);
        $discovered = max($queued, $done + $pending + $errors);
        $cap = ScanLimits::pageCap($options);
        $denominator = $cap > 0 ? min($cap, max($discovered, $done)) : max($discovered, $done + $pending);
        $percent = $denominator > 0 ? (int) round(100 * $done / $denominator) : 0;
        $estimated = $pending > 0 && ($cap === 0 || $discovered < $cap);

        return [
            'status'          => $status,
            'scan_mode'       => ScanLimits::mode($options),
            'pages'           => $done,
            'pending'         => $pending,
            'errors'          => $errors,
            'discovered'      => $discovered,
            'elapsed'         => $elapsed,
            'percent'         => min(99, $percent),
            'percent_final'   => $status === 'complete' ? 100 : min(99, $percent),
            'estimated'       => $estimated && $status === 'running',
            'incomplete'      => \in_array($status, ['cancelled', 'paused', 'running'], true),
            'findings'        => $this->findingCount($scanId),
        ];
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function seedSitemaps(int $scanId, string $startUrl, array $options): void
    {
        $origin = parse_url($startUrl, PHP_URL_SCHEME) . '://' . parse_url($startUrl, PHP_URL_HOST);
        $candidates = [$origin . '/sitemap.xml', $origin . '/robots.txt'];
        $timeout = ScanLimits::timeout($options);
        $maxBytes = ScanLimits::maxBytes($options);

        foreach ($candidates as $url) {
            try {
                $safe = $this->guard->assertSafe($url);
                $response = $this->transport->get($safe, $timeout, $maxBytes);
            } catch (\Throwable) {
                continue;
            }

            if ($response->status >= 400) {
                continue;
            }

            if (str_ends_with($url, 'robots.txt')) {
                if (preg_match_all('/^sitemap:\s*(\S+)/im', $response->body, $matches)) {
                    foreach ($matches[1] as $sitemap) {
                        try {
                            $this->enqueue($scanId, $this->guard->assertSafe(trim((string) $sitemap)), 0);
                        } catch (\InvalidArgumentException) {
                            // Off-site sitemap ignored.
                        }
                    }
                }
                continue;
            }

            foreach (HtmlAnalyzer::sitemapUrls($response->body, $this->guard) as $loc) {
                $this->enqueue($scanId, $loc, 0);
            }
        }
    }

    private function seedMenus(int $scanId): void
    {
        try {
            $query = $this->db->getQuery(true)
                ->select($this->db->quoteName(['id', 'link']))
                ->from($this->db->quoteName('#__menu'))
                ->where($this->db->quoteName('client_id') . ' = 0')
                ->where($this->db->quoteName('published') . ' = 1')
                ->where($this->db->quoteName('access') . ' = 1');
            $this->db->setQuery($query, 0, 5000);
            $rows = $this->db->loadAssocList() ?: [];
        } catch (\Throwable) {
            return;
        }

        $root = rtrim(Uri::root(), '/');

        foreach ($rows as $row) {
            $itemId = (int) ($row['id'] ?? 0);
            $link = trim((string) ($row['link'] ?? ''));

            if ($itemId > 0) {
                $this->enqueue($scanId, $root . '/index.php?Itemid=' . $itemId, 0);
            }

            if ($link !== '' && preg_match('#^https?://#i', $link) === 1) {
                try {
                    $this->enqueue($scanId, $this->guard->assertSafe($link), 0);
                } catch (\InvalidArgumentException) {
                    continue;
                }
            }
        }
    }

    private function enqueue(int $scanId, string $url, int $depth): void
    {
        $normalized = UrlPolicy::normalize($url);

        if (!UrlPolicy::isSafeToCrawl($normalized) || $this->queueHas($scanId, $normalized)) {
            return;
        }

        $query = $this->db->getQuery(true)
            ->insert($this->db->quoteName('#__joomcookies_scan_queue'))
            ->columns(['scan_id', 'url', 'normalized', 'depth', 'status'])
            ->values(implode(',', [
                $scanId,
                $this->db->quote(mb_substr($url, 0, 1024)),
                $this->db->quote(mb_substr($normalized, 0, 512)),
                $depth,
                $this->db->quote('pending'),
            ]));

        try {
            $this->db->setQuery($query)->execute();
        } catch (\Throwable) {
            // Duplicate URL for this scan.
        }
    }

    private function queueHas(int $scanId, string $normalized): bool
    {
        $query = $this->db->getQuery(true)
            ->select('COUNT(*)')
            ->from($this->db->quoteName('#__joomcookies_scan_queue'))
            ->where($this->db->quoteName('scan_id') . ' = ' . $scanId)
            ->where($this->db->quoteName('normalized') . ' = ' . $this->db->quote(mb_substr($normalized, 0, 512)));
        $this->db->setQuery($query);

        return (int) $this->db->loadResult() > 0;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function nextPending(int $scanId): ?array
    {
        $query = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName('#__joomcookies_scan_queue'))
            ->where($this->db->quoteName('scan_id') . ' = ' . $scanId)
            ->where($this->db->quoteName('status') . ' = ' . $this->db->quote('pending'))
            ->order($this->db->quoteName('id') . ' ASC');
        $this->db->setQuery($query, 0, 1);
        $row = $this->db->loadAssoc();

        return \is_array($row) ? $row : null;
    }

    private function markQueue(int $id, string $status, string $error = ''): void
    {
        $query = $this->db->getQuery(true)
            ->update($this->db->quoteName('#__joomcookies_scan_queue'))
            ->set($this->db->quoteName('status') . ' = ' . $this->db->quote($status))
            ->set($this->db->quoteName('error') . ' = ' . $this->db->quote(mb_substr($error, 0, 255)))
            ->where($this->db->quoteName('id') . ' = ' . $id);
        $this->db->setQuery($query)->execute();
    }

    /**
     * @param  array<string, mixed>  $finding
     */
    private function storeFinding(int $scanId, array $finding): void
    {
        $type = mb_substr((string) $finding['type'], 0, 32);
        $name = mb_substr((string) $finding['name'], 0, 191);
        $domain = mb_substr((string) ($finding['domain'] ?? ''), 0, 191);
        $service = mb_substr((string) ($finding['service_id'] ?? ''), 0, 64);
        $page = mb_substr((string) ($finding['page_url'] ?? ''), 0, 512);
        $query = $this->db->getQuery(true)
            ->select(['id', 'meta', 'page_url'])
            ->from($this->db->quoteName('#__joomcookies_findings'))
            ->where($this->db->quoteName('scan_id') . ' = ' . $scanId)
            ->where($this->db->quoteName('type') . ' = ' . $this->db->quote($type))
            ->where($this->db->quoteName('name') . ' = ' . $this->db->quote($name))
            ->where($this->db->quoteName('domain') . ' = ' . $this->db->quote($domain))
            ->where($this->db->quoteName('service_id') . ' = ' . $this->db->quote($service));
        $this->db->setQuery($query, 0, 1);
        $existing = $this->db->loadAssoc();

        if (\is_array($existing)) {
            $meta = json_decode((string) ($existing['meta'] ?? '{}'), true);
            $meta = \is_array($meta) ? $meta : [];
            $pages = $meta['pages'] ?? [];
            if (!\is_array($pages)) {
                $pages = [];
            }
            if ($page !== '' && !\in_array($page, $pages, true)) {
                $pages[] = $page;
            }
            $meta['pages'] = $pages;
            $meta['occurrences'] = (int) ($meta['occurrences'] ?? 1) + 1;
            $update = $this->db->getQuery(true)
                ->update($this->db->quoteName('#__joomcookies_findings'))
                ->set($this->db->quoteName('meta') . ' = ' . $this->db->quote(json_encode($meta, JSON_UNESCAPED_SLASHES) ?: '{}'))
                ->where($this->db->quoteName('id') . ' = ' . (int) $existing['id']);
            $this->db->setQuery($update)->execute();

            return;
        }

        $meta = $finding['meta'] ?? [];
        if (!\is_array($meta)) {
            $meta = [];
        }
        $meta['pages'] = $page !== '' ? [$page] : [];
        $meta['occurrences'] = 1;

        $insert = $this->db->getQuery(true)
            ->insert($this->db->quoteName('#__joomcookies_findings'))
            ->columns([
                'scan_id', 'type', 'name', 'domain', 'page_url', 'target',
                'category_suggested', 'service_id', 'provider', 'evidence_kind', 'observed', 'meta',
            ])
            ->values(implode(',', [
                $scanId,
                $this->db->quote($type),
                $this->db->quote($name),
                $this->db->quote($domain),
                $this->db->quote($page),
                $this->db->quote(mb_substr((string) ($finding['target'] ?? ''), 0, 512)),
                $this->db->quote((string) ($finding['category_suggested'] ?? '')),
                $this->db->quote($service),
                $this->db->quote((string) ($finding['provider'] ?? '')),
                $this->db->quote((string) ($finding['evidence_kind'] ?? 'reference')),
                (int) ($finding['observed'] ?? 0),
                $this->db->quote(json_encode($meta, JSON_UNESCAPED_SLASHES) ?: '{}'),
            ]));
        $this->db->setQuery($insert)->execute();
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function finish(int $scanId, string $status, array $options): void
    {
        $summary = $this->progress($scanId, $options, $status);
        $summary['percent'] = 100;
        $summary['percent_final'] = 100;
        $summary['estimated'] = false;
        $summary['incomplete'] = $status !== 'complete';
        $this->writeSummary($scanId, $summary, true);
    }

    /**
     * @param  array<string, mixed>  $summary
     */
    private function writeSummary(int $scanId, array $summary, bool $finished = false): void
    {
        $query = $this->db->getQuery(true)
            ->update($this->db->quoteName('#__joomcookies_scans'))
            ->set($this->db->quoteName('status') . ' = ' . $this->db->quote((string) $summary['status']))
            ->set($this->db->quoteName('summary') . ' = ' . $this->db->quote(json_encode($summary, JSON_UNESCAPED_SLASHES) ?: '{}'));

        if ($finished) {
            $query->set($this->db->quoteName('finished_at') . ' = ' . $this->db->quote(Factory::getDate()->toSql()));
        }

        $query->where($this->db->quoteName('id') . ' = ' . $scanId);
        $this->db->setQuery($query)->execute();
    }

    private function setStatus(int $scanId, string $status): void
    {
        $query = $this->db->getQuery(true)
            ->update($this->db->quoteName('#__joomcookies_scans'))
            ->set($this->db->quoteName('status') . ' = ' . $this->db->quote($status))
            ->where($this->db->quoteName('id') . ' = ' . $scanId);
        $this->db->setQuery($query)->execute();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function loadScan(int $scanId): ?array
    {
        $query = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName('#__joomcookies_scans'))
            ->where($this->db->quoteName('id') . ' = ' . $scanId);
        $this->db->setQuery($query);
        $row = $this->db->loadAssoc();

        return \is_array($row) ? $row : null;
    }

    /**
     * @param  array<string, mixed>  $scan
     * @return array<string, mixed>
     */
    private function summary(array $scan): array
    {
        $decoded = json_decode((string) ($scan['summary'] ?? ''), true);

        return \is_array($decoded) ? $decoded : [];
    }

    private function pendingCount(int $scanId): int
    {
        return $this->queueCount($scanId, 'pending') + $this->queueCount($scanId, 'running');
    }

    private function queueCount(int $scanId, string $status): int
    {
        $query = $this->db->getQuery(true)
            ->select('COUNT(*)')
            ->from($this->db->quoteName('#__joomcookies_scan_queue'))
            ->where($this->db->quoteName('scan_id') . ' = ' . $scanId);

        if ($status !== '') {
            $query->where($this->db->quoteName('status') . ' = ' . $this->db->quote($status));
        }

        $this->db->setQuery($query);

        return (int) $this->db->loadResult();
    }

    private function findingCount(int $scanId): int
    {
        $query = $this->db->getQuery(true)
            ->select('COUNT(*)')
            ->from($this->db->quoteName('#__joomcookies_findings'))
            ->where($this->db->quoteName('scan_id') . ' = ' . $scanId);
        $this->db->setQuery($query);

        return (int) $this->db->loadResult();
    }
}
