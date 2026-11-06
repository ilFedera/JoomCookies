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
    ];

    public static function isSafeToCrawl(string $url): bool
    {
        $parts = parse_url($url);

        if (!\is_array($parts)) {
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

        if (preg_match('/\.(jpg|jpeg|png|gif|webp|svg|css|js|zip|pdf|woff2?|ico|mp4|mp3)$/i', $path) === 1) {
            return false;
        }

        return true;
    }

    public static function normalize(string $url): string
    {
        $parts = parse_url($url);

        if (!\is_array($parts) || empty($parts['host'])) {
            return $url;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? 'https'));
        $host   = strtolower((string) $parts['host']);
        $path   = $parts['path'] ?? '/';
        $query  = isset($parts['query']) ? '?' . $parts['query'] : '';

        if ($path === '') {
            $path = '/';
        }

        return $scheme . '://' . $host . $path . $query;
    }
}
