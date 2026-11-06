<?php

/**
 * @package     JoomCookies
 * @subpackage  com_joomcookies
 */

namespace JoomCookies\Component\Joomcookies\Administrator\Service\Scan;

\defined('_JEXEC') or die;

/**
 * Restricts the crawler to public, read-only pages on the authorised host.
 */
final class UrlPolicy
{
    /**
     * @var list<string>
     */
    private const BLOCKED_PATHS = [
        '/administrator',
        '/api/',
        '/administrator/',
        '/installation/',
    ];

    /**
     * @var list<string>
     */
    private const BLOCKED_NEEDLES = [
        'task=',
        'logout',
        'password',
        'passwd',
        'token=',
        'return=',
        'remember=',
        'option=com_users',
        'option=com_privacy',
        'option=com_config',
        'option=com_joomlaupdate',
        'option=com_login',
        'format=feed',
        'format=raw',
        'print=1',
    ];

    /**
     * @var list<string>
     */
    private const TRACKING_PARAMS = [
        'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'utm_id',
        'fbclid', 'gclid', 'gbraid', 'wbraid', 'dclid', 'msclkid', 'twclid', 'li_fat_id',
        'mc_cid', 'mc_eid', 'igshid', '_ga', '_gl', 'yclid', 'zanpid',
    ];

    /**
     * @var list<string>
     */
    private const SENSITIVE_PARAMS = [
        'token', 'password', 'passwd', 'pass', 'secret', 'key', 'api_key', 'apikey',
    ];

    public static function isHttpUrl(string $url): bool
    {
        $url = trim($url);

        if ($url === '' || str_starts_with($url, '#')) {
            return false;
        }

        if (preg_match('#^([a-z][a-z0-9+.-]*):#i', $url, $match) === 1) {
            $scheme = strtolower($match[1]);

            return $scheme === 'http' || $scheme === 'https';
        }

        return true;
    }

    public static function isSafeToCrawl(string $url): bool
    {
        if (!self::isHttpUrl($url)) {
            return false;
        }

        $parts = parse_url($url);

        if (!\is_array($parts) || empty($parts['host'])) {
            return false;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));

        if ($scheme !== 'http' && $scheme !== 'https') {
            return false;
        }

        $path  = strtolower((string) ($parts['path'] ?? '/'));
        $query = strtolower((string) ($parts['query'] ?? ''));
        $full  = $path . '?' . $query;

        foreach (self::BLOCKED_PATHS as $blocked) {
            if ($path === $blocked || str_starts_with($path, rtrim($blocked, '/') . '/')) {
                return false;
            }
        }

        if (str_contains($path, '/administrator') || str_contains($path, 'com_joomlaupdate')) {
            return false;
        }

        foreach (self::BLOCKED_NEEDLES as $needle) {
            if (str_contains($full, $needle)) {
                return false;
            }
        }

        if (preg_match('/\.(jpg|jpeg|png|gif|webp|svg|css|js|zip|pdf|woff2?|ico|mp4|mp3|map)$/i', $path) === 1) {
            return false;
        }

        return true;
    }

    public static function excludeReason(string $url): string
    {
        if (self::isSafeToCrawl($url)) {
            return '';
        }

        if (!self::isHttpUrl($url)) {
            return 'non_http_scheme';
        }

        $path = strtolower((string) (parse_url($url, PHP_URL_PATH) ?? ''));
        $query = strtolower((string) (parse_url($url, PHP_URL_QUERY) ?? ''));

        if (str_contains($path, '/administrator') || str_contains($path, '/api')) {
            return 'admin_or_api';
        }

        if (preg_match('/\.(jpg|jpeg|png|gif|webp|svg|css|js|zip|pdf|woff2?|ico|mp4|mp3|map)$/i', $path) === 1) {
            return 'static_asset';
        }

        if (str_contains($query, 'token=') || str_contains($query, 'password') || str_contains($query, 'passwd')) {
            return 'sensitive_query';
        }

        return 'policy';
    }

    public static function normalize(string $url): string
    {
        $parts = parse_url($url);

        if (!\is_array($parts) || empty($parts['host'])) {
            return $url;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));

        if ($scheme !== 'http' && $scheme !== 'https') {
            return $url;
        }

        $host   = strtolower((string) $parts['host']);
        $path   = $parts['path'] ?? '/';
        $port   = (int) ($parts['port'] ?? 0);

        if ($path === '') {
            $path = '/';
        }

        if ($path !== '/') {
            $path = rtrim($path, '/');
        }

        $origin = $scheme . '://' . $host;

        if ($port > 0 && !(($scheme === 'http' && $port === 80) || ($scheme === 'https' && $port === 443))) {
            $origin .= ':' . $port;
        }

        $query = '';

        if (isset($parts['query']) && $parts['query'] !== '') {
            parse_str((string) $parts['query'], $params);
            foreach (self::TRACKING_PARAMS as $drop) {
                unset($params[$drop]);
            }
            foreach (self::SENSITIVE_PARAMS as $drop) {
                unset($params[$drop]);
            }
            if ($params !== []) {
                ksort($params);
                $query = '?' . http_build_query($params);
            }
        }

        return $origin . $path . $query;
    }

    public static function redact(string $url): string
    {
        $url = trim($url);

        if ($url !== '' && preg_match('#^([a-z][a-z0-9+.-]*):#i', $url, $match) === 1) {
            $scheme = strtolower($match[1]);

            if ($scheme !== 'http' && $scheme !== 'https') {
                return $scheme . ':';
            }
        }

        $redacted = preg_replace('/([?&](?:' . implode('|', self::SENSITIVE_PARAMS) . ')=)[^&]*/i', '$1***', $url);

        return \is_string($redacted) ? $redacted : $url;
    }
}
