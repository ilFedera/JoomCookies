<?php

/**
 * @package     JoomCookies
 * @subpackage  plg_system_joomcookies
 */

namespace JoomCookies\Plugin\System\Joomcookies\Service;

\defined('_JEXEC') or die;

/**
 * Rewrites HTML so managed non-essential scripts and iframes cannot execute
 * until the matching category is allowed.
 */
final class HtmlGuard
{
    private ServiceRegistry $registry;

    /**
     * @var array<string, mixed>
     */
    private array $options;

    /**
     * @var array<string, bool>
     */
    private array $consent;

    /**
     * @var list<array<string, string>>
     */
    private array $blocked = [];

    private bool $recaptchaNotice = false;

    /**
     * @param  array<string, mixed>  $options
     * @param  array<string, bool>   $consent
     */
    public function __construct(ServiceRegistry $registry, array $options, array $consent)
    {
        $this->registry = $registry;
        $this->options  = $options;
        $this->consent  = ConsentState::normalizeCategories($consent);
    }

    /**
     * @return list<array<string, string>>
     */
    public function blocked(): array
    {
        return $this->blocked;
    }

    public function process(string $html): string
    {
        $this->recaptchaNotice = false;
        $this->blocked = [];
        $html = $this->replaceShortcodes($html);
        $html = $this->replacePreferenceShortcodes($html);
        $html = preg_replace_callback('#<script(\s[^>]*)?>(.*?)</script>#is', [$this, 'replaceScript'], $html) ?? $html;
        $html = preg_replace_callback('#<iframe(\s[^>]*)?>.*?</iframe>#is', [$this, 'replaceIframe'], $html) ?? $html;
        $html = preg_replace_callback('#<iframe(\s[^>]*)?/>#is', [$this, 'replaceIframe'], $html) ?? $html;
        $html = $this->enhanceYoutubePlayers($html);

        return $html;
    }

    /**
     * @param  array<int, string>  $match
     */
    private function replaceScript(array $match): string
    {
        $full       = $match[0];
        $attributes = $match[1] ?? '';
        $content    = $match[2] ?? '';

        if ($this->isOwnAsset($attributes, $content) || $this->isAlreadyManaged($attributes)) {
            return $full;
        }

        $src = $this->attribute($attributes, 'src');
        $id  = $this->attribute($attributes, 'id');
        $hit = $this->registry->matchScript($src, $content, $id);

        if ($hit === null) {
            return $full;
        }

        // Always rewrite in HTML so LiteSpeed can cache one guest document.
        // Consent is applied in the browser from cookie/localStorage.
        $this->blocked[] = [
            'type'     => 'script',
            'service'  => $hit['id'],
            'category' => $hit['category'],
            'src'      => $src,
        ];

        $originalType = $this->attribute($attributes, 'type');
        $attrs        = $this->stripAttribute($attributes, 'type');
        $attrs        = $this->stripAttribute($attrs, 'src');

        $safeSrc = htmlspecialchars($src, ENT_QUOTES, 'UTF-8');
        $extra   = ' type="text/plain" data-jck-managed="1" data-jck-category="'
            . htmlspecialchars($hit['category'], ENT_QUOTES, 'UTF-8')
            . '" data-jck-service="' . htmlspecialchars($hit['id'], ENT_QUOTES, 'UTF-8') . '"';

        if ($src !== '') {
            $extra .= ' data-jck-src="' . $safeSrc . '"';
        }

        if ($originalType !== '' && strcasecmp($originalType, 'text/plain') !== 0) {
            $extra .= ' data-jck-type="' . htmlspecialchars($originalType, ENT_QUOTES, 'UTF-8') . '"';
        }

        $out = '<script' . $attrs . $extra . '>' . $content . '</script>';

        if ($this->isRecaptchaService($hit['id']) && !$this->recaptchaNotice) {
            $this->recaptchaNotice = true;
            $out .= $this->recaptchaConsentNotice();
        }

        return $out;
    }

