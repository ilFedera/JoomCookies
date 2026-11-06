<?php

/**
 * @package     JoomCookies
 * @subpackage  plg_system_joomcookies
 */

namespace JoomCookies\Plugin\System\Joomcookies\Service;

\defined('_JEXEC') or die;

/**
 * Encodes, decodes and validates the first-party consent record.
 *
 * The payload is stored in a strictly necessary cookie and mirrored in localStorage
 * by the frontend. It is not a profiling cookie: it only records category choices,
 * consent version and the time of the choice.
 */
final class ConsentState
{
    public const CATEGORY_NECESSARY  = 'necessary';
    public const CATEGORY_STATISTICS = 'statistics';
    public const CATEGORY_MARKETING  = 'marketing';
    public const CATEGORY_EMBEDS     = 'embeds';

    /**
     * @return list<string>
     */
    public static function categories(): array
    {
        return [
            self::CATEGORY_NECESSARY,
            self::CATEGORY_STATISTICS,
            self::CATEGORY_MARKETING,
            self::CATEGORY_EMBEDS,
        ];
    }

    /**
     * @return array<string, bool>
     */
    public static function deniedOptional(): array
    {
        return [
            self::CATEGORY_NECESSARY  => true,
            self::CATEGORY_STATISTICS => false,
            self::CATEGORY_MARKETING  => false,
            self::CATEGORY_EMBEDS     => false,
        ];
    }

    /**
     * @param  array<string, mixed>  $categories
     * @return array<string, bool>
     */
    public static function normalizeCategories(array $categories): array
    {
        $normalized = self::deniedOptional();

        foreach (self::categories() as $category) {
            if ($category === self::CATEGORY_NECESSARY) {
                $normalized[$category] = true;
                continue;
            }

            $normalized[$category] = !empty($categories[$category]);
        }

        return $normalized;
    }

    /**
     * @param  array<string, bool>  $categories
     */
    public static function encode(array $categories, string $version, int $timestamp = 0): string
    {
        $payload = [
            'v' => $version,
            't' => $timestamp > 0 ? $timestamp : time(),
            'c' => self::normalizeCategories($categories),
        ];

        $json = json_encode($payload, JSON_UNESCAPED_SLASHES);

        if ($json === false) {
            return '';
        }

        return rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
    }

    /**
     * @return array{v: string, t: int, c: array<string, bool>}|null
     */
    public static function decode(string $raw): ?array
    {
        $raw = trim($raw);

        if ($raw === '') {
            return null;
        }

        $json = self::decodePayload($raw);

        if ($json === null) {
            return null;
        }

        $data = json_decode($json, true);

        if (!\is_array($data) || !isset($data['v'], $data['c']) || !\is_array($data['c'])) {
            return null;
        }

        $version = preg_replace('/[^A-Za-z0-9._-]/', '', (string) $data['v']);

        if ($version === '') {
            return null;
        }

        return [
            'v' => $version,
            't' => isset($data['t']) ? (int) $data['t'] : 0,
            'c' => self::normalizeCategories($data['c']),
        ];
    }

    /**
     * @param  array{v: string, t: int, c: array<string, bool>}  $state
     */
    public static function matchesVersion(array $state, string $version): bool
    {
        return hash_equals($version, (string) $state['v']);
    }

    public static function allows(array $state, string $category): bool
    {
        if ($category === self::CATEGORY_NECESSARY) {
            return true;
        }

        $categories = $state['c'] ?? [];

        return !empty($categories[$category]);
    }

    private static function decodePayload(string $raw): ?string
    {
        if (str_starts_with($raw, '{')) {
            return $raw;
        }

        $padded = strtr($raw, '-_', '+/');
        $pad    = \strlen($padded) % 4;

        if ($pad > 0) {
            $padded .= str_repeat('=', 4 - $pad);
        }

        $decoded = base64_decode($padded, true);

        return $decoded === false ? null : $decoded;
    }
}
