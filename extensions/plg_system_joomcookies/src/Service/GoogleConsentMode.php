<?php

/**
 * @package     JoomCookies
 * @subpackage  plg_system_joomcookies
 */

namespace JoomCookies\Plugin\System\Joomcookies\Service;

\defined('_JEXEC') or die;

/**
 * Cache-safe Google Consent Mode v2 default (always denied in HTML).
 *
 * This is a safety net, not the primary block: YOOtheme/gtag scripts stay
 * type=text/plain until statistics (and ads until marketing) consent.
 */
final class GoogleConsentMode
{
    public const SCRIPT_ID = 'joomcookies-gcm';

    public static function defaultDeniedSnippet(): string
    {
        $js = 'window.dataLayer=window.dataLayer||[];'
            . 'window.gtag=window.gtag||function(){dataLayer.push(arguments);};'
            . "gtag('consent','default',{"
            . "analytics_storage:'denied',"
            . "ad_storage:'denied',"
            . "ad_user_data:'denied',"
            . "ad_personalization:'denied',"
            . "functionality_storage:'granted',"
            . "security_storage:'granted',"
            . "wait_for_update:500"
            . '});';

        return '<script id="' . self::SCRIPT_ID . '" data-jck-ignore="1" type="text/javascript"'
            . FrontendAssets::protectionAttributes() . '>' . $js . '</script>';
    }

    public static function injectAfterHeadOpen(string $html): string
    {
        if (str_contains($html, 'id="' . self::SCRIPT_ID . '"')) {
            return $html;
        }

        $snippet = self::defaultDeniedSnippet();

        if (preg_match('/<head\b[^>]*>/i', $html, $match, PREG_OFFSET_CAPTURE) === 1) {
            $pos = (int) $match[0][1] + strlen($match[0][0]);

            return substr($html, 0, $pos) . $snippet . substr($html, $pos);
        }

        if (preg_match('/<html\b[^>]*>/i', $html, $match, PREG_OFFSET_CAPTURE) === 1) {
            $pos = (int) $match[0][1] + strlen($match[0][0]);

            return substr($html, 0, $pos) . $snippet . substr($html, $pos);
        }

        return $snippet . $html;
    }
}