    /**
     * @param  array<int, string>  $match
     */
    private function replaceIframe(array $match): string
    {
        $full       = $match[0];
        $attributes = $match[1] ?? '';

        if ($this->isAlreadyManaged($attributes)) {
            return $full;
        }

        $src = $this->attribute($attributes, 'src');
        $hit = $src !== '' ? $this->registry->matchFrame($src) : null;

        if ($hit === null) {
            return $full;
        }

        $this->blocked[] = [
            'type'     => 'iframe',
            'service'  => $hit['id'],
            'category' => $hit['category'],
            'src'      => $src,
        ];

        $title   = $this->attribute($attributes, 'title');
        $videoId = YoutubeEmbed::videoId($src);

        if ($videoId !== null) {
            return $this->youtubePlaceholder($videoId, $title, '16 / 9', $attributes);
        }

        return $this->genericPlaceholder($hit, $src, $title, $attributes);
    }

    public function replaceShortcodes(string $html): string
    {
        return preg_replace_callback(
            YoutubeEmbed::SHORTCODE_PATTERN,
            function (array $match): string {
                $parsed = YoutubeEmbed::parseShortcode(
                    $match[0],
                    $match[1] ?? '',
                    $match[2] ?? '',
                    $match[3] ?? '',
                    $match[4] ?? ''
                );

                if ($parsed === null) {
                    return $match[0];
                }

                $this->blocked[] = [
                    'type'     => 'shortcode',
                    'service'  => 'youtube',
                    'category' => ConsentState::CATEGORY_EMBEDS,
                    'src'      => $parsed['id'],
                ];

                return $this->youtubePlaceholder($parsed['id'], $parsed['title'], $parsed['ratio']);
            },
            $html
        ) ?? $html;
    }

    public function replacePreferenceShortcodes(string $html): string
    {
        $button = '<button type="button" class="jck-open-prefs" data-jck-open-prefs="1">'
            . htmlspecialchars((string) ($this->options['open_prefs_label'] ?? 'Cookie preferences'), ENT_QUOTES, 'UTF-8')
            . '</button>';

        return str_ireplace('{joomcookies-preferences}', $button, $html);
    }

    /**
     * @param  array<string, mixed>  $hit
     */
    private function genericPlaceholder(array $hit, string $src, string $title, string $originalAttrs = ''): string
    {
        $payload = htmlspecialchars(json_encode([
            'src'        => $src,
            'title'      => $title,
            'attrs'      => $this->safeIframeAttrs($originalAttrs),
            'category'   => $hit['category'],
            'service'    => $hit['id'],
        ], JSON_UNESCAPED_SLASHES) ?: '{}', ENT_QUOTES, 'UTF-8');

        $label = htmlspecialchars((string) ($this->options['embed_blocked_label'] ?? 'External content blocked'), ENT_QUOTES, 'UTF-8');
        $btn   = htmlspecialchars((string) ($this->options['embed_load_label'] ?? 'Load content'), ENT_QUOTES, 'UTF-8');
        $name  = htmlspecialchars((string) $hit['id'], ENT_QUOTES, 'UTF-8');

        return '<div class="jck-embed jck-embed--generic" data-jck-embed="1" data-jck-category="'
            . htmlspecialchars((string) $hit['category'], ENT_QUOTES, 'UTF-8')
            . '" data-jck-service="' . $name . '" data-jck-iframe="' . $payload . '"'
            . ' role="group" aria-label="' . $label . '">'
            . '<div class="jck-embed__panel"><p class="jck-embed__title">' . $label . '</p>'
            . '<p class="jck-embed__text">' . $name . '</p>'
            . '<button type="button" class="jck-embed__button" data-jck-load-embed="1">' . $btn . '</button>'
            . '</div></div>';
    }

    private function isRecaptchaService(string $id): bool
    {
        return str_starts_with($id, 'recaptcha');
    }

