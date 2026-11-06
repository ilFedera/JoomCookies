<?php

/**
 * @package     JoomCookies
 * @subpackage  com_joomcookies
 */

namespace JoomCookies\Component\Joomcookies\Administrator\Model;

\defined('_JEXEC') or die;

use JoomCookies\Component\Joomcookies\Administrator\Service\Scan\UnavailableBrowserScanDriver;
use JoomCookies\Component\Joomcookies\Administrator\Service\SettingsStore;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Database\DatabaseInterface;

class DashboardModel extends BaseDatabaseModel
{
    /**
     * @return array<string, mixed>
     */
    public function getStateData(): array
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $plugin = PluginHelper::getPlugin('system', 'joomcookies');
        $lastScan = null;

        try {
            $query = $db->getQuery(true)
                ->select('*')
                ->from($db->quoteName('#__joomcookies_scans'))
                ->order($db->quoteName('id') . ' DESC');
            $db->setQuery($query, 0, 1);
            $lastScan = $db->loadAssoc() ?: null;
        } catch (\Throwable) {
            $lastScan = null;
        }

        $driver = new UnavailableBrowserScanDriver();

        return [
            'plugin_enabled'   => $plugin && PluginHelper::isEnabled('system', 'joomcookies'),
            'enabled'          => (int) SettingsStore::get('enabled', 1) === 1,
            'consent_version'  => (string) SettingsStore::get('consent_version', '1'),
            'last_scan'        => $lastScan,
            'browser_scan'     => $driver->describe(),
            'browser_available'=> $driver->isAvailable(),
        ];
    }
}
