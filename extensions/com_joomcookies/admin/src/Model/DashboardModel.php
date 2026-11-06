<?php

/**
 * @package     JoomCookies
 * @subpackage  com_joomcookies
 */

namespace JoomCookies\Component\Joomcookies\Administrator\Model;

\defined('_JEXEC') or die;

use JoomCookies\Component\Joomcookies\Administrator\Service\Catalog\CatalogSync;
use JoomCookies\Component\Joomcookies\Administrator\Service\Scan\UnavailableBrowserScanDriver;
use JoomCookies\Component\Joomcookies\Administrator\Service\SettingsStore;
use JoomCookies\Plugin\System\Joomcookies\Service\ServiceRegistry;
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

        try {
            (new CatalogSync($db))->seedKnownServices();
        } catch (\Throwable) {
        }

        $catalog = [
            'identified' => 0,
            'classified' => 0,
            'review'     => 0,
            'rules_on'   => 0,
            'unverified' => 0,
            'anomalies'  => 0,
            'coverage'   => '',
        ];

        try {
            $query = $db->getQuery(true)
                ->select([
                    'COUNT(*) AS identified',
                    'SUM(CASE WHEN ' . $db->quoteName('approved') . ' = 1 THEN 1 ELSE 0 END) AS classified',
                    'SUM(CASE WHEN ' . $db->quoteName('classification_status') . ' = ' . $db->quote('review')
                        . ' OR (' . $db->quoteName('approved') . ' = 0 AND ' . $db->quoteName('category') . ' = ' . $db->quote('unclassified') . ') THEN 1 ELSE 0 END) AS review',
                    'SUM(CASE WHEN ' . $db->quoteName('approved') . ' = 1 AND ' . $db->quoteName('block_enabled') . ' = 1 THEN 1 ELSE 0 END) AS rules_on',
                    'SUM(CASE WHEN ' . $db->quoteName('approved') . ' = 1 AND ' . $db->quoteName('block_enabled') . ' = 1 AND ' . $db->quoteName('last_evidence') . ' NOT IN ('
                        . $db->quote('blocked') . ',' . $db->quote('builtin') . ') THEN 1 ELSE 0 END) AS unverified',
                ])
                ->from($db->quoteName('#__joomcookies_services'));
            $db->setQuery($query);
            $row = $db->loadAssoc() ?: [];
            $catalog['identified'] = (int) ($row['identified'] ?? 0);
            $catalog['classified'] = (int) ($row['classified'] ?? 0);
            $catalog['review'] = (int) ($row['review'] ?? 0);
            $catalog['rules_on'] = (int) ($row['rules_on'] ?? 0);
            $catalog['unverified'] = (int) ($row['unverified'] ?? 0);
        } catch (\Throwable) {
        }

        if (\is_array($lastScan) && (int) ($lastScan['id'] ?? 0) > 0) {
            $summary = json_decode((string) ($lastScan['summary'] ?? '{}'), true);
            $summary = \is_array($summary) ? $summary : [];
            $done = (int) ($summary['done'] ?? $summary['analyzed'] ?? 0);
            $queued = (int) ($summary['queued'] ?? $summary['discovered'] ?? 0);
            $catalog['coverage'] = $done . ($queued > 0 ? ' / ' . $queued : '');

            try {
                $query = $db->getQuery(true)
                    ->select('COUNT(*)')
                    ->from($db->quoteName('#__joomcookies_findings'))
                    ->where($db->quoteName('scan_id') . ' = ' . (int) $lastScan['id'])
                    ->where($db->quoteName('type') . ' = ' . $db->quote('anomaly'));
                $db->setQuery($query);
                $catalog['anomalies'] = (int) $db->loadResult();
            } catch (\Throwable) {
            }
        }

        return [
            'plugin_enabled'   => $plugin && PluginHelper::isEnabled('system', 'joomcookies'),
            'enabled'          => (int) SettingsStore::get('enabled', 1) === 1,
            'consent_version'  => (string) SettingsStore::get('consent_version', '1'),
            'last_scan'        => $lastScan,
            'catalog'          => $catalog,
            'builtin_rules'    => \count(ServiceRegistry::builtIn()),
            'browser_scan'     => $driver->describe(),
            'browser_available'=> $driver->isAvailable(),
        ];
    }
}
