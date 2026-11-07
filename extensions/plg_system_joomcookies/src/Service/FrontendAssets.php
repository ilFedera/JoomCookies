<?php

/**
 * @package     JoomCookies
 * @subpackage  plg_system_joomcookies
 */

namespace JoomCookies\Plugin\System\Joomcookies\Service;

\defined('_JEXEC') or die;

/**
 * Versioned frontend assets that LiteSpeed must not minify, defer, delay or combine.
 */
final class FrontendAssets
{
    public const VERSION = '2.2.15';

    public static function version(): string
    {
        return self::VERSION;
    }

    public static function protectionAttributes(): string
    {
        return ' data-no-optimize="1" data-no-defer="1" data-no-delay="1" data-no-combine="1" data-no-minify="1" data-cfasync="false"';
    }

    public static function withVersion(string $url): string
    {
        $base = preg_replace('/[?#].*$/', '', $url) ?? $url;

        return $base . '?v=' . rawurlencode(self::VERSION);
    }
}
