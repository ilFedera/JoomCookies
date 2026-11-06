<?php

/**
 * @package     JoomCookies
 * @subpackage  com_joomcookies
 */

namespace JoomCookies\Component\Joomcookies\Administrator\Service\Scan;

\defined('_JEXEC') or die;

/**
 * Detects admin AJAX scan requests without using format=json (no JsonView).
 */
final class ScanAjax
{
    public static function wantsJson(?array $server = null): bool
    {
        $server ??= $_SERVER;
        $requested = strtolower((string) ($server['HTTP_X_REQUESTED_WITH'] ?? $server['REDIRECT_HTTP_X_REQUESTED_WITH'] ?? ''));

        if ($requested === 'xmlhttprequest') {
            return true;
        }

        $accept = strtolower((string) ($server['HTTP_ACCEPT'] ?? ''));

        return str_contains($accept, 'application/json');
    }
}