    private function recaptchaConsentNotice(): string
    {
        $label = htmlspecialchars((string) ($this->options['embed_blocked_label'] ?? 'External content blocked'), ENT_QUOTES, 'UTF-8');
        $help  = htmlspecialchars((string) ($this->options['embed_help'] ?? $this->options['youtube_help'] ?? ''), ENT_QUOTES, 'UTF-8');
        $prefs = htmlspecialchars((string) ($this->options['open_prefs_label'] ?? 'Cookie preferences'), ENT_QUOTES, 'UTF-8');

        if ($help === '') {
            $help = 'Authorize External content in the privacy preferences to use forms protected by reCAPTCHA.';
        }

        return '<div class="jck-embed jck-embed--notice" data-jck-consent-hint="embeds" role="status">'
            . '<p class="jck-embed__title">' . $label . '</p>'
            . '<p class="jck-embed__text">' . $help . '</p>'
            . '<button type="button" class="jck-embed__button" data-jck-open-prefs="1">' . $prefs . '</button>'
            . '</div>';
    }

    public function youtubePlaceholder(string $videoId, string $title = '', string $ratio = '16 / 9', string $originalAttrs = ''): string
    {
        $src     = YoutubeEmbed::playerSrc($videoId, !empty($this->options['youtube_nocookie']));
        $payload = htmlspecialchars(json_encode([
            'src'      => $src,
            'title'    => $title,
            'attrs'    => $this->safeIframeAttrs($originalAttrs),
            'category' => ConsentState::CATEGORY_EMBEDS,
            'service'  => 'youtube',
            'videoId'  => $videoId,
        ], JSON_UNESCAPED_SLASHES) ?: '{}', ENT_QUOTES, 'UTF-8');

        $label   = $title !== '' ? $title : (string) ($this->options['youtube_blocked_label'] ?? 'Video YouTube bloccato');
        $prefs   = htmlspecialchars((string) ($this->options['youtube_prefs_label'] ?? 'Gestisci preferenze'), ENT_QUOTES, 'UTF-8');
        $help    = htmlspecialchars((string) ($this->options['youtube_help'] ?? 'Attiva i contenuti esterni per visualizzarlo.'), ENT_QUOTES, 'UTF-8');
        $safeId  = htmlspecialchars($videoId, ENT_QUOTES, 'UTF-8');
        $safeLab = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
        $icon    = '<span class="jck-embed__icon" aria-hidden="true"><svg viewBox="0 0 24 24" width="28" height="28" focusable="false"><path fill="currentColor" d="M8 5v14l11-7z"/></svg></span>';

        return '<div class="jck-embed jck-embed--youtube" data-jck-embed="1" data-jck-youtube="' . $safeId
            . '" data-jck-category="embeds" data-jck-service="youtube" data-jck-iframe="' . $payload
            . '" style="--jck-ratio:' . htmlspecialchars($ratio, ENT_QUOTES, 'UTF-8') . '"'
            . ' role="group" aria-label="' . $safeLab . '">'
            . '<div class="jck-embed__surface">'
            . '<div class="jck-embed__panel">' . $icon
            . '<p class="jck-embed__title">' . $safeLab . '</p>'
            . ($help !== '' ? '<p class="jck-embed__text">' . $help . '</p>' : '')
            . '<div class="jck-embed__actions">'
            . '<button type="button" class="jck-embed__button" data-jck-open-prefs="1">' . $prefs . '</button>'
            . '</div></div></div></div>';
    }

    public function enhanceYoutubePlayers(string $html): string
    {
        return preg_replace_callback(
            '#<div\b([^>]*\byoutube-player\b[^>]*)>(\s*)</div>#is',
            function (array $match): string {
                $attributes = $match[1] ?? '';
                $inner      = $match[2] ?? '';

                if ($this->attribute($attributes, 'data-jck-yt') !== '' || str_contains($inner, 'data-jck-youtube')) {
                    return $match[0];
                }

                $videoId = YoutubeEmbed::videoId($this->attribute($attributes, 'data-id'));

                if ($videoId === null) {
                    return $match[0];
                }

                $this->blocked[] = [
                    'type'     => 'youtube-player',
                    'service'  => 'youtube',
                    'category' => ConsentState::CATEGORY_EMBEDS,
                    'src'      => $videoId,
                ];

                $attrs = trim($attributes);
                if (!str_contains($attrs, 'data-jck-yt=')) {
                    $attrs .= ' data-jck-yt="1"';
                }

                return '<div ' . $attrs . '>' . $this->youtubePlaceholder($videoId, '', '16 / 9') . '</div>';
            },
            $html
        ) ?? $html;
    }

