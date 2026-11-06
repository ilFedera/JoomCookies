<?php

/**
 * @package     JoomCookies
 * @subpackage  com_joomcookies
 */

namespace JoomCookies\Component\Joomcookies\Administrator\Service\Access;

\defined('_JEXEC') or die;

/**
 * Pure ACL/CSRF helpers used by controllers and unit tests.
 */
final class AccessGate
{
    /**
     * @param  list<string>  $granted
     */
    public static function allows(array $granted, string $needed): bool
    {
        return \in_array($needed, $granted, true) || \in_array('core.admin', $granted, true);
    }

    public static function validToken(string $posted, string $expected): bool
    {
        return $expected !== '' && hash_equals($expected, $posted);
    }
}
