<?php

/**
 * @package     JoomCookies
 * @subpackage  plg_system_joomcookies
 */

namespace JoomCookies\Plugin\System\Joomcookies\Service;

\defined('_JEXEC') or die;

/**
 * Parses YouTube shortcodes and iframe URLs without contacting YouTube.
 */
final class YoutubeEmbed
{
    public const SHORTCODE_PATTERN = '/\{joomcookies\s+youtube(?:=|\s+id=|\s+video=)?(?:"([^"]+)"|\'([^\']+)\'|([A-Za-z0-9_-]{11}))([^}]*)\}/i';

    public static function videoId(string $value): ?string
    {
        $value = trim($value);

        if (preg_match('/^[A-Za-z0-9_-]{11}$/', $value) === 1) {
            return $value;
        }

        $parts = parse_url($value);

        if (!\is_array($parts)) {
            return null;
        }

        $host = strtolower((string) ($parts['host'] ?? ''));
        $path = (string) ($parts['path'] ?? '');

        if (str_contains($host, 'youtu.be')) {
            $id = trim($path, '/');

            return preg_match('/^[A-Za-z0-9_-]{11}$/', $id) === 1 ? $id : null;
        }

        if (str_contains($host, 'youtube.com') || str_contains($host, 'youtube-nocookie.com')) {
            if (isset($parts['query'])) {
                parse_str($parts['query'], $query);

                if (!empty($query['v']) && preg_match('/^[A-Za-z0-9_-]{11}$/', (string) $query['v']) === 1) {
                    return (string) $query['v'];
                }
            }

            if (preg_match('#/(?:embed|shorts|live|v)/([A-Za-z0-9_-]{11})#', $path, $match) === 1) {
                return $match[1];
            }
        }

        return null;
    }

    /**
     * @return array{id: string, title: string, ratio: string}|null
     */
    public static function parseShortcode(string $full, string $id1, string $id2, string $id3, string $attrs): ?array
    {
        $id = self::videoId($id1 !== '' ? $id1 : ($id2 !== '' ? $id2 : $id3));

        if ($id === null) {
            return null;
        }

        $title = '';
        $ratio = '16/9';

        if (preg_match('/title=("([^"]*)"|\'([^\']*)\')/i', $attrs, $match) === 1) {
            $title = $match[2] !== '' ? $match[2] : $match[3];
        }

        if (preg_match('/ratio=("([^"]*)"|\'([^\']*)\'|([0-9:.\/]+))/i', $attrs, $match) === 1) {
            $ratio = $match[2] !== '' ? $match[2] : ($match[3] !== '' ? $match[3] : $match[4]);
        }

        return [
            'id'    => $id,
            'title' => $title,
            'ratio' => self::normalizeRatio($ratio),
        ];
    }

    public static function normalizeRatio(string $ratio): string
    {
        $ratio = trim(str_replace(':', '/', $ratio));

        if (preg_match('#^(\d+)\s*/\s*(\d+)$#', $ratio, $match) !== 1) {
            return '16 / 9';
        }

        return ((int) $match[1]) . ' / ' . ((int) $match[2]);
    }

    public static function playerSrc(string $videoId, bool $noCookie = true): string
    {
        $host = $noCookie ? 'https://www.youtube-nocookie.com' : 'https://www.youtube.com';

        return $host . '/embed/' . rawurlencode($videoId);
    }

    public static function thumbnailUrl(string $videoId): string
    {
        return 'https://i.ytimg.com/vi/' . rawurlencode($videoId) . '/hqdefault.jpg';
    }
}
