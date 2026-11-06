<?php

/**
 * @package     JoomCookies
 * @subpackage  plg_system_joomcookies
 */

namespace JoomCookies\Plugin\System\Joomcookies\Service;

\defined('_JEXEC') or die;

use Joomla\CMS\Filter\InputFilter;

/**
 * Renders the consent banner, preferences panel and reopen control.
 */
final class BannerRenderer
{
    /**
     * @param  array<string, mixed>  $config
     */
    public static function markup(array $config): string
    {
        $texts      = $config['texts'] ?? [];
        $categories = $config['categories'] ?? [];
        $links      = $config['links'] ?? [];
        $filter     = InputFilter::getInstance(
            ['p', 'br', 'a', 'strong', 'em', 'span', 'ul', 'ol', 'li', 'b', 'i'],
            ['href', 'title', 'rel', 'target', 'class'],
            1,
            1
        );

        $title   = self::plain($texts['title'] ?? '');
        $message = $filter->clean((string) ($texts['message'] ?? ''), 'html');
        $accept  = self::plain($texts['accept'] ?? 'Accetta tutti');
        $reject  = self::plain($texts['reject'] ?? 'Rifiuta tutti');
        $custom  = self::plain($texts['customize'] ?? 'Personalizza');
        $save    = self::plain($texts['save'] ?? 'Salva preferenze');
        $close   = self::plain($texts['close'] ?? 'Chiudi');
        $reopen  = self::plain($texts['reopen'] ?? 'Preferenze sulla privacy');
        $prefs   = self::plain($texts['prefsTitle'] ?? $title);
        $prefsDesc = $filter->clean((string) ($texts['prefsDesc'] ?? ''), 'html');

        $policyLinks = '';

        if (!empty($links['cookie'])) {
            $policyLinks .= '<a class="jck-link" href="' . self::attr((string) $links['cookie']) . '">'
                . self::plain($texts['cookieLink'] ?? 'Cookie policy') . '</a>';
        }

        if (!empty($links['privacy'])) {
            $policyLinks .= '<a class="jck-link" href="' . self::attr((string) $links['privacy']) . '">'
                . self::plain($texts['privacyLink'] ?? 'Privacy policy') . '</a>';
        }

        $categoryMarkup = '';

        foreach ($categories as $category) {
            $id       = preg_replace('/[^a-z]/', '', (string) ($category['id'] ?? '')) ?? '';
            $required = !empty($category['required']);
            $label    = self::plain((string) ($category['label'] ?? $id));
            $desc     = $filter->clean((string) ($category['description'] ?? ''), 'html');
            $checked  = $required ? ' checked disabled' : '';
            $inputId  = 'jck-cat-' . $id;

            $categoryMarkup .= '<div class="jck-category">'
                . '<div class="jck-category__row">'
                . '<label class="jck-switch" for="' . $inputId . '">'
                . '<input type="checkbox" id="' . $inputId . '" data-jck-category-toggle="' . $id . '"' . $checked . '>'
                . '<span class="jck-switch__ui" aria-hidden="true"></span>'
                . '<span class="jck-switch__label">' . $label . '</span>'
                . '</label>'
                . ($required ? '<span class="jck-badge">' . self::plain($texts['necessaryAlways'] ?? '') . '</span>' : '')
                . '</div>'
                . '<div class="jck-category__desc">' . $desc . '</div>'
                . '</div>';
        }

        $reopenButton = '';

        if (!empty($config['ui']['reopen'])) {
            $reopenButton = '<button type="button" class="jck-reopen" data-jck-open-prefs="1" aria-haspopup="dialog">'
                . $reopen . '</button>';
        }

        $position = self::attr((string) ($config['position'] ?? 'bottom'));

        return '<div id="joomcookies-root" class="jck-root" data-jck-root="1" hidden>'
            . $reopenButton
            . '<div class="jck-overlay" data-jck-overlay="1" hidden></div>'
            . '<section class="jck-banner jck-banner--' . $position . '" data-jck-banner="1" hidden'
            . ' role="dialog" aria-modal="true" aria-labelledby="jck-banner-title" tabindex="-1">'
            . '<div class="jck-banner__inner">'
            . '<h2 id="jck-banner-title" class="jck-title">' . $title . '</h2>'
            . '<div class="jck-message">' . $message . '</div>'
            . '<div class="jck-links">' . $policyLinks . '</div>'
            . '<div class="jck-actions">'
            . '<button type="button" class="jck-btn jck-btn--reject" data-jck-action="reject">' . $reject . '</button>'
            . '<button type="button" class="jck-btn jck-btn--ghost" data-jck-action="customize">' . $custom . '</button>'
            . '<button type="button" class="jck-btn jck-btn--accept" data-jck-action="accept">' . $accept . '</button>'
            . '</div></div></section>'
            . '<section class="jck-panel" data-jck-panel="1" hidden role="dialog" aria-modal="true"'
            . ' aria-labelledby="jck-panel-title" tabindex="-1">'
            . '<div class="jck-panel__inner">'
            . '<div class="jck-panel__head"><h2 id="jck-panel-title" class="jck-title">' . $prefs . '</h2>'
            . '<button type="button" class="jck-icon-btn" data-jck-action="close" aria-label="' . $close . '">×</button></div>'
            . ($prefsDesc !== '' ? '<div class="jck-message">' . $prefsDesc . '</div>' : '')
            . '<div class="jck-panel__body">' . $categoryMarkup . '</div>'
            . '<div class="jck-links">' . $policyLinks . '</div>'
            . '<div class="jck-actions">'
            . '<button type="button" class="jck-btn jck-btn--reject" data-jck-action="reject">' . $reject . '</button>'
            . '<button type="button" class="jck-btn jck-btn--ghost" data-jck-action="save">' . $save . '</button>'
            . '<button type="button" class="jck-btn jck-btn--accept" data-jck-action="accept">' . $accept . '</button>'
            . '</div></div></section>'
            . '</div>';
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public static function cssVariables(array $config): string
    {
        $theme = $config['theme'] ?? [];

        $map = [
            '--jck-bg'      => $theme['background'] ?? '#111827',
            '--jck-text'    => $theme['text'] ?? '#F9FAFB',
            '--jck-accent'  => $theme['accent'] ?? '#2563EB',
            '--jck-btn-text'=> $theme['buttonText'] ?? '#FFFFFF',
            '--jck-muted'   => $theme['muted'] ?? '#D1D5DB',
        ];

        $chunks = [];

        foreach ($map as $name => $value) {
            $chunks[] = $name . ':' . htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
        }

        return '#joomcookies-root.jck-root{' . implode(';', $chunks) . ';}';
    }

    private static function plain(string $value): string
    {
        return htmlspecialchars(strip_tags($value), ENT_QUOTES, 'UTF-8');
    }

    private static function attr(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
