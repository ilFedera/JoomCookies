<?php

/**
 * @package     JoomCookies
 * @subpackage  com_joomcookies
 */

namespace JoomCookies\Component\Joomcookies\Administrator\Model;

\defined('_JEXEC') or die;

use JoomCookies\Component\Joomcookies\Administrator\Service\Scan\UnavailableBrowserScanDriver;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Database\DatabaseInterface;

class DiagnosticModel extends BaseDatabaseModel
{
    /**
     * @return array<string, mixed>
     */
    public function getReport(): array
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $checks = [];
        $tables = [
            '#__joomcookies_settings',
            '#__joomcookies_categories',
            '#__joomcookies_services',
            '#__joomcookies_scans',
            '#__joomcookies_findings',
            '#__joomcookies_migrations',
            '#__joomcookies_logs',
        ];
        $list = $db->getTableList();
        $prefix = $db->getPrefix();

        foreach ($tables as $table) {
            $name = str_replace('#__', $prefix, $table);
            $checks[] = [
                'label' => $table,
                'ok'    => \in_array(strtolower($name), array_map('strtolower', $list), true),
            ];
        }

        $checks[] = [
            'label' => 'plg_system_joomcookies',
            'ok'    => (bool) PluginHelper::getPlugin('system', 'joomcookies'),
        ];
        $checks[] = [
            'label' => 'plugin enabled',
            'ok'    => PluginHelper::isEnabled('system', 'joomcookies'),
        ];

        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__joomcookies_logs'))
            ->order($db->quoteName('id') . ' DESC');
        $db->setQuery($query, 0, 50);
        $logs = $db->loadAssocList() ?: [];

        return [
            'checks' => $checks,
            'logs'   => $logs,
            'browser'=> (new UnavailableBrowserScanDriver())->describe(),
        ];
    }
}
