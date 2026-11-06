<?php

/**
 * @package     JoomCookies
 * @subpackage  com_joomcookies
 */

namespace JoomCookies\Component\Joomcookies\Administrator\Service\Catalog;

\defined('_JEXEC') or die;

use JoomCookies\Component\Joomcookies\Administrator\Service\Scan\ServiceCatalog;
use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;

/**
 * Writes scan identifications into the admin catalogue without enabling blocks.
 */
final class CatalogSync
{
    public function __construct(private readonly DatabaseInterface $db)
    {
    }

    public function importScan(int $scanId): void
    {
        $this->seedKnownServices();
        $query = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName('#__joomcookies_findings'))
            ->where($this->db->quoteName('scan_id') . ' = ' . $scanId);
        $this->db->setQuery($query);
        $rows = $this->db->loadAssocList() ?: [];
        $grouped = [];

        foreach ($rows as $row) {
            if (!self::isCatalogCandidate($row)) {
                continue;
            }

            $alias = self::aliasFromFinding($row);

            if ($alias === '') {
                continue;
            }

            if (!isset($grouped[$alias])) {
                $grouped[$alias] = self::draftFromFinding($row);
                $grouped[$alias]['pages'] = [];
            }

            $page = (string) ($row['page_url'] ?? '');

            if ($page !== '' && !\in_array($page, $grouped[$alias]['pages'], true)) {
                $grouped[$alias]['pages'][] = $page;
            }

            $grouped[$alias]['last_evidence'] = (string) ($row['evidence_kind'] ?? $grouped[$alias]['last_evidence']);
            $grouped[$alias]['last_scan_id'] = $scanId;
        }

