<?php

/**
 * @package     JoomCookies
 * @subpackage  com_joomcookies
 */

namespace JoomCookies\Component\Joomcookies\Administrator\Service\Migration;

\defined('_JEXEC') or die;

/**
 * Idempotent, reversible mapping of V1 plugin params onto component settings.
 */
final class PluginMigration
{
    public const FLAG_KEY = '_migration';

    /**
     * @param  array<string, mixed>  $pluginParams
     */
    public static function sourceHash(array $pluginParams): string
    {
        unset($pluginParams[self::FLAG_KEY]);
        ksort($pluginParams);

        return hash('sha256', json_encode($pluginParams, JSON_UNESCAPED_SLASHES) ?: '');
    }

    /**
     * @param  array<string, mixed>  $componentSettings
     * @param  array<string, mixed>  $pluginParams
     */
    public static function shouldMigrate(array $componentSettings, array $pluginParams, bool $force = false): bool
    {
        if ($pluginParams === []) {
            return false;
        }

        if ($force) {
            return true;
        }

        $meta = $componentSettings[self::FLAG_KEY] ?? null;

        return !\is_array($meta) || empty($meta['source_hash']);
    }

    /**
     * @param  array<string, mixed>  $pluginParams
     * @param  array<string, mixed>  $previousSettings
     * @return array<string, mixed>
     */
    public static function apply(array $pluginParams, array $previousSettings = [], bool $force = false): array
    {
        $keys = self::migratedKeys();
        $out  = $previousSettings;

        foreach ($keys as $key) {
            if (!array_key_exists($key, $pluginParams)) {
                continue;
            }

            $current = $out[$key] ?? null;
            $empty   = $current === null || $current === '' || $current === [];

            if ($force || $empty) {
                $out[$key] = $pluginParams[$key];
            }
        }

        $out[self::FLAG_KEY] = [
            'source'      => 'plg_system_joomcookies',
            'source_hash' => self::sourceHash($pluginParams),
            'migrated_at' => gmdate('c'),
            'reversible'  => true,
        ];

        return $out;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    public static function reverse(array $snapshot): array
    {
        return $snapshot;
    }

    /**
     * @return list<string>
     */
    public static function migratedKeys(): array
    {
        return [
            'enabled',
            'consent_version',
            'cookie_lifetime_days',
            'show_reopen_button',
            'diagnostic',
            'cookie_policy_url',
            'privacy_policy_url',
            'position',
            'color_background',
            'color_text',
            'color_accent',
            'color_button_text',
            'color_muted',
            'text_title',
            'text_message',
            'text_accept',
            'text_reject',
            'text_customize',
            'text_save',
            'text_close',
            'text_reopen',
            'text_prefs_title',
            'text_cookie_link',
            'text_privacy_link',
            'cat_necessary_label',
            'cat_necessary_desc',
            'cat_statistics_label',
            'cat_statistics_desc',
            'cat_marketing_label',
            'cat_marketing_desc',
            'cat_embeds_label',
            'cat_embeds_desc',
            'youtube_nocookie',
            'youtube_thumbnails',
            'text_youtube_blocked',
            'text_youtube_load',
            'text_youtube_prefs',
            'text_youtube_help',
            'text_prefs_desc',
            'text_embed_blocked',
            'text_embed_load',
            'text_embed_help',
            'services',
        ];
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private static function onlyMigrationMeta(array $settings): bool
    {
        $keys = array_keys($settings);

        return $keys === [] || $keys === [self::FLAG_KEY];
    }
}

