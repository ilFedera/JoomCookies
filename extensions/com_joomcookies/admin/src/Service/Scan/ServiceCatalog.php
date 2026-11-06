<?php

/**
 * @package     JoomCookies
 * @subpackage  com_joomcookies
 */

namespace JoomCookies\Component\Joomcookies\Administrator\Service\Scan;

\defined('_JEXEC') or die;

/**
 * Heuristic catalogue used only to *suggest* a category. Never enables blocking.
 */
final class ServiceCatalog
{
    /**
     * @return list<array{id: string, name: string, provider: string, category: string, match_type: string, match_value: string, cookies: list<string>}>
     */
    public static function all(): array
    {
        return [
            ['id' => 'gtm', 'name' => 'Google Tag Manager', 'provider' => 'Google', 'category' => 'statistics', 'match_type' => 'domain', 'match_value' => 'googletagmanager.com', 'cookies' => ['_ga', '_gid', '_gat', '_gcl_au']],
            ['id' => 'ga', 'name' => 'Google Analytics', 'provider' => 'Google', 'category' => 'statistics', 'match_type' => 'domain', 'match_value' => 'google-analytics.com', 'cookies' => ['_ga', '_gid', '_gat']],
            ['id' => 'hotjar', 'name' => 'Hotjar', 'provider' => 'Hotjar', 'category' => 'statistics', 'match_type' => 'domain', 'match_value' => 'hotjar.com', 'cookies' => ['_hjSessionUser', '_hjSession']],
            ['id' => 'matomo', 'name' => 'Matomo', 'provider' => 'Matomo', 'category' => 'statistics', 'match_type' => 'domain', 'match_value' => 'matomo.cloud', 'cookies' => ['_pk_id', '_pk_ses']],
            ['id' => 'facebook', 'name' => 'Meta Pixel', 'provider' => 'Meta', 'category' => 'marketing', 'match_type' => 'domain', 'match_value' => 'facebook.net', 'cookies' => ['_fbp', '_fbc']],
            ['id' => 'facebook-com', 'name' => 'Facebook', 'provider' => 'Meta', 'category' => 'marketing', 'match_type' => 'domain', 'match_value' => 'facebook.com', 'cookies' => ['_fbp']],
            ['id' => 'doubleclick', 'name' => 'DoubleClick', 'provider' => 'Google', 'category' => 'marketing', 'match_type' => 'domain', 'match_value' => 'doubleclick.net', 'cookies' => []],
            ['id' => 'googleads', 'name' => 'Google Ads', 'provider' => 'Google', 'category' => 'marketing', 'match_type' => 'domain', 'match_value' => 'googleadservices.com', 'cookies' => []],
            ['id' => 'adsense', 'name' => 'Google AdSense', 'provider' => 'Google', 'category' => 'marketing', 'match_type' => 'domain', 'match_value' => 'googlesyndication.com', 'cookies' => []],
            ['id' => 'youtube', 'name' => 'YouTube', 'provider' => 'Google', 'category' => 'embeds', 'match_type' => 'domain', 'match_value' => 'youtube.com', 'cookies' => []],
            ['id' => 'youtube-nocookie', 'name' => 'YouTube nocookie', 'provider' => 'Google', 'category' => 'embeds', 'match_type' => 'domain', 'match_value' => 'youtube-nocookie.com', 'cookies' => []],
            ['id' => 'youtu-be', 'name' => 'YouTube short', 'provider' => 'Google', 'category' => 'embeds', 'match_type' => 'domain', 'match_value' => 'youtu.be', 'cookies' => []],
            ['id' => 'vimeo', 'name' => 'Vimeo', 'provider' => 'Vimeo', 'category' => 'embeds', 'match_type' => 'domain', 'match_value' => 'player.vimeo.com', 'cookies' => []],
            ['id' => 'gmaps', 'name' => 'Google Maps', 'provider' => 'Google', 'category' => 'embeds', 'match_type' => 'url_contains', 'match_value' => 'google.com/maps', 'cookies' => []],
            ['id' => 'gmaps-api', 'name' => 'Google Maps API', 'provider' => 'Google', 'category' => 'embeds', 'match_type' => 'domain', 'match_value' => 'maps.googleapis.com', 'cookies' => []],
            ['id' => 'instagram', 'name' => 'Instagram', 'provider' => 'Meta', 'category' => 'embeds', 'match_type' => 'domain', 'match_value' => 'instagram.com', 'cookies' => []],
            ['id' => 'tiktok', 'name' => 'TikTok', 'provider' => 'TikTok', 'category' => 'embeds', 'match_type' => 'domain', 'match_value' => 'tiktok.com', 'cookies' => []],
            ['id' => 'cookieyes', 'name' => 'CookieYes', 'provider' => 'CookieYes', 'category' => 'necessary', 'match_type' => 'domain', 'match_value' => 'cookieyes.com', 'cookies' => []],
        ];
    }

    /**
     * @return array{id: string, name: string, provider: string, category: string}|null
     */
    public static function identify(string $src, string $inline = ''): ?array
    {
        foreach (self::all() as $service) {
            if (self::matches($service, $src, $inline)) {
                return [
                    'id'       => $service['id'],
                    'name'     => $service['name'],
                    'provider' => $service['provider'],
                    'category' => $service['category'],
                ];
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $service
     */
    public static function matches(array $service, string $src, string $inline): bool
    {
        $type   = $service['match_type'];
        $needle = strtolower((string) $service['match_value']);
        $srcL   = strtolower($src);

        return match ($type) {
            'domain' => $src !== '' && self::hostContains($srcL, $needle),
            'url_contains' => $src !== '' && str_contains($srcL, $needle),
            'content_contains' => $inline !== '' && str_contains(strtolower($inline), $needle),
            default => false,
        };
    }

    public static function hostContains(string $src, string $domain): bool
    {
        $host = strtolower((string) parse_url($src, PHP_URL_HOST));

        if ($host === '') {
            return str_contains(strtolower($src), $domain);
        }

        $domain = strtolower(ltrim($domain, '.'));

        return $host === $domain || str_ends_with($host, '.' . $domain);
    }
}