        foreach ($grouped as $draft) {
            $draft['page_count'] = \count($draft['pages']);
            $this->upsert($draft);
        }
    }

    public function seedKnownServices(): void
    {
        foreach (self::knownServices() as $service) {
            $category = (string) ($service['category'] ?? 'unclassified');
            $classified = \in_array($category, ['statistics', 'marketing', 'embeds'], true);
            $this->upsert([
                'alias'                 => (string) $service['id'],
                'name'                  => (string) $service['name'],
                'provider'              => (string) ($service['provider'] ?? ''),
                'category'              => $classified ? $category : 'unclassified',
                'category_suggested'    => self::safeCategory($category),
                'match_type'            => (string) $service['match_type'],
                'match_value'           => (string) $service['match_value'],
                'cookies'               => \is_array($service['cookies'] ?? null)
                    ? implode(',', $service['cookies'])
                    : (string) ($service['cookies'] ?? ''),
                'source'                => 'builtin',
                'classification_status' => $classified ? 'approved' : 'review',
                'approved'              => $classified ? 1 : 0,
                'block_enabled'         => $classified ? 1 : 0,
                'last_evidence'         => 'builtin',
                'pages'                 => [],
                'page_count'            => 0,
                'last_scan_id'          => 0,
                'notes'                 => $classified
                    ? 'Built-in plugin rule. Scan evidence updates pages without changing this classification.'
                    : 'Recognised but not treated as necessary and not blocked automatically.',
            ], true);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function knownServices(): array
    {
        $out = [];

        foreach (ServiceCatalog::all() as $service) {
            $out[(string) $service['id']] = $service;
        }

        if (class_exists(\JoomCookies\Plugin\System\Joomcookies\Service\ServiceRegistry::class)) {
            foreach (\JoomCookies\Plugin\System\Joomcookies\Service\ServiceRegistry::builtIn() as $service) {
                $id = (string) $service['id'];
                $out[$id] = array_merge($out[$id] ?? [], $service);
            }
        }

        return array_values($out);
    }

    /**
     * @param  array<string, mixed>  $finding
     */
    public static function isCatalogCandidate(array $finding): bool
    {
        return \in_array((string) ($finding['type'] ?? ''), ['script', 'iframe', 'preconnect', 'anomaly'], true);
    }

    /**
     * @param  array<string, mixed>  $finding
     */
    public static function aliasFromFinding(array $finding): string
    {
        $id = strtolower(preg_replace('/[^a-z0-9_-]/', '', (string) ($finding['service_id'] ?? '')) ?? '');

        if ($id !== '' && $id !== 'unknown') {
            return mb_substr($id, 0, 64);
        }

        $domain = strtolower(preg_replace('/[^a-z0-9.-]/', '', (string) ($finding['domain'] ?? '')) ?? '');

        if ($domain === '') {
            return '';
        }

        return mb_substr('unknown-' . str_replace('.', '-', $domain), 0, 64);
    }

    /**
     * @param  array<string, mixed>  $finding
     * @return array<string, mixed>
     */
    public static function draftFromFinding(array $finding): array
    {
        $alias = self::aliasFromFinding($finding);
        $serviceId = (string) ($finding['service_id'] ?? '');
        $known = $serviceId !== '' && $serviceId !== 'unknown';
        $category = self::safeCategory((string) ($finding['category_suggested'] ?? ''));
        $target = (string) ($finding['target'] ?? '');
        $domain = (string) ($finding['domain'] ?? '');

        return [
            'alias'                 => $alias,
            'name'                  => $known ? (string) ($finding['name'] ?? $alias) : ('Unknown: ' . ($domain !== '' ? $domain : $alias)),
            'provider'              => (string) ($finding['provider'] ?? ''),
            'category'              => $category,
            'category_suggested'    => $category,
            'match_type'            => $domain !== '' ? 'domain' : 'url_contains',
            'match_value'           => $domain !== '' ? $domain : mb_substr($target, 0, 255),
            'cookies'               => '',
            'source'                => 'scan',
            'classification_status' => 'review',
            'approved'              => 0,
            'block_enabled'         => 0,
            'last_evidence'         => (string) ($finding['evidence_kind'] ?? 'reference'),
            'last_scan_id'          => 0,
            'pages'                 => [],
            'page_count'            => 0,
            'notes'                 => $known ? '' : 'Requires admin review. Not treated as necessary and not blocked automatically.',
        ];
    }

    /**
     * @param  array<string, mixed>  $existing
     */
    public static function preserveAdmin(array $existing): bool
    {
        return (int) ($existing['approved'] ?? 0) === 1
            || ($existing['source'] ?? '') === 'manual'
            || ($existing['classification_status'] ?? '') === 'manual';
    }

    /**
     * @param  array<string, mixed>  $existing
     * @param  array<string, mixed>  $incoming
     * @return array<string, mixed>
     */
    public static function mergeScan(array $existing, array $incoming): array
    {
        $pages = self::mergePages($existing, $incoming);
        $out = $existing;
        $out['last_scan_id'] = (int) ($incoming['last_scan_id'] ?? $existing['last_scan_id'] ?? 0);
        $out['last_evidence'] = (string) ($incoming['last_evidence'] ?? $existing['last_evidence'] ?? '');
        $out['pages'] = $pages;
        $out['page_count'] = \count($pages);
        $out['category_suggested'] = (string) ($incoming['category_suggested'] ?? $existing['category_suggested'] ?? '');

        if (!self::preserveAdmin($existing)) {
            $out['name'] = (string) ($incoming['name'] ?? $existing['name']);
            $out['provider'] = (string) ($incoming['provider'] ?? $existing['provider']);
            $out['match_type'] = (string) ($incoming['match_type'] ?? $existing['match_type']);
            $out['match_value'] = (string) ($incoming['match_value'] ?? $existing['match_value']);

            if ((string) ($existing['classification_status'] ?? 'review') === 'review') {
                $out['category'] = (string) ($incoming['category'] ?? $existing['category']);
            }
        }

        return $out;
    }

    public static function safeCategory(string $category): string
    {
        return \in_array($category, ['statistics', 'marketing', 'embeds', 'unclassified'], true)
            ? $category
            : 'unclassified';
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function upsert(array $row, bool $builtin = false): void
    {
        $alias = mb_substr((string) ($row['alias'] ?? ''), 0, 64);

        if ($alias === '') {
            return;
        }

        $existing = $this->find($alias);
        $now = Factory::getDate()->toSql();

        if ($existing !== null) {
            if ($builtin) {
                return;
            }

            $this->update((int) $existing['id'], self::mergeScan($existing, $row), $now);

            return;
        }

        $insert = [
            'alias'                 => $alias,
            'name'                  => mb_substr((string) $row['name'], 0, 255),
            'provider'              => mb_substr((string) ($row['provider'] ?? ''), 0, 191),
            'category'              => self::safeCategory((string) ($row['category'] ?? 'unclassified')),
            'category_suggested'    => self::safeCategory((string) ($row['category_suggested'] ?? '')),
            'domains'               => json_encode([$row['match_value'] ?? ''], JSON_UNESCAPED_SLASHES) ?: '[]',
            'match_type'            => (string) ($row['match_type'] ?? 'domain'),
            'match_value'           => mb_substr((string) ($row['match_value'] ?? ''), 0, 255),
            'cookies'               => (string) ($row['cookies'] ?? ''),
            'source'                => (string) ($row['source'] ?? 'scan'),
            'classification_status' => (string) ($row['classification_status'] ?? 'review'),
            'approved'              => (int) ($row['approved'] ?? 0),
            'block_enabled'         => (int) ($row['block_enabled'] ?? 0),
            'last_scan_id'          => (int) ($row['last_scan_id'] ?? 0),
            'last_evidence'         => mb_substr((string) ($row['last_evidence'] ?? ''), 0, 32),
            'page_count'            => (int) ($row['page_count'] ?? 0),
            'pages'                 => json_encode($row['pages'] ?? [], JSON_UNESCAPED_SLASHES) ?: '[]',
            'notes'                 => (string) ($row['notes'] ?? ''),
            'created'               => $now,
            'modified'              => $now,
        ];

        try {
            $query = $this->db->getQuery(true)
                ->insert($this->db->quoteName('#__joomcookies_services'))
                ->columns(array_keys($insert))
                ->values(implode(',', array_map(fn ($v) => \is_int($v) ? (string) $v : $this->db->quote($v), array_values($insert))));
            $this->db->setQuery($query)->execute();
        } catch (\Throwable $exception) {
            if (!str_contains(strtolower($exception->getMessage()), 'unknown column')) {
                throw $exception;
            }

            $this->upsertLegacy($insert);
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function find(string $alias): ?array
    {
        $query = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName('#__joomcookies_services'))
            ->where($this->db->quoteName('alias') . ' = ' . $this->db->quote($alias));
        $this->db->setQuery($query, 0, 1);
        $row = $this->db->loadAssoc();

        return \is_array($row) ? $row : null;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function update(int $id, array $row, string $now): void
    {
        $pages = $row['pages'] ?? [];

        if (\is_string($pages)) {
            $decoded = json_decode($pages, true);
            $pages = \is_array($decoded) ? $decoded : [];
        }

        $sets = [
            $this->db->quoteName('modified') . ' = ' . $this->db->quote($now),
            $this->db->quoteName('last_scan_id') . ' = ' . (int) ($row['last_scan_id'] ?? 0),
            $this->db->quoteName('last_evidence') . ' = ' . $this->db->quote(mb_substr((string) ($row['last_evidence'] ?? ''), 0, 32)),
            $this->db->quoteName('page_count') . ' = ' . (int) ($row['page_count'] ?? \count($pages)),
            $this->db->quoteName('pages') . ' = ' . $this->db->quote(json_encode(array_values($pages), JSON_UNESCAPED_SLASHES) ?: '[]'),
            $this->db->quoteName('category_suggested') . ' = ' . $this->db->quote(self::safeCategory((string) ($row['category_suggested'] ?? ''))),
            $this->db->quoteName('name') . ' = ' . $this->db->quote(mb_substr((string) ($row['name'] ?? ''), 0, 255)),
            $this->db->quoteName('provider') . ' = ' . $this->db->quote(mb_substr((string) ($row['provider'] ?? ''), 0, 191)),
            $this->db->quoteName('category') . ' = ' . $this->db->quote(self::safeCategory((string) ($row['category'] ?? 'unclassified'))),
            $this->db->quoteName('match_type') . ' = ' . $this->db->quote((string) ($row['match_type'] ?? 'domain')),
            $this->db->quoteName('match_value') . ' = ' . $this->db->quote(mb_substr((string) ($row['match_value'] ?? ''), 0, 255)),
        ];

        $query = $this->db->getQuery(true)
            ->update($this->db->quoteName('#__joomcookies_services'))
            ->set($sets)
            ->where($this->db->quoteName('id') . ' = ' . $id);

        try {
            $this->db->setQuery($query)->execute();
        } catch (\Throwable $exception) {
            if (!str_contains(strtolower($exception->getMessage()), 'unknown column')) {
                throw $exception;
            }
        }
    }

    /**
     * @param  array<string, mixed>  $insert
     */
    private function upsertLegacy(array $insert): void
    {
        unset($insert['category_suggested'], $insert['classification_status'], $insert['last_scan_id'], $insert['last_evidence'], $insert['page_count'], $insert['pages']);
        $query = $this->db->getQuery(true)
            ->insert($this->db->quoteName('#__joomcookies_services'))
            ->columns(array_keys($insert))
            ->values(implode(',', array_map(fn ($v) => \is_int($v) ? (string) $v : $this->db->quote($v), array_values($insert))));
        $this->db->setQuery($query)->execute();
    }

    /**
     * @param  array<string, mixed>  $existing
     * @param  array<string, mixed>  $incoming
     * @return list<string>
     */
    private static function mergePages(array $existing, array $incoming): array
    {
        $pages = $incoming['pages'] ?? [];

        if (\is_string($pages)) {
            $decoded = json_decode($pages, true);
            $pages = \is_array($decoded) ? $decoded : [];
        }

        $current = $existing['pages'] ?? [];

        if (\is_string($current)) {
            $decoded = json_decode($current, true);
            $current = \is_array($decoded) ? $decoded : [];
        }

        if (!\is_array($current)) {
            $current = [];
        }

        foreach ($pages as $page) {
            if (\is_string($page) && $page !== '' && !\in_array($page, $current, true)) {
                $current[] = $page;
            }
        }

        return array_slice(array_values($current), 0, 50);
    }
}
