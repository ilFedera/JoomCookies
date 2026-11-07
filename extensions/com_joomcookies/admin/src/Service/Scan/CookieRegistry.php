<?php

namespace JoomCookies\Component\Joomcookies\Administrator\Service\Scan;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;

/** Persists metadata-only cookie observations; cookie values are never accepted. */
final class CookieRegistry
{
    public function __construct(private readonly DatabaseInterface $db) {}

    /** @param list<array<string,mixed>> $findings */
    public function recordFindings(int $scanId, array $findings): void
    {
        foreach ($findings as $finding) {
            if (!in_array((string) ($finding['type'] ?? ''), ['cookie_header', 'cookie_browser'], true)
                || empty($finding['observed']) || (string) ($finding['name'] ?? '') === '') {
                continue;
            }
            $meta = json_decode((string) ($finding['meta'] ?? '{}'), true);
            $meta = is_array($meta) ? $meta : [];
            unset($meta['value'], $meta['cookie_value'], $meta['token']);
            $source = (string) ($finding['evidence_kind'] ?? '') === 'browser' ? 'browser' : 'http';
            $domain = strtolower((string) ($meta['domain'] ?? $finding['domain'] ?? ''));
            $path = substr((string) ($meta['path'] ?? '/'), 0, 255) ?: '/';
            $now = Factory::getDate()->toSql();
            $key = hash('sha256', strtolower((string) $finding['name']) . "\0" . $domain . "\0" . $path);
            $firstParty = (int) (($meta['first_party'] ?? $this->firstParty($domain, (string) ($finding['page_url'] ?? ''))) ? 1 : 0);
            $duration = isset($meta['max_age']) && $meta['max_age'] !== null ? (string) (int) $meta['max_age'] : '';
            $attrs = json_encode([
                'http_only' => !empty($meta['httponly']), 'secure' => !empty($meta['secure']),
                'same_site' => (string) ($meta['same_site'] ?? ''), 'host_only' => !empty($meta['host_only']),
            ], JSON_UNESCAPED_SLASHES) ?: '{}';
            $q = $this->db->getQuery(true)->select($this->db->quoteName('id'))->from($this->db->quoteName('#__joomcookies_cookie_registry'))
                ->where($this->db->quoteName('cookie_key') . ' = ' . $this->db->quote($key));
            $this->db->setQuery($q); $id = (int) $this->db->loadResult();
            $values = ['name' => substr((string) $finding['name'], 0, 191), 'domain' => substr($domain, 0, 191), 'path' => $path,
                'service_id' => '', 'category' => substr((string) ($finding['category_suggested'] ?? ''), 0, 32), 'duration' => $duration,
                'first_party' => $firstParty, 'source' => $source, 'scenario' => substr((string) ($meta['scenario'] ?? 'before_consent'), 0, 32),
                'status' => 'observed', 'last_scan_id' => $scanId, 'last_page_url' => substr((string) ($finding['page_url'] ?? ''), 0, 512),
                'attributes_json' => $attrs, 'last_observed_at' => $now];
            if ($id) {
                $q = $this->db->getQuery(true)->update($this->db->quoteName('#__joomcookies_cookie_registry'));
                foreach ($values as $column => $value) { $q->set($this->db->quoteName($column) . ' = ' . $this->db->quote($value)); }
                $q->where($this->db->quoteName('id') . ' = ' . $id);
            } else {
                $values = ['cookie_key' => $key] + $values + ['created_at' => $now];
                $q = $this->db->getQuery(true)->insert($this->db->quoteName('#__joomcookies_cookie_registry'))->columns(array_map([$this->db, 'quoteName'], array_keys($values)))->values(implode(',', array_map([$this->db, 'quote'], $values)));
            }
            $this->db->setQuery($q)->execute();
        }
    }

    private function firstParty(string $cookieDomain, string $pageUrl): bool
    {
        $pageHost = strtolower((string) parse_url($pageUrl, PHP_URL_HOST));
        return $cookieDomain === '' || $cookieDomain === $pageHost || str_ends_with($pageHost, '.' . ltrim($cookieDomain, '.'));
    }
}
