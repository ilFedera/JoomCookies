<?php

/**
 * @package     JoomCookies
 * @subpackage  com_joomcookies
 */

namespace JoomCookies\Component\Joomcookies\Administrator\Service\Scan;

\defined('_JEXEC') or die;

use JoomCookies\Component\Joomcookies\Administrator\Service\Catalog\CatalogSync;
use Joomla\CMS\Factory;
use Joomla\CMS\Router\Route;
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
            return $this->processBatch((int) $active['id']);
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

        $this->enqueue($scanId, $safeStart, 0, 'seed');

        if (ScanLimits::usesSitemap($options)) {
            $this->seedSitemaps($scanId, $safeStart, $options);
        }

        if (ScanLimits::followsHtml($options)) {
            $this->seedMenus($scanId);
        }

        $this->writeSummary($scanId, $this->progress($scanId, $options, 'running'));

        return $this->processBatch($scanId);
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
        $summary = $this->summary($scan);
        $lockUntil = (int) ($summary['batch_lock_until'] ?? 0);

        if ($lockUntil > time()) {
            $payload = $this->statusPayload($scanId);
            $payload['busy'] = true;
            $payload['retry_ms'] = max(1000, ($lockUntil - time()) * 1000);

            return $payload;
        }

        $this->reclaimStuck($scanId);
        $this->lockBatch($scanId, $summary, ScanLimits::maxSeconds($options));

        $timeout = ScanLimits::timeout($options);
        $maxBytes = ScanLimits::maxBytes($options);
        $maxDepth = ScanLimits::depth($options);
        $delayMs = ScanLimits::delayMs($options);
        $batchSize = ScanLimits::batchSize($options);
        $maxSeconds = ScanLimits::maxSeconds($options);
        $started = microtime(true);
        $processed = $this->analyzedCount($scanId);
        $handled = 0;
        $active = (int) ($summary['elapsed_active'] ?? 0);

        try {
            while ($handled < $batchSize && (microtime(true) - $started) < $maxSeconds) {
                $processed = $this->analyzedCount($scanId);

                if (!ScanLimits::allowsMorePages($options, $processed)) {
                    $this->finish($scanId, 'complete', $options, $active + (int) round(microtime(true) - $started), 'page_cap');

                    return $this->statusPayload($scanId);
                }

                $item = $this->nextPending($scanId);

                if ($item === null) {
                    $this->finish($scanId, 'complete', $options, $active + (int) round(microtime(true) - $started), 'queue_empty');

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
                    $this->urls()->record($scanId, [
                        'original_url' => (string) $item['url'],
                        'normalized'   => (string) $item['normalized'],
                        'outcome'      => 'error',
                        'reason'       => 'fetch_failed',
                    ]);
                    $handled++;
                    continue;
                }

                $decision = $this->recordFetch($scanId, $item, $result, $options);

                if ($decision['store_findings']) {
                    foreach ($result['findings'] as $finding) {
                        $this->storeFinding($scanId, $finding);
                    }
                }

                foreach ($result['sitemap_links'] ?? [] as $link) {
                    $this->enqueue($scanId, $link, 0, 'sitemap', (string) $item['url']);
                }

                if (ScanLimits::followsHtml($options)) {
                    foreach ($result['html_links'] ?? [] as $link) {
                        $this->enqueue($scanId, $link, (int) $item['depth'] + 1, 'html_link', (string) $item['url']);
                    }
                }

                foreach ($result['discarded'] ?? [] as $discarded) {
                    $this->recordDiscarded($scanId, $discarded, (string) $item['url']);
                }

                $statusCode = (int) ($result['status'] ?? 0);
                if ($statusCode >= 400 || $statusCode === 0) {
                    $this->markQueue($item['id'], 'error', 'HTTP ' . $statusCode);
                } else {
                    $this->markQueue($item['id'], 'done');
                }
                $processed = $this->analyzedCount($scanId);
                $handled++;

                if ($delayMs > 0) {
                    usleep($delayMs * 1000);
                }
            }
        } finally {
            $active += (int) max(0, round(microtime(true) - $started));
        }

        $processed = $this->analyzedCount($scanId);
        $status = ScanLimits::allowsMorePages($options, $processed) && $this->pendingCount($scanId) > 0
            ? 'running'
            : 'complete';

        if ($status === 'complete') {
            $reason = ScanLimits::allowsMorePages($options, $processed) ? 'queue_empty' : 'page_cap';
            $this->finish($scanId, 'complete', $options, $active, $reason);
        } else {
            $progress = $this->progress($scanId, $options, 'running');
            $progress['elapsed_active'] = $active;
            $progress['batch_lock_until'] = 0;
            $this->writeSummary($scanId, $progress);
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
        $this->reclaimAbandoned($scanId);
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
        $existing = $this->summary(\is_array($scan) ? $scan : []);
        $startedAt = \is_array($scan) ? (string) ($scan['started_at'] ?? '') : '';
        $started = strtotime($startedAt) ?: time();
        $elapsed = max(0, time() - $started);
        $analyzed = $this->analyzedCount($scanId);
        $discovered = max($this->urlLogCount($scanId), $queued, $analyzed + $pending + $errors);
        $cap = ScanLimits::pageCap($options);
        $percent = ScanLimits::percent($analyzed, $discovered, $cap, $status);
        $estimated = $pending > 0 && $cap === 0 && $status === 'running';
        $completeReason = (string) ($existing['complete_reason'] ?? '');

        if ($status === 'complete' && $completeReason === '') {
            $completeReason = ScanLimits::allowsMorePages($options, $analyzed) ? 'queue_empty' : 'page_cap';
        }

        return [
            'status'          => $status,
            'scan_mode'       => ScanLimits::mode($options),
            'discovery'       => ScanLimits::discovery($options),
            'page_cap'        => $cap,
            'pages'           => $analyzed,
            'pending'         => $pending,
            'errors'          => $errors,
            'discovered'      => $discovered,
            'duplicates'      => $this->urls()->countOutcome($scanId, 'duplicate'),
            'excluded'        => $this->urls()->countOutcome($scanId, 'excluded'),
            'elapsed'         => $elapsed,
            'elapsed_active'  => (int) ($existing['elapsed_active'] ?? 0),
            'percent'         => $percent,
            'percent_final'   => $status === 'complete' ? 100 : $percent,
            'estimated'       => $estimated,
            'incomplete'      => \in_array($status, ['cancelled', 'paused', 'running'], true),
            'complete_reason' => $completeReason,
            'findings'        => $this->findingCount($scanId),
        ];
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function seedSitemaps(int $scanId, string $startUrl, array $options): void
    {
        $scheme = parse_url($startUrl, PHP_URL_SCHEME);
        $host = parse_url($startUrl, PHP_URL_HOST);

        if (!\is_string($scheme) || $scheme === '' || !\is_string($host) || $host === '') {
            return;
        }

        $origin = $scheme . '://' . $host;
        $timeout = ScanLimits::timeout($options);
        $maxBytes = ScanLimits::maxBytes($options);
        $declared = [];

        try {
            $safe = $this->guard->assertSafe($origin . '/robots.txt');
            $response = $this->transport->get($safe, $timeout, $maxBytes);
            if ($response->status < 400 && preg_match_all('/^sitemap:\s*(\S+)/im', $response->body, $matches)) {
                foreach ($matches[1] as $sitemap) {
                    $declared[] = trim((string) $sitemap);
                }
            }
        } catch (\Throwable) {
            $declared = [];
        }

        if ($declared === []) {
            $declared[] = $origin . '/sitemap.xml';
        }

        foreach (array_unique($declared) as $sitemap) {
            try {
                $this->enqueue($scanId, $this->guard->assertSafe($sitemap), 0, 'sitemap');
            } catch (\InvalidArgumentException) {
                $this->urls()->record($scanId, [
                    'original_url' => $sitemap,
                    'outcome'      => 'excluded',
                    'reason'       => 'offsite_sitemap',
                    'discovery'    => 'sitemap',
                ]);
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
                $sef = $this->menuUrl($root, $itemId);

                if ($sef !== '') {
                    $this->enqueue($scanId, $sef, 0, 'menu');
                }
            }

            if ($link !== '' && preg_match('#^https?://#i', $link) === 1) {
                try {
                    $this->enqueue($scanId, $this->guard->assertSafe($link), 0, 'menu');
                } catch (\InvalidArgumentException) {
                    continue;
                }
            }
        }
    }

    private function menuUrl(string $root, int $itemId): string
    {
        try {
            $sef = Route::link('site', 'index.php?Itemid=' . $itemId, false, Route::TLS_IGNORE, true);
        } catch (\Throwable) {
            return '';
        }

        if (!\is_string($sef) || $sef === '' || str_contains($sef, 'Itemid=')) {
            return '';
        }

        if (preg_match('#^https?://#i', $sef) !== 1) {
            $sef = $root . '/' . ltrim($sef, '/');
        }

        return $sef;
    }

    private function enqueue(int $scanId, string $url, int $depth, string $discovery = 'html_link', string $referrer = ''): void
    {
        $redacted = UrlPolicy::redact($url);

        if (!UrlPolicy::isHttpUrl($url)) {
            $this->recordDiscarded($scanId, [
                'original' => $url,
                'url'      => '',
                'reason'   => 'non_http_scheme',
            ], $referrer, $discovery);

            return;
        }

        $normalized = UrlPolicy::normalize($url);

        if (!UrlPolicy::isSafeToCrawl($normalized)) {
            $this->urls()->record($scanId, [
                'original_url' => $redacted,
                'normalized'   => $normalized,
                'referrer_url' => $referrer,
                'discovery'    => $discovery,
                'outcome'      => 'excluded',
                'reason'       => UrlPolicy::excludeReason($url),
            ]);

            return;
        }

        if ($this->queueHas($scanId, $normalized)) {
            $this->urls()->record($scanId, [
                'original_url' => $redacted,
                'normalized'   => $normalized,
                'referrer_url' => $referrer,
                'discovery'    => $discovery,
                'outcome'      => 'duplicate',
                'reason'       => 'already_queued',
            ]);

            return;
        }

        $query = $this->db->getQuery(true)
            ->insert($this->db->quoteName('#__joomcookies_scan_queue'))
            ->columns(['scan_id', 'url', 'normalized', 'depth', 'status'])
            ->values(implode(',', [
                $scanId,
                $this->db->quote(mb_substr($redacted, 0, 1024)),
                $this->db->quote(mb_substr($normalized, 0, 512)),
                $depth,
                $this->db->quote('pending'),
            ]));

        try {
            $this->db->setQuery($query)->execute();
        } catch (\Throwable $exception) {
            $message = strtolower($exception->getMessage());

            if (str_contains($message, 'duplicate') || str_contains($message, '1062')) {
                $this->urls()->record($scanId, [
                    'original_url' => $redacted,
                    'normalized'   => $normalized,
                    'referrer_url' => $referrer,
                    'discovery'    => $discovery,
                    'outcome'      => 'duplicate',
                    'reason'       => 'already_queued',
                ]);

                return;
            }

            throw $exception;
        }

        $this->urls()->record($scanId, [
            'original_url' => $redacted,
            'normalized'   => $normalized,
            'referrer_url' => $referrer,
            'discovery'    => $discovery,
            'outcome'      => 'queued',
            'reason'       => '',
        ]);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function recordDiscarded(int $scanId, array $row, string $referrer, string $discovery = 'html_link'): void
    {
        $original = UrlPolicy::redact((string) ($row['original'] ?? $row['url'] ?? ''));
        $normalized = $original;

        if (UrlPolicy::isHttpUrl((string) ($row['url'] ?? ''))) {
            $normalized = UrlPolicy::normalize((string) $row['url']);
        }

        $this->urls()->record($scanId, [
            'original_url' => $original,
            'normalized'   => $normalized,
            'referrer_url' => $referrer,
            'discovery'    => $discovery,
            'outcome'      => 'excluded',
            'reason'       => (string) ($row['reason'] ?? 'policy'),
        ]);
    }

    private function reclaimAbandoned(int $scanId): void
    {
        if ($scanId < 1) {
            return;
        }

        $scan = $this->loadScan($scanId);

        if ($scan === null || (string) $scan['status'] !== 'running') {
            return;
        }

        $summary = $this->summary($scan);
        $lockUntil = (int) ($summary['batch_lock_until'] ?? 0);

        if ($lockUntil > 0 && $lockUntil < (time() - 600)) {
            $this->reclaimStuck($scanId);
            $options = json_decode((string) ($scan['config'] ?? '{}'), true);
            $progress = $this->progress($scanId, \is_array($options) ? $options : [], 'paused');
            $progress['complete_reason'] = 'abandoned';
            $this->writeSummary($scanId, $progress);
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
    private function finish(int $scanId, string $status, array $options, int $elapsedActive = 0, string $completeReason = ''): void
    {
        if ($status === 'complete') {
            $query = $this->db->getQuery(true)->select('*')->from($this->db->quoteName('#__joomcookies_findings'))
                ->where($this->db->quoteName('scan_id') . ' = ' . $scanId)
                ->where($this->db->quoteName('type') . ' = ' . $this->db->quote('cookie_header'));
            $this->db->setQuery($query);
            (new CookieRegistry($this->db))->recordFindings($scanId, $this->db->loadAssocList() ?: []);
        }
        $summary = $this->progress($scanId, $options, $status);
        $summary['percent'] = 100;
        $summary['percent_final'] = 100;
        $summary['estimated'] = false;
        $summary['incomplete'] = $status !== 'complete';
        $summary['elapsed_active'] = $elapsedActive > 0 ? $elapsedActive : (int) ($summary['elapsed_active'] ?? 0);
        $summary['batch_lock_until'] = 0;
        $summary['complete_reason'] = $completeReason !== '' ? $completeReason : (string) ($summary['complete_reason'] ?? '');
        $this->writeSummary($scanId, $summary, true);
        $this->syncCatalog($scanId);
    }

    private function syncCatalog(int $scanId): void
    {
        try {
            (new CatalogSync($this->db))->importScan($scanId);
        } catch (\Throwable) {
            // Catalogue sync must not fail the scan job.
        }
    }

    /**
     * @param  array<string, mixed>  $summary
     */
    private function lockBatch(int $scanId, array $summary, int $maxSeconds): void
    {
        $summary['batch_lock_until'] = time() + $maxSeconds + 5;
        $query = $this->db->getQuery(true)
            ->update($this->db->quoteName('#__joomcookies_scans'))
            ->set($this->db->quoteName('summary') . ' = ' . $this->db->quote(json_encode($summary, JSON_UNESCAPED_SLASHES) ?: '{}'))
            ->where($this->db->quoteName('id') . ' = ' . $scanId);
        $this->db->setQuery($query)->execute();
    }

    private function reclaimStuck(int $scanId): void
    {
        $query = $this->db->getQuery(true)
            ->update($this->db->quoteName('#__joomcookies_scan_queue'))
            ->set($this->db->quoteName('status') . ' = ' . $this->db->quote('pending'))
            ->where($this->db->quoteName('scan_id') . ' = ' . $scanId)
            ->where($this->db->quoteName('status') . ' = ' . $this->db->quote('running'));
        $this->db->setQuery($query)->execute();
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

    private function urls(): ScanUrlStore
    {
        return new ScanUrlStore($this->db);
    }

    private function analyzedCount(int $scanId): int
    {
        if (!$this->urls()->available()) {
            return $this->queueCount($scanId, 'done');
        }

        return $this->urls()->countOutcome($scanId, 'analyzed');
    }

    private function urlLogCount(int $scanId): int
    {
        if (!$this->urls()->available()) {
            return 0;
        }

        $query = $this->db->getQuery(true)
            ->select('COUNT(*)')
            ->from($this->db->quoteName('#__joomcookies_scan_urls'))
            ->where($this->db->quoteName('scan_id') . ' = ' . $scanId);
        $this->db->setQuery($query);

        return (int) $this->db->loadResult();
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array<string, mixed>  $result
     * @param  array<string, mixed>  $options
     * @return array{store_findings: bool}
     */
    private function recordFetch(int $scanId, array $item, array $result, array $options): array
    {
        $itemNorm = UrlPolicy::normalize((string) $item['normalized']);
        $finalNorm = UrlPolicy::normalize((string) ($result['url'] ?? $item['url']));
        $canonical = (string) ($result['canonical'] ?? '');
        $canonicalNorm = $canonical !== '' ? UrlPolicy::normalize($canonical) : '';
        $statusCode = (int) ($result['status'] ?? 0);
        $outcome = 'analyzed';
        $reason = '';
        $storeFindings = true;

        if ($statusCode >= 400 || $statusCode === 0) {
            $outcome = 'error';
            $reason = $statusCode === 404 ? 'http_404' : ('http_' . $statusCode);
            $storeFindings = false;
        } elseif ($finalNorm !== $itemNorm) {
            $reason = 'redirect';
        }

        if ($outcome === 'analyzed' && $finalNorm !== $itemNorm) {
            $other = $this->urls()->find($scanId, $finalNorm);

            if (\is_array($other) && (string) ($other['outcome'] ?? '') === 'analyzed') {
                $outcome = 'duplicate';
                $reason = 'redirect';
                $storeFindings = false;
            }
        }

        if ($outcome === 'analyzed' && $canonicalNorm !== '' && $canonicalNorm !== $itemNorm && $canonicalNorm !== $finalNorm) {
            $this->enqueue($scanId, $canonical, (int) $item['depth'], 'canonical', (string) $item['url']);
            $other = $this->urls()->find($scanId, $canonicalNorm);

            if (\is_array($other) && (string) ($other['outcome'] ?? '') === 'analyzed') {
                $outcome = 'duplicate';
                $reason = 'canonical';
                $storeFindings = false;
            }
        }

        $this->urls()->record($scanId, [
            'original_url'  => (string) $item['url'],
            'normalized'    => $itemNorm,
            'http_status'   => (int) ($result['status'] ?? 0),
            'final_url'     => (string) ($result['url'] ?? ''),
            'canonical_url' => $canonical,
            'outcome'       => $outcome,
            'reason'        => $reason,
        ]);

        unset($options);

        return ['store_findings' => $storeFindings];
    }
}
