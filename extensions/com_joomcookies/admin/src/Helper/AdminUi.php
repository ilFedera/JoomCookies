<?php

/**
 * @package     JoomCookies
 * @subpackage  com_joomcookies
 */

namespace JoomCookies\Component\Joomcookies\Administrator\Helper;

\defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Toolbar\ToolbarHelper;

final class AdminUi
{
    public static function title(string $key): void
    {
        ToolbarHelper::title(Text::_($key), 'lock');
    }

    /**
     * @return list<array{view: string, label: string}>
     */
    public static function nav(): array
    {
        return [
            ['view' => 'dashboard', 'label' => 'COM_JOOMCOOKIES_NAV_DASHBOARD'],
            ['view' => 'scan', 'label' => 'COM_JOOMCOOKIES_NAV_SCAN'],
            ['view' => 'cookies', 'label' => 'COM_JOOMCOOKIES_NAV_COOKIES'],
            ['view' => 'services', 'label' => 'COM_JOOMCOOKIES_NAV_SERVICES'],
            ['view' => 'categories', 'label' => 'COM_JOOMCOOKIES_NAV_CATEGORIES'],
            ['view' => 'banner', 'label' => 'COM_JOOMCOOKIES_NAV_BANNER'],
            ['view' => 'texts', 'label' => 'COM_JOOMCOOKIES_NAV_TEXTS'],
            ['view' => 'youtube', 'label' => 'COM_JOOMCOOKIES_NAV_YOUTUBE'],
            ['view' => 'diagnostic', 'label' => 'COM_JOOMCOOKIES_NAV_DIAGNOSTIC'],
        ];
    }

    public static function token(): string
    {
        return HTMLHelper::_('form.token');
    }
}
