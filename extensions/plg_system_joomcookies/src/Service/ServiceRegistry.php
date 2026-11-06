<?php

/**
 * @package     JoomCookies
 * @subpackage  plg_system_joomcookies
 */

namespace JoomCookies\Plugin\System\Joomcookies\Service;

\defined('_JEXEC') or die;

/**
 * Configurable catalogue of non-essential services and match rules.
 */
final class ServiceRegistry
{
    /**
     * @var list<array<string, mixed>>
     */
    private array $services;

    /**
     * @param  list<array<string, mixed>>|array<string, mixed>  $configured
     */
    public function __construct(array $configured = [])
    {
        // Site-approved rules override generic signatures.
        $this->services = array_merge($this->normalize($configured), self::builtIn());
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function all(): array
    {
        return $this->services;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function forFrontend(): array
    {
        $export = [];

        foreach ($this->services as $service) {
            $export[] = [
                'id'       => $service['id'],
                'name'     => $service['name'],
                'category' => $service['category'],
                'cookies'  => $service['cookies'],
            ];
        }

        return $export;
    }

    /**
     * @return array{category: string, id: string}|null
     */
    public function matchScript(string $src, string $inline, string $idAttribute = ''): ?array
    {
        foreach ($this->services as $service) {
            if ($this->matches($service, $src, $inline, $idAttribute, 'script')) {
                return [
                    'category' => $service['category'],
                    'id'       => $service['id'],
                ];
            }
        }

        return null;
    }

    /**
     * @return array{category: string, id: string}|null
     */
    public function matchFrame(string $src): ?array
    {
        foreach ($this->services as $service) {
            if ($this->matches($service, $src, '', '', 'iframe')) {
                return [
                    'category' => $service['category'],
                    'id'       => $service['id'],
                ];
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $service
     */
    private function matches(array $service, string $src, string $inline, string $idAttribute, string $kind): bool
    {
        $type  = $service['match_type'];
        $value = $service['match_value'];

        if ($value === '') {
            return false;
        }

        if ($kind === 'iframe' && !\in_array($type, ['domain', 'url_contains'], true)) {
            return false;
        }

        $srcLower    = strtolower($src);
        $inlineLower = strtolower($inline);
        $needle      = strtolower($value);

        return match ($type) {
            'domain' => $src !== '' && self::hostContains($srcLower, $needle),
            'url_contains' => $src !== '' && str_contains($srcLower, $needle),
            'script_id' => $idAttribute !== '' && strcasecmp($idAttribute, $value) === 0,
            'content_contains' => $inline !== '' && str_contains($inlineLower, $needle),
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

    /**
     * @param  list<array<string, mixed>>|array<string, mixed>  $configured
     * @return list<array<string, mixed>>
     */
    private function normalize(array $configured): array
    {
        $services = [];

        if ($configured === []) {
            return $services;
        }

        if (isset($configured['id']) || isset($configured['match_value'])) {
            $configured = [$configured];
        }

        foreach ($configured as $row) {
            if (!\is_array($row)) {
                continue;
            }

            $id       = self::slug((string) ($row['id'] ?? $row['name'] ?? ''));
            $category = (string) ($row['category'] ?? '');
            $value    = trim((string) ($row['match_value'] ?? ''));

            if ($id === '' || $value === '' || !\in_array($category, ConsentState::categories(), true)) {
                continue;
            }

            if ($category === ConsentState::CATEGORY_NECESSARY) {
                continue;
            }

            $type = (string) ($row['match_type'] ?? 'domain');

            if (!\in_array($type, ['domain', 'url_contains', 'script_id', 'content_contains'], true)) {
                $type = 'domain';
            }

            $cookies = $row['cookies'] ?? '';

            if (\is_array($cookies)) {
                $cookies = implode(',', $cookies);
            }

            $services[] = [
                'id'          => $id,
                'name'        => trim((string) ($row['name'] ?? $id)),
                'category'    => $category,
                'match_type'  => $type,
                'match_value' => $value,
                'cookies'     => self::cookieNames((string) $cookies),
            ];
        }

        return $services;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function builtIn(): array
    {
        $rows = [
            ['id' => 'gtm', 'name' => 'Google Tag Manager', 'category' => ConsentState::CATEGORY_STATISTICS, 'match_type' => 'domain', 'match_value' => 'googletagmanager.com', 'cookies' => '_ga,_gid,_gat,_gcl_au'],
            ['id' => 'ga', 'name' => 'Google Analytics', 'category' => ConsentState::CATEGORY_STATISTICS, 'match_type' => 'domain', 'match_value' => 'google-analytics.com', 'cookies' => '_ga,_gid,_gat'],
            ['id' => 'gtag-js', 'name' => 'Google gtag.js', 'category' => ConsentState::CATEGORY_STATISTICS, 'match_type' => 'url_contains', 'match_value' => 'googletagmanager.com/gtag/js', 'cookies' => '_ga,_gid,_gat'],
            ['id' => 'yootheme-analytics', 'name' => 'YOOtheme Google Analytics', 'category' => ConsentState::CATEGORY_STATISTICS, 'match_type' => 'url_contains', 'match_value' => 'theme-analytics/app/analytics', 'cookies' => '_ga,_gid,_gat'],
            ['id' => 'gtag-inline', 'name' => 'Google gtag inline', 'category' => ConsentState::CATEGORY_STATISTICS, 'match_type' => 'content_contains', 'match_value' => 'gtag(', 'cookies' => '_ga,_gid'],
            ['id' => 'datalayer-gtm', 'name' => 'Google dataLayer GTM', 'category' => ConsentState::CATEGORY_STATISTICS, 'match_type' => 'content_contains', 'match_value' => 'googletagmanager.com/gtm.js', 'cookies' => '_ga'],
            ['id' => 'datalayer-gtag', 'name' => 'Google dataLayer gtag', 'category' => ConsentState::CATEGORY_STATISTICS, 'match_type' => 'content_contains', 'match_value' => 'googletagmanager.com/gtag/js', 'cookies' => '_ga,_gid'],
            ['id' => 'hotjar', 'name' => 'Hotjar', 'category' => ConsentState::CATEGORY_STATISTICS, 'match_type' => 'domain', 'match_value' => 'hotjar.com', 'cookies' => '_hjSessionUser,_hjSession'],
            ['id' => 'facebook', 'name' => 'Meta Pixel', 'category' => ConsentState::CATEGORY_MARKETING, 'match_type' => 'domain', 'match_value' => 'facebook.net', 'cookies' => '_fbp,_fbc'],
            ['id' => 'facebook-connect', 'name' => 'Meta Connect', 'category' => ConsentState::CATEGORY_MARKETING, 'match_type' => 'domain', 'match_value' => 'connect.facebook.net', 'cookies' => '_fbp'],
            ['id' => 'fbq-inline', 'name' => 'Meta fbq inline', 'category' => ConsentState::CATEGORY_MARKETING, 'match_type' => 'content_contains', 'match_value' => 'fbq(', 'cookies' => '_fbp'],
            ['id' => 'doubleclick', 'name' => 'DoubleClick', 'category' => ConsentState::CATEGORY_MARKETING, 'match_type' => 'domain', 'match_value' => 'doubleclick.net', 'cookies' => ''],
            ['id' => 'googleads', 'name' => 'Google Ads', 'category' => ConsentState::CATEGORY_MARKETING, 'match_type' => 'domain', 'match_value' => 'googleadservices.com', 'cookies' => ''],
            ['id' => 'adsense', 'name' => 'Google AdSense', 'category' => ConsentState::CATEGORY_MARKETING, 'match_type' => 'domain', 'match_value' => 'googlesyndication.com', 'cookies' => ''],
            // Consent-managed anti-bot assets; local adapters must also be held.
            ['id' => 'recaptcha-api', 'name' => 'Google reCAPTCHA API', 'category' => ConsentState::CATEGORY_EMBEDS, 'match_type' => 'url_contains', 'match_value' => '/recaptcha/api.js', 'cookies' => ''],
            ['id' => 'recaptcha-gstatic', 'name' => 'Google reCAPTCHA runtime', 'category' => ConsentState::CATEGORY_EMBEDS, 'match_type' => 'url_contains', 'match_value' => 'gstatic.com/recaptcha/', 'cookies' => ''],
            ['id' => 'recaptcha-convertforms', 'name' => 'Convert Forms reCAPTCHA v3', 'category' => ConsentState::CATEGORY_EMBEDS, 'match_type' => 'url_contains', 'match_value' => 'com_convertforms/js/recaptcha_v3.js', 'cookies' => ''],
            ['id' => 'youtube', 'name' => 'YouTube', 'category' => ConsentState::CATEGORY_EMBEDS, 'match_type' => 'domain', 'match_value' => 'youtube.com', 'cookies' => ''],
            ['id' => 'youtube-nocookie', 'name' => 'YouTube nocookie', 'category' => ConsentState::CATEGORY_EMBEDS, 'match_type' => 'domain', 'match_value' => 'youtube-nocookie.com', 'cookies' => ''],
            ['id' => 'youtu-be', 'name' => 'YouTube short', 'category' => ConsentState::CATEGORY_EMBEDS, 'match_type' => 'domain', 'match_value' => 'youtu.be', 'cookies' => ''],
            ['id' => 'vimeo', 'name' => 'Vimeo', 'category' => ConsentState::CATEGORY_EMBEDS, 'match_type' => 'domain', 'match_value' => 'player.vimeo.com', 'cookies' => ''],
            ['id' => 'gmaps', 'name' => 'Google Maps embed', 'category' => ConsentState::CATEGORY_EMBEDS, 'match_type' => 'url_contains', 'match_value' => 'google.com/maps', 'cookies' => ''],
            ['id' => 'gmaps-api', 'name' => 'Google Maps API', 'category' => ConsentState::CATEGORY_EMBEDS, 'match_type' => 'domain', 'match_value' => 'maps.googleapis.com', 'cookies' => ''],
            ['id' => 'instagram', 'name' => 'Instagram embed', 'category' => ConsentState::CATEGORY_EMBEDS, 'match_type' => 'domain', 'match_value' => 'instagram.com', 'cookies' => ''],
            ['id' => 'tiktok', 'name' => 'TikTok embed', 'category' => ConsentState::CATEGORY_EMBEDS, 'match_type' => 'domain', 'match_value' => 'tiktok.com', 'cookies' => ''],
        ];

        foreach ($rows as &$row) {
            $row['cookies'] = self::cookieNames($row['cookies']);
        }

        unset($row);

        return $rows;
    }

    /**
     * @return list<string>
     */
    public static function cookieNames(string $raw): array
    {
        $names = [];

        foreach (preg_split('/[\s,;]+/', $raw) ?: [] as $name) {
            $name = preg_replace('/[^A-Za-z0-9._-]/', '', $name);

            if ($name !== '') {
                $names[] = $name;
            }
        }

        return array_values(array_unique($names));
    }

    public static function slug(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9_-]+/', '-', $value) ?? '';

        return trim($value, '-');
    }
}
