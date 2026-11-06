<?php

/**
 * @package     JoomCookies
 * @subpackage  com_joomcookies
 */

namespace JoomCookies\Component\Joomcookies\Administrator\Service\Scan;

\defined('_JEXEC') or die;

/**
 * Suggests a category from Set-Cookie attributes. Name-only guesses are not enough.
 */
final class CookieClassifier
{
    /**
     * @param  array{name: string, domain: string, max_age: ?int, httponly?: bool, path?: string, secure?: bool}  $cookie
     * @return array{id: string, name: string, provider: string, category: string}|null
     */
    public static function suggest(array $cookie, string $pageHost): ?array
    {
        $name = (string) ($cookie['name'] ?? '');
        $host = strtolower((string) ($cookie['domain'] ?? ''));
        $pageHost = strtolower($pageHost);
        $maxAge = $cookie['max_age'] ?? null;
        $httpOnly = !empty($cookie['httponly']);
        $path = (string) ($cookie['path'] ?? '/');
        $firstParty = $host === '' || $host === $pageHost || str_ends_with($pageHost, '.' . $host) || str_ends_with($host, '.' . $pageHost);
        $sessionLike = $maxAge === null || (int) $maxAge === 0;

        if ($name === 'joomla_user_state' && $firstParty) {
            return self::hit('joomla-user-state', 'Joomla user state', 'Joomla', 'necessary');
        }

        if (\in_array($name, ['_ga', '_gid', '_gat', '_gcl_au', '_fbp', '_fbc'], true) || str_starts_with($name, '_ga_')) {
            return self::hit('tracker-cookie', $name, '', 'statistics');
        }

        if ($name === 'PHPSESSID' && $firstParty && $sessionLike) {
            return self::hit('php-session', 'PHP session', 'PHP', 'necessary');
        }

        $hexName = preg_match('/^[a-f0-9]{32}$/i', $name) === 1;

        if ($hexName && $firstParty && $httpOnly && ($path === '' || $path === '/') && $sessionLike) {
            return self::hit('joomla-session', 'Joomla session (attribute match)', 'Joomla', 'necessary');
        }

        return null;
    }

    /**
     * @return array{id: string, name: string, provider: string, category: string}
     */
    private static function hit(string $id, string $name, string $provider, string $category): array
    {
        return [
            'id'       => $id,
            'name'     => $name,
            'provider' => $provider,
            'category' => $category,
        ];
    }
}