    public function youtubePlayer(string $videoId, string $title = '', string $ratio = '16 / 9'): string
    {
        $src      = YoutubeEmbed::playerSrc($videoId, !empty($this->options['youtube_nocookie']));
        $safeSrc  = htmlspecialchars($src, ENT_QUOTES, 'UTF-8');
        $safeTitle = htmlspecialchars($title !== '' ? $title : 'YouTube', ENT_QUOTES, 'UTF-8');

        return '<div class="jck-embed jck-embed--youtube jck-embed--active" style="--jck-ratio:'
            . htmlspecialchars($ratio, ENT_QUOTES, 'UTF-8') . '">'
            . '<iframe src="' . $safeSrc . '" title="' . $safeTitle
            . '" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"'
            . ' allowfullscreen loading="lazy" referrerpolicy="strict-origin-when-cross-origin" data-jck-managed="1"></iframe>'
            . '</div>';
    }

    private function rewriteAllowedYoutubeIframe(string $attributes, string $src): string
    {
        $videoId = YoutubeEmbed::videoId($src);

        if ($videoId === null || empty($this->options['youtube_nocookie'])) {
            return '<iframe' . $attributes . '></iframe>';
        }

        $newSrc = YoutubeEmbed::playerSrc($videoId, true);
        $attrs  = preg_replace(
            '/\ssrc=("([^"]*)"|\'([^\']*)\')/i',
            ' src="' . htmlspecialchars($newSrc, ENT_QUOTES, 'UTF-8') . '"',
            $attributes
        );

        return '<iframe' . $attrs . '></iframe>';
    }

    /**
     * @return array<string, string>
     */
    private function safeIframeAttrs(string $attributes): array
    {
        $allowed = ['width', 'height', 'class', 'id', 'allow', 'allowfullscreen', 'loading', 'referrerpolicy', 'name'];
        $out     = [];

        foreach ($allowed as $name) {
            $value = $this->attribute($attributes, $name);

            if ($value !== '') {
                $out[$name] = $value;
            }
        }

        return $out;
    }

    private function isOwnAsset(string $attributes, string $content): bool
    {
        $src = strtolower($this->attribute($attributes, 'src'));
        $id  = strtolower($this->attribute($attributes, 'id'));

        if (\in_array($id, ['joomcookies-config', 'joomcookies-runtime', 'joomcookies-css', GoogleConsentMode::SCRIPT_ID], true)) {
            return true;
        }

        // media/templates is not skipped: YOOtheme analytics may live there.
        if ($src !== '' && (str_contains($src, 'plg_system_joomcookies') || str_contains($src, 'joomcookies.js') || str_contains($src, 'joomcookies.css') || str_contains($src, 'media/system') || str_contains($src, 'media/vendor'))) {
            return true;
        }

        $type = strtolower($this->attribute($attributes, 'type'));

        return \in_array($type, ['application/json', 'application/ld+json', 'importmap'], true);
    }

    private function isAlreadyManaged(string $attributes): bool
    {
        return $this->attribute($attributes, 'data-jck-managed') !== ''
            || $this->attribute($attributes, 'data-jck-embed') !== ''
            || $this->attribute($attributes, 'data-jck-ignore') !== '';
    }

    private function attribute(string $attributes, string $name): string
    {
        if (preg_match('/\s' . preg_quote($name, '/') . '\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+))/i', $attributes, $match) !== 1) {
            return '';
        }

        return html_entity_decode($match[1] !== '' ? $match[1] : ($match[2] !== '' ? $match[2] : $match[3]), ENT_QUOTES, 'UTF-8');
    }

    private function stripAttribute(string $attributes, string $name): string
    {
        return preg_replace('/\s' . preg_quote($name, '/') . '\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $attributes) ?? $attributes;
    }
}
