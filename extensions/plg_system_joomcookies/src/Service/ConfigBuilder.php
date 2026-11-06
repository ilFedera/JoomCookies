<?php

/**
 * @package     JoomCookies
 * @subpackage  plg_system_joomcookies
 */

namespace JoomCookies\Plugin\System\Joomcookies\Service;

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;
use Joomla\Registry\Registry;

/**
 * Builds a frontend-safe configuration payload from plugin parameters.
 */
final class ConfigBuilder
{
    /**
     * @return array<string, mixed>
     */
    public static function fromParams(Registry $params, string $cookiePath): array
    {
        return self::fromArray($params->toArray(), $cookiePath);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<array<string, mixed>>  $extraServices
     * @param  list<array<string, mixed>>  $categoryRows
     * @return array<string, mixed>
     */
    public static function fromArray(array $data, string $cookiePath, array $extraServices = [], array $categoryRows = []): array
    {
        $params = new Registry($data);
        $version = self::version($params->get('consent_version', '1'));
        $services = array_merge(self::services($params->get('services', [])), $extraServices);
        $registry = new ServiceRegistry($services);

        return [
            'enabled'            => (int) $params->get('enabled', 1) === 1,
            'version'            => $version,
            'position'           => self::position((string) $params->get('position', 'bottom')),
            'theme'              => [
                'background' => self::color((string) $params->get('color_background', '#111827'), '#111827'),
                'text'       => self::color((string) $params->get('color_text', '#F9FAFB'), '#F9FAFB'),
                'accent'     => self::color((string) $params->get('color_accent', '#2563EB'), '#2563EB'),
                'buttonText' => self::color((string) $params->get('color_button_text', '#FFFFFF'), '#FFFFFF'),
                'muted'      => self::color((string) $params->get('color_muted', '#D1D5DB'), '#D1D5DB'),
            ],
            'texts'              => self::texts($params),
            'links'              => [
                'cookie'  => self::url((string) $params->get('cookie_policy_url', '')),
                'privacy' => self::url((string) $params->get('privacy_policy_url', '')),
            ],
            'categories'         => self::categories($params, $categoryRows),
            'services'           => $registry->forFrontend(),
            'cookie'             => [
                'name'     => 'joomcookies_consent',
                'days'     => max(1, (int) $params->get('cookie_lifetime_days', 180)),
                'path'     => $cookiePath !== '' ? $cookiePath : '/',
                'sameSite' => 'Lax',
            ],
            'youtube'            => [
                'nocookie'   => (int) $params->get('youtube_nocookie', 1) === 1,
                'thumbnails' => self::thumbnails((string) $params->get('youtube_thumbnails', 'local')),
            ],
            'ui'                 => [
                'reopen'     => (int) $params->get('show_reopen_button', 1) === 1,
                'diagnostic' => (int) $params->get('diagnostic', 0) === 1,
            ],
            'reloadOnRevoke'     => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function guardOptions(array $config): array
    {
        $texts = $config['texts'] ?? [];

        return [
            'youtube_nocookie'      => !empty($config['youtube']['nocookie']),
            'youtube_thumbnails'    => $config['youtube']['thumbnails'] ?? 'local',
            'open_prefs_label'      => $texts['reopen'] ?? Text::_('PLG_SYSTEM_JOOMCOOKIES_UI_REOPEN'),
            'embed_blocked_label'   => $texts['embedBlocked'] ?? Text::_('PLG_SYSTEM_JOOMCOOKIES_UI_EMBED_BLOCKED'),
            'embed_load_label'      => $texts['embedLoad'] ?? Text::_('PLG_SYSTEM_JOOMCOOKIES_UI_EMBED_LOAD'),
            'youtube_blocked_label' => $texts['youtubeBlocked'] ?? Text::_('PLG_SYSTEM_JOOMCOOKIES_UI_YOUTUBE_BLOCKED'),
            'youtube_load_label'    => $texts['youtubeLoad'] ?? Text::_('PLG_SYSTEM_JOOMCOOKIES_UI_YOUTUBE_LOAD'),
            'youtube_prefs_label'   => $texts['youtubePrefs'] ?? Text::_('PLG_SYSTEM_JOOMCOOKIES_UI_YOUTUBE_PREFS'),
            'youtube_help'          => $texts['youtubeHelp'] ?? '',
        ];
    }

    public static function version(mixed $value): string
    {
        $version = preg_replace('/[^A-Za-z0-9._-]/', '', (string) $value);

        return $version !== '' ? $version : '1';
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function services(mixed $raw): array
    {
        if (\is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            $raw     = \is_array($decoded) ? $decoded : [];
        }

        if (!\is_array($raw)) {
            return [];
        }

        if (isset($raw['id']) || isset($raw['match_value'])) {
            return [$raw];
        }

        return array_values(array_filter($raw, 'is_array'));
    }

    /**
     * @return array<string, mixed>
     */
    private static function texts(Registry $params): array
    {
        return [
            'title'          => self::text($params, 'text_title', 'PLG_SYSTEM_JOOMCOOKIES_UI_TITLE'),
            'message'        => self::htmlText($params, 'text_message', 'PLG_SYSTEM_JOOMCOOKIES_UI_MESSAGE'),
            'accept'         => self::text($params, 'text_accept', 'PLG_SYSTEM_JOOMCOOKIES_UI_ACCEPT'),
            'reject'         => self::text($params, 'text_reject', 'PLG_SYSTEM_JOOMCOOKIES_UI_REJECT'),
            'customize'      => self::text($params, 'text_customize', 'PLG_SYSTEM_JOOMCOOKIES_UI_CUSTOMIZE'),
            'save'           => self::text($params, 'text_save', 'PLG_SYSTEM_JOOMCOOKIES_UI_SAVE'),
            'close'          => self::text($params, 'text_close', 'PLG_SYSTEM_JOOMCOOKIES_UI_CLOSE'),
            'reopen'         => self::text($params, 'text_reopen', 'PLG_SYSTEM_JOOMCOOKIES_UI_REOPEN'),
            'prefsTitle'     => self::text($params, 'text_prefs_title', 'PLG_SYSTEM_JOOMCOOKIES_UI_PREFS_TITLE'),
            'prefsDesc'      => self::htmlText($params, 'text_prefs_desc', 'PLG_SYSTEM_JOOMCOOKIES_UI_PREFS_DESC'),
            'youtubePrefs'   => self::text($params, 'text_youtube_prefs', 'PLG_SYSTEM_JOOMCOOKIES_UI_YOUTUBE_PREFS'),
            'cookieLink'     => self::text($params, 'text_cookie_link', 'PLG_SYSTEM_JOOMCOOKIES_UI_COOKIE_LINK'),
            'privacyLink'    => self::text($params, 'text_privacy_link', 'PLG_SYSTEM_JOOMCOOKIES_UI_PRIVACY_LINK'),
            'embedBlocked'   => self::text($params, 'text_embed_blocked', 'PLG_SYSTEM_JOOMCOOKIES_UI_EMBED_BLOCKED'),
            'embedLoad'      => self::text($params, 'text_embed_load', 'PLG_SYSTEM_JOOMCOOKIES_UI_EMBED_LOAD'),
            'youtubeBlocked' => self::text($params, 'text_youtube_blocked', 'PLG_SYSTEM_JOOMCOOKIES_UI_YOUTUBE_BLOCKED'),
            'youtubeLoad'    => self::text($params, 'text_youtube_load', 'PLG_SYSTEM_JOOMCOOKIES_UI_YOUTUBE_LOAD'),
            'youtubeHelp'    => self::text($params, 'text_youtube_help', 'PLG_SYSTEM_JOOMCOOKIES_UI_YOUTUBE_HELP'),
            'necessaryAlways'=> self::text($params, 'text_necessary_always', 'PLG_SYSTEM_JOOMCOOKIES_UI_NECESSARY_ALWAYS'),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $overrides
     * @return array<string, array<string, mixed>>
     */
    private static function categories(Registry $params, array $overrides = []): array
    {
        $map = [
            ConsentState::CATEGORY_NECESSARY  => ['label' => 'PLG_SYSTEM_JOOMCOOKIES_CAT_NECESSARY', 'desc' => 'PLG_SYSTEM_JOOMCOOKIES_CAT_NECESSARY_DESC', 'labelParam' => 'cat_necessary_label', 'descParam' => 'cat_necessary_desc'],
            ConsentState::CATEGORY_STATISTICS => ['label' => 'PLG_SYSTEM_JOOMCOOKIES_CAT_STATISTICS', 'desc' => 'PLG_SYSTEM_JOOMCOOKIES_CAT_STATISTICS_DESC', 'labelParam' => 'cat_statistics_label', 'descParam' => 'cat_statistics_desc'],
            ConsentState::CATEGORY_MARKETING  => ['label' => 'PLG_SYSTEM_JOOMCOOKIES_CAT_MARKETING', 'desc' => 'PLG_SYSTEM_JOOMCOOKIES_CAT_MARKETING_DESC', 'labelParam' => 'cat_marketing_label', 'descParam' => 'cat_marketing_desc'],
            ConsentState::CATEGORY_EMBEDS     => ['label' => 'PLG_SYSTEM_JOOMCOOKIES_CAT_EMBEDS', 'desc' => 'PLG_SYSTEM_JOOMCOOKIES_CAT_EMBEDS_DESC', 'labelParam' => 'cat_embeds_label', 'descParam' => 'cat_embeds_desc'],
        ];

        $categories = [];

        foreach ($map as $id => $meta) {
            $categories[$id] = [
                'id'       => $id,
                'label'    => self::text($params, $meta['labelParam'], $meta['label']),
                'description' => self::htmlText($params, $meta['descParam'], $meta['desc']),
                'required' => $id === ConsentState::CATEGORY_NECESSARY,
            ];
        }

        foreach ($overrides as $row) {
            $id = preg_replace('/[^a-z]/', '', (string) ($row['alias'] ?? '')) ?? '';

            if ($id === '' || !isset($categories[$id])) {
                continue;
            }

            $title = trim(strip_tags((string) ($row['title'] ?? '')));
            $desc  = trim((string) ($row['description'] ?? ''));

            if ($title !== '') {
                $categories[$id]['label'] = $title;
            }

            if ($desc !== '') {
                $categories[$id]['description'] = $desc;
            }
        }

        return $categories;
    }

    private static function text(Registry $params, string $param, string $langKey): string
    {
        $value = trim(strip_tags((string) $params->get($param, '')));

        return $value !== '' ? $value : Text::_($langKey);
    }

    private static function htmlText(Registry $params, string $param, string $langKey): string
    {
        $value = trim((string) $params->get($param, ''));

        if ($value === '') {
            return Text::_($langKey);
        }

        return $value;
    }

    private static function color(string $value, string $fallback): string
    {
        return preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/', $value) === 1
            ? $value
            : $fallback;
    }

    private static function position(string $value): string
    {
        $allowed = ['bottom', 'top', 'box-left', 'box-right', 'modal'];

        return \in_array($value, $allowed, true) ? $value : 'bottom';
    }

    private static function thumbnails(string $value): string
    {
        return \in_array($value, ['local', 'youtube'], true) ? $value : 'local';
    }

    private static function url(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        if (str_starts_with($value, '/') || str_starts_with($value, '#')) {
            return $value;
        }

        if (filter_var($value, FILTER_VALIDATE_URL) && preg_match('#^https?://#i', $value) === 1) {
            return $value;
        }

        if (preg_match('/^[a-z0-9\/_.-]+$/i', $value) === 1) {
            return rtrim(Uri::root(), '/') . '/' . ltrim($value, '/');
        }

        return '';
    }
}
