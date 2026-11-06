<?php

/**
 * @package     JoomCookies
 * @subpackage  com_joomcookies
 */

namespace JoomCookies\Component\Joomcookies\Administrator\Model;

\defined('_JEXEC') or die;

use JoomCookies\Component\Joomcookies\Administrator\Service\Scan\ScanComparer;
use JoomCookies\Component\Joomcookies\Administrator\Service\Scan\UnavailableBrowserScanDriver;
use JoomCookies\Component\Joomcookies\Administrator\Service\SettingsStore;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\DatabaseInterface;

class ScanModel extends BaseDatabaseModel
{
    /**
     * @return array<string, mixed>
     */
    public function getScanPage(): array
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $id = (int) Factory::getApplication()->getInput()->getInt('id');
        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__joomcookies_scans'))
            ->order($db->quoteName('id') . ' DESC');
        $db->setQuery($query, 0, 10);
        $scans = $db->loadAssocList() ?: [];
        $current = null;

        foreach ($scans as $scan) {
            if ($id === 0 || (int) $scan['id'] === $id) {
                $current = $scan;
                break;
            }
        }

        $findings = [];
        $diff = null;

        if ($current) {
            $query = $db->getQuery(true)
                ->select('*')
                ->from($db->quoteName('#__joomcookies_findings'))
                ->where($db->quoteName('scan_id') . ' = ' . (int) $current['id']);
            $db->setQuery($query);
            $findings = $db->loadAssocList() ?: [];

            if (isset($scans[1])) {
                $query = $db->getQuery(true)
                    ->select('*')
                    ->from($db->quoteName('#__joomcookies_findings'))
                    ->where($db->quoteName('scan_id') . ' = ' . (int) $scans[1]['id']);
                $db->setQuery($query);
                $diff = ScanComparer::diff($db->loadAssocList() ?: [], $findings);
            }
        }

        $driver = new UnavailableBrowserScanDriver();

        return [
            'start_url' => (string) SettingsStore::get('scan_start_url', Uri::root()),
            'scans'     => $scans,
            'current'   => $current,
            'findings'  => $findings,
            'diff'      => $diff,
            'browser'   => $driver->describe(),
            'browser_available' => $driver->isAvailable(),
            'limits'    => [
                'max_pages'   => SettingsStore::get('scan_max_pages', 10),
                'max_depth'   => SettingsStore::get('scan_max_depth', 2),
                'max_seconds' => SettingsStore::get('scan_max_seconds', 25),
            ],
        ];
    }
}
