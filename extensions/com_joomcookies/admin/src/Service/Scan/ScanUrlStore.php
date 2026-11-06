<?php

/**
 * @package     JoomCookies
 * @subpackage  com_joomcookies
 */

namespace JoomCookies\Component\Joomcookies\Administrator\Service\Scan;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;

final class ScanUrlStore
{
    public function __construct(private readonly DatabaseInterface $db)
    {
    }

    public function available(): bool
    {
        static $ok = null;

        if ($ok !== null) {
            return $ok;
        }

        try {
            $this->db->setQuery('SELECT 1 FROM ' . $this->db->quoteName('#__joomcookies_scan_urls') . ' WHERE 0');
            $this->db->execute();
            $ok = true;
        } catch (\Throwable) {
            $ok = false;
        }

        return $ok;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public function record(int $scanId, array $row): void
    {
        if (!$this->available()) {
            return;
        }

        $normalized = mb_substr(UrlPolicy::normalize((string) ($row['normalized'] ?? $row['original_url'] ?? '')), 0, 512);
        $original = UrlPolicy::redact((string) ($row['original_url'] ?? ''));
        $existing = $this->find($scanId, $normalized);

        if ($existing !== null) {
            $current = (string) ($existing['outcome'] ?? '');
            $incoming = (string) ($row['outcome'] ?? '');

            if (\in_array($current, ['analyzed', 'queued', 'error'], true) && \in_array($incoming, ['duplicate', 'queued', 'excluded'], true)) {
                return;
            }

            $this->update((int) $existing['id'], $row);

            return;
        }

        $now = Factory::getDate()->toSql();
        $query = $this->db->getQuery(true)
            ->insert($this->db->quoteName('#__joomcookies_scan_urls'))
            ->columns([
                'scan_id', 'original_url', 'normalized', 'referrer_url', 'discovery',
                'http_status', 'final_url', 'canonical_url', 'outcome', 'reason', 'created_at',
            ])
            ->values(implode(',', [
                $scanId,
                $this->db->quote(mb_substr($original, 0, 1024)),
                $this->db->quote($normalized),
                $this->db->quote(mb_substr(UrlPolicy::redact((string) ($row['referrer_url'] ?? '')), 0, 1024)),
                $this->db->quote(mb_substr((string) ($row['discovery'] ?? 'html_link'), 0, 32)),
                (int) ($row['http_status'] ?? 0),
                $this->db->quote(mb_substr(UrlPolicy::redact((string) ($row['final_url'] ?? '')), 0, 1024)),
                $this->db->quote(mb_substr(UrlPolicy::redact((string) ($row['canonical_url'] ?? '')), 0, 1024)),
                $this->db->quote(mb_substr((string) ($row['outcome'] ?? 'queued'), 0, 16)),
                $this->db->quote(mb_substr((string) ($row['reason'] ?? ''), 0, 255)),
                $this->db->quote($now),
            ]));

        try {
            $this->db->setQuery($query)->execute();
        } catch (\Throwable $exception) {
            $message = strtolower($exception->getMessage());

            if (str_contains($message, 'duplicate') || str_contains($message, '1062')) {
                $existing = $this->find($scanId, $normalized);

                if ($existing !== null) {
                    $this->update((int) $existing['id'], $row);
                }

                return;
            }

            throw $exception;
        }
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public function update(int $id, array $row): void
    {
        if (!$this->available() || $id < 1) {
            return;
        }

        $sets = [];

        foreach (['http_status' => 'int', 'final_url' => 'url', 'canonical_url' => 'url', 'outcome' => 'str', 'reason' => 'str', 'referrer_url' => 'url'] as $field => $kind) {
            if (!array_key_exists($field, $row)) {
                continue;
            }

            if ($kind === 'int') {
                $sets[] = $this->db->quoteName($field) . ' = ' . (int) $row[$field];
                continue;
            }

            $value = (string) $row[$field];

            if ($kind === 'url') {
                $value = UrlPolicy::redact($value);
            }

            $sets[] = $this->db->quoteName($field) . ' = ' . $this->db->quote(mb_substr($value, 0, $field === 'reason' ? 255 : 1024));
        }

        if ($sets === []) {
            return;
        }

        $query = $this->db->getQuery(true)
            ->update($this->db->quoteName('#__joomcookies_scan_urls'))
            ->set($sets)
            ->where($this->db->quoteName('id') . ' = ' . $id);
        $this->db->setQuery($query)->execute();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $scanId, string $normalized): ?array
    {
        if (!$this->available()) {
            return null;
        }

        $query = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName('#__joomcookies_scan_urls'))
            ->where($this->db->quoteName('scan_id') . ' = ' . $scanId)
            ->where($this->db->quoteName('normalized') . ' = ' . $this->db->quote(mb_substr($normalized, 0, 512)));
        $this->db->setQuery($query, 0, 1);
        $row = $this->db->loadAssoc();

        return \is_array($row) ? $row : null;
    }

    public function countOutcome(int $scanId, string $outcome): int
    {
        if (!$this->available()) {
            return 0;
        }

        $query = $this->db->getQuery(true)
            ->select('COUNT(*)')
            ->from($this->db->quoteName('#__joomcookies_scan_urls'))
            ->where($this->db->quoteName('scan_id') . ' = ' . $scanId)
            ->where($this->db->quoteName('outcome') . ' = ' . $this->db->quote($outcome));
        $this->db->setQuery($query);

        return (int) $this->db->loadResult();
    }

    /**
     * @return array{rows: list<array<string, mixed>>, total: int}
     */
    public function list(int $scanId, string $search, string $outcome, string $discovery, int $limit, int $offset): array
    {
        if (!$this->available() || $scanId < 1) {
            return ['rows' => [], 'total' => 0];
        }

        $where = [$this->db->quoteName('scan_id') . ' = ' . $scanId];

        if ($search !== '') {
            $like = $this->db->quote('%' . $this->db->escape($search, true) . '%', false);
            $where[] = '(' . $this->db->quoteName('original_url') . ' LIKE ' . $like
                . ' OR ' . $this->db->quoteName('normalized') . ' LIKE ' . $like
                . ' OR ' . $this->db->quoteName('final_url') . ' LIKE ' . $like . ')';
        }

        if ($outcome !== '') {
            $where[] = $this->db->quoteName('outcome') . ' = ' . $this->db->quote($outcome);
        }

        if ($discovery !== '') {
            $where[] = $this->db->quoteName('discovery') . ' = ' . $this->db->quote($discovery);
        }

        $count = $this->db->getQuery(true)
            ->select('COUNT(*)')
            ->from($this->db->quoteName('#__joomcookies_scan_urls'))
            ->where($where);
        $this->db->setQuery($count);
        $total = (int) $this->db->loadResult();

        $query = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName('#__joomcookies_scan_urls'))
            ->where($where)
            ->order($this->db->quoteName('id') . ' ASC');
        $this->db->setQuery($query, max(0, $offset), max(1, min(200, $limit)));
        $rows = $this->db->loadAssocList() ?: [];

        return ['rows' => $rows, 'total' => $total];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function export(int $scanId): array
    {
        if (!$this->available() || $scanId < 1) {
            return [];
        }

        $query = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName('#__joomcookies_scan_urls'))
            ->where($this->db->quoteName('scan_id') . ' = ' . $scanId)
            ->order($this->db->quoteName('id') . ' ASC');
        $this->db->setQuery($query, 0, 20000);

        return $this->db->loadAssocList() ?: [];
    }
}
