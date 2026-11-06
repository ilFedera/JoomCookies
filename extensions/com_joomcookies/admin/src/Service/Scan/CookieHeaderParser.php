<?php

/**
 * @package     JoomCookies
 * @subpackage  com_joomcookies
 */

namespace JoomCookies\Component\Joomcookies\Administrator\Service\Scan;

\defined('_JEXEC') or die;

final class CookieHeaderParser
{
    /**
     * @param  array<string, mixed>  $headers
     * @return list<array{name: string, domain: string, max_age: ?int, httponly: bool, secure: bool, path: string, raw: string}>
     */
    public static function parse(array $headers): array
    {
        $values = [];

        foreach ($headers as $name => $value) {
            if (strtolower((string) $name) !== 'set-cookie') {
                continue;
            }

            foreach ((array) $value as $line) {
                $values[] = (string) $line;
            }
        }

        $cookies = [];

        foreach ($values as $line) {
            $parsed = self::parseLine($line);

            if ($parsed !== null) {
                $cookies[] = $parsed;
            }
        }

        return $cookies;
    }

    /**
     * @return array{name: string, domain: string, max_age: ?int, httponly: bool, secure: bool, path: string, raw: string}|null
     */
    public static function parseLine(string $line): ?array
    {
        $line = trim($line);

        if ($line === '' || !str_contains($line, '=')) {
            return null;
        }

        $parts = explode(';', $line);
        $nv    = array_shift($parts);
        $eq    = strpos((string) $nv, '=');

        if ($eq === false) {
            return null;
        }

        $name = preg_replace('/[^A-Za-z0-9._-]/', '', substr((string) $nv, 0, $eq));

        if ($name === '') {
            return null;
        }

        $domain = '';
        $maxAge = null;
        $httpOnly = false;
        $secure = false;
        $path = '/';

        foreach ($parts as $attr) {
            $attr = trim($attr);
            $pos  = strpos($attr, '=');
            $key  = strtolower($pos === false ? $attr : substr($attr, 0, $pos));
            $val  = $pos === false ? '' : trim(substr($attr, $pos + 1));

            if ($key === 'domain') {
                $domain = strtolower(ltrim($val, '.'));
            }

            if ($key === 'path') {
                $path = $val !== '' ? $val : '/';
            }

            if ($key === 'httponly') {
                $httpOnly = true;
            }

            if ($key === 'secure') {
                $secure = true;
            }

            if ($key === 'max-age' && is_numeric($val)) {
                $maxAge = (int) $val;
            }

            if ($key === 'expires' && $maxAge === null) {
                $ts = strtotime($val);
                $maxAge = $ts !== false ? max(0, $ts - time()) : null;
            }
        }

        return [
            'name'     => $name,
            'domain'   => $domain,
            'max_age'  => $maxAge,
            'httponly' => $httpOnly,
            'secure'   => $secure,
            'path'     => $path,
            'raw'      => $name,
        ];
    }
}
