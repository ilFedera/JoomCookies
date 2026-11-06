<?php

/**
 * @package     JoomCookies
 * @subpackage  com_joomcookies
 */

namespace JoomCookies\Component\Joomcookies\Administrator\Service\Scan;

\defined('_JEXEC') or die;

final class HtmlAnalyzer
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function analyze(string $html, string $pageUrl, string $pageHost): array
    {
        $findings = [];
        $findings = array_merge($findings, self::scripts($html, $pageUrl, $pageHost));
        $findings = array_merge($findings, self::iframes($html, $pageUrl, $pageHost));
        $findings = array_merge($findings, self::youtubePlayers($html, $pageUrl, $pageHost));
        $findings = array_merge($findings, self::preconnects($html, $pageUrl, $pageHost));
        $findings = array_merge($findings, self::codeReferences($html, $pageUrl));

        return $findings;
    }

    /**
     * @return list<string>
     */
    public static function internalLinks(string $html, string $baseUrl, SsrfGuard $guard): array
    {
        $links = [];

        if (preg_match_all('/<a\s[^>]*href=("([^"]*)"|\'([^\']*)\'|([^\s>]+))/i', $html, $matches, PREG_SET_ORDER) !== false) {
            foreach ($matches as $match) {
                $href = html_entity_decode($match[2] !== '' ? $match[2] : ($match[3] !== '' ? $match[3] : $match[4]), ENT_QUOTES, 'UTF-8');
                $abs  = self::absolutize($href, $baseUrl);

                if ($abs === null) {
                    continue;
                }

                try {
                    $safe = $guard->assertSafe($abs);
                } catch (\InvalidArgumentException) {
                    continue;
                }

                if (UrlPolicy::isSafeToCrawl($safe)) {
                    $links[] = UrlPolicy::normalize($safe);
                }
            }
        }

        foreach (self::canonicalUrls($html, $baseUrl, $guard) as $canonical) {
            $links[] = $canonical;
        }

        return array_values(array_unique($links));
    }

    /**
     * @return list<string>
     */
    public static function canonicalUrls(string $html, string $baseUrl, SsrfGuard $guard): array
    {
        $urls = [];

        if (preg_match_all('/<link\s[^>]*rel=("canonical"|\'canonical\')[^>]*>/i', $html, $matches) === false) {
            return $urls;
        }

        foreach ($matches[0] as $tag) {
            $href = self::attr($tag, 'href');
            $abs  = self::absolutize($href, $baseUrl);

            if ($abs === null) {
                continue;
            }

            try {
                $safe = $guard->assertSafe($abs);
            } catch (\InvalidArgumentException) {
                continue;
            }

            if (UrlPolicy::isSafeToCrawl($safe)) {
                $urls[] = UrlPolicy::normalize($safe);
            }
        }

        return array_values(array_unique($urls));
    }

    /**
     * @return list<string>
     */
    public static function sitemapUrls(string $xml, SsrfGuard $guard): array
    {
        $urls = [];
        preg_match_all('#<loc>\s*([^<]+)\s*</loc>#i', $xml, $matches);

        foreach ($matches[1] ?? [] as $loc) {
            $loc = html_entity_decode(trim((string) $loc), ENT_QUOTES, 'UTF-8');

            try {
                $safe = $guard->assertSafe($loc);
            } catch (\InvalidArgumentException) {
                continue;
            }

            if (UrlPolicy::isSafeToCrawl($safe)) {
                $urls[] = UrlPolicy::normalize($safe);
            }
        }

        return array_values(array_unique($urls));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function scripts(string $html, string $pageUrl, string $pageHost): array
    {
        $out = [];

        if (preg_match_all('#<script(\s[^>]*)?>(.*?)</script>#is', $html, $matches, PREG_SET_ORDER) === false) {
            return $out;
        }

        foreach ($matches as $match) {
            $attrs = $match[1] ?? '';
            $src = self::attr($attrs, 'src') ?: self::attr($attrs, 'data-jck-src');
            $id  = self::attr($attrs, 'id');
            $js  = $match[2] ?? '';
            $blocked = self::attr($attrs, 'data-jck-managed') !== ''
                || str_contains(strtolower($attrs), 'text/plain');
            $hit = ServiceCatalog::identify($src, $js);
            $host = strtolower((string) parse_url($src, PHP_URL_HOST));
            $evidence = $blocked ? 'blocked' : 'reference';

            if ($src !== '' && $host !== '' && $host !== strtolower($pageHost) && $hit === null) {
                $out[] = self::finding('script', 'unknown', $src, $pageUrl, null, $evidence, [
                    'script_id' => $id,
                    'unknown'   => true,
                    'blocked'   => $blocked,
                    'loaded'    => false,
                ]);
                continue;
            }

            if ($hit === null && $src === '') {
                continue;
            }

            if ($hit !== null) {
                $out[] = self::finding('script', $hit['id'], $src !== '' ? $src : $pageUrl, $pageUrl, $hit, $evidence, [
                    'script_id' => $id,
                    'inline'    => $src === '',
                    'blocked'   => $blocked,
                    'loaded'    => !$blocked,
                ]);
            }
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function youtubePlayers(string $html, string $pageUrl, string $pageHost): array
    {
        $out = [];

        if (preg_match_all('#<div\b[^>]*\byoutube-player\b[^>]*>#i', $html, $matches) === false) {
            return $out;
        }

        foreach ($matches[0] as $tag) {
            $id = self::attr($tag, 'data-id') ?: self::attr($tag, 'data-jck-youtube');

            if ($id === '') {
                continue;
            }

            $target = preg_match('/^[A-Za-z0-9_-]{11}$/', $id) === 1
                ? 'https://www.youtube.com/embed/' . $id
                : $id;
            $hit = ServiceCatalog::identify($target, '');

            $out[] = self::finding('iframe', $hit['id'] ?? 'youtube', $target, $pageUrl, $hit, 'blocked', [
                'youtube_player' => true,
                'blocked'        => true,
                'loaded'         => false,
            ]);
        }

        if (preg_match_all('#data-jck-youtube=["\']([^"\']+)["\']#i', $html, $ids) !== false) {
            foreach (array_unique($ids[1]) as $id) {
                $id = trim((string) $id);

                if ($id === '') {
                    continue;
                }

                $target = preg_match('/^[A-Za-z0-9_-]{11}$/', $id) === 1
                    ? 'https://www.youtube.com/embed/' . $id
                    : $id;
                $hit = ServiceCatalog::identify($target, '');
                $out[] = self::finding('iframe', $hit['id'] ?? 'youtube', $target, $pageUrl, $hit, 'blocked', [
                    'youtube_placeholder' => true,
                    'blocked'             => true,
                    'loaded'              => false,
                ]);
            }
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function iframes(string $html, string $pageUrl, string $pageHost): array
    {
        $out = [];

        if (preg_match_all('#<iframe(\s[^>]*)?>#is', $html, $matches, PREG_SET_ORDER) === false) {
            return $out;
        }

        foreach ($matches as $match) {
            $attrs = $match[1] ?? '';
            $src = self::attr($attrs, 'src')
                ?: self::attr($attrs, 'data-jck-src')
                ?: self::attr($attrs, 'data-jck-embed');

            if ($src === '') {
                continue;
            }

            $blocked = self::attr($attrs, 'data-jck-managed') !== ''
                || self::attr($attrs, 'data-jck-src') !== ''
                || self::attr($attrs, 'data-jck-embed') !== '';
            $hit = ServiceCatalog::identify($src, '');
            $evidence = $blocked ? 'blocked' : 'reference';

            $out[] = self::finding('iframe', $hit['id'] ?? 'unknown', $src, $pageUrl, $hit, $evidence, [
                'unknown' => $hit === null,
                'blocked' => $blocked,
                'loaded'  => !$blocked,
            ]);
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function preconnects(string $html, string $pageUrl, string $pageHost): array
    {
        $out = [];

        if (preg_match_all('#<link\s[^>]*>#is', $html, $matches) === false) {
            return $out;
        }

        foreach ($matches[0] as $tag) {
            $rel = strtolower(self::attr(' ' . $tag, 'rel'));

            if (!str_contains($rel, 'preconnect') && !str_contains($rel, 'dns-prefetch')) {
                continue;
            }

            $href = self::attr(' ' . $tag, 'href');

            if ($href === '') {
                continue;
            }

            $hit = ServiceCatalog::identify($href, '');
            $out[] = self::finding('preconnect', $hit['id'] ?? 'unknown', $href, $pageUrl, $hit, 'reference', [
                'unknown' => $hit === null,
            ]);
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function codeReferences(string $html, string $pageUrl): array
    {
        $out = [];

        if (preg_match_all('/document\.cookie\s*=\s*[\'"]([A-Za-z0-9._-]+)=/i', $html, $matches)) {
            foreach (array_unique($matches[1]) as $name) {
                $out[] = self::finding('cookie_reference', (string) $name, $pageUrl, $pageUrl, null, 'reference', [
                    'name' => $name,
                    'note' => 'JavaScript assignment detected in HTML source. Not proof that a cookie was set.',
                ]);
            }
        }

        if (preg_match_all('/localStorage\.(?:setItem|getItem)\(\s*[\'"]([^\'"]+)[\'"]/i', $html, $matches)) {
            foreach (array_unique($matches[1]) as $name) {
                $out[] = self::finding('storage_reference', (string) $name, $pageUrl, $pageUrl, null, 'reference', [
                    'name' => $name,
                    'store' => 'localStorage',
                    'note' => 'Reference in source. Not an observed write.',
                ]);
            }
        }

        if (preg_match_all('/sessionStorage\.(?:setItem|getItem)\(\s*[\'"]([^\'"]+)[\'"]/i', $html, $matches)) {
            foreach (array_unique($matches[1]) as $name) {
                $out[] = self::finding('storage_reference', (string) $name, $pageUrl, $pageUrl, null, 'reference', [
                    'name' => $name,
                    'store' => 'sessionStorage',
                    'note' => 'Reference in source. Not an observed write.',
                ]);
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>|null  $hit
     * @param  array<string, mixed>       $meta
     * @return array<string, mixed>
     */
    public static function finding(string $type, string $name, string $target, string $pageUrl, ?array $hit, string $evidenceKind, array $meta = []): array
    {
        $host = strtolower((string) (parse_url($target, PHP_URL_HOST) ?: parse_url($pageUrl, PHP_URL_HOST) ?: ''));

        return [
            'type'               => $type,
            'name'               => $name,
            'domain'             => $host,
            'page_url'           => $pageUrl,
            'target'             => $target,
            'category_suggested' => $hit['category'] ?? '',
            'service_id'         => $hit['id'] ?? '',
            'provider'           => $hit['provider'] ?? '',
            'evidence_kind'      => $evidenceKind,
            'observed'           => $evidenceKind === 'observed' ? 1 : 0,
            'meta'               => $meta,
        ];
    }

    private static function attr(string $attributes, string $name): string
    {
        if (preg_match('/\s' . preg_quote($name, '/') . '\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+))/i', $attributes, $match) !== 1) {
            return '';
        }

        return html_entity_decode($match[1] !== '' ? $match[1] : ($match[2] !== '' ? $match[2] : $match[3]), ENT_QUOTES, 'UTF-8');
    }

    public static function absolutize(string $href, string $baseUrl): ?string
    {
        $href = trim($href);

        if ($href === '' || str_starts_with($href, '#') || str_starts_with($href, 'mailto:') || str_starts_with($href, 'javascript:') || str_starts_with($href, 'data:')) {
            return null;
        }

        if (preg_match('#^https?://#i', $href) === 1) {
            return $href;
        }

        if (str_starts_with($href, '//')) {
            $scheme = parse_url($baseUrl, PHP_URL_SCHEME) ?: 'https';

            return $scheme . ':' . $href;
        }

        $base = parse_url($baseUrl);

        if (!\is_array($base) || empty($base['scheme']) || empty($base['host'])) {
            return null;
        }

        $origin = $base['scheme'] . '://' . $base['host'];

        if (str_starts_with($href, '/')) {
            return $origin . $href;
        }

        $dir = preg_replace('#/[^/]*$#', '/', (string) ($base['path'] ?? '/')) ?: '/';

        return $origin . $dir . $href;
    }
}
