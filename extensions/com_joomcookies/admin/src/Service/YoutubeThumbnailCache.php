<?php

namespace JoomCookies\Component\Joomcookies\Administrator\Service;

\defined('_JEXEC') or die;

/** Downloads only the fixed YouTube thumbnail URL, from an administrator action. */
final class YoutubeThumbnailCache
{
    public static function validId(string $id): bool
    {
        return preg_match('/^[A-Za-z0-9_-]{11}$/', $id) === 1;
    }

    public static function find(string $id): ?string
    {
        if (!self::validId($id)) { return null; }
        foreach (['webp', 'jpg'] as $extension) {
            $file = self::directory() . '/' . $id . '.' . $extension;
            if (is_file($file)) { return $file; }
        }
        return null;
    }

    public static function fetch(string $id): string
    {
        if (!self::validId($id)) { throw new \InvalidArgumentException('Invalid YouTube video ID.'); }
        if (($cached = self::find($id)) !== null) { return $cached; }
        $directory = self::directory();
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) { throw new \RuntimeException('Cannot create thumbnail cache.'); }
        $context = stream_context_create(['http' => ['timeout' => 10, 'follow_location' => 0, 'user_agent' => 'JoomCookies thumbnail cache/2.2']]);
        $bytes = @file_get_contents('https://i.ytimg.com/vi/' . rawurlencode($id) . '/hqdefault.jpg', false, $context);
        if (!is_string($bytes) || $bytes === '' || strlen($bytes) > 3 * 1024 * 1024) { throw new \RuntimeException('Thumbnail download failed.'); }
        $info = @getimagesizefromstring($bytes);
        if (!is_array($info) || ($info[2] ?? 0) !== IMAGETYPE_JPEG) { throw new \RuntimeException('Thumbnail is not a JPEG image.'); }
        $jpg = $directory . '/' . $id . '.jpg';
        if (function_exists('imagecreatefromstring') && function_exists('imagewebp')) {
            $image = @imagecreatefromstring($bytes);
            if ($image !== false) {
                $webp = $directory . '/' . $id . '.webp';
                if (@imagewebp($image, $webp, 82)) { imagedestroy($image); return $webp; }
                imagedestroy($image);
            }
        }
        if (@file_put_contents($jpg, $bytes, LOCK_EX) === false) { throw new \RuntimeException('Cannot save thumbnail cache.'); }
        return $jpg;
    }

    private static function directory(): string { return rtrim(JPATH_ROOT, '/\\') . '/images/youtube'; }
}
