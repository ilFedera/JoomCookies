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
        $summary = [];

        if ($current && !empty($current['summary'])) {
            $decoded = json_decode((string) $current['summary'], true);
            $summary = \is_array($decoded) ? $decoded : [];
        }

        return [
            'start_url' => (string) SettingsStore::get('scan_start_url', Uri::root()),
            'scans'     => $scans,
            'current'   => $current,
            'findings'  => $findings,
            'stats'     => self::stats($findings, $summary),
            'diff'      => $diff,
            'browser'   => $driver->describe(),
            'browser_available' => $driver->isAvailable(),
            'limits'    => [
                'max_pages'   => min(50, max(1, (int) SettingsStore::get('scan_max_pages', 10))),
                'max_depth'   => SettingsStore::get('scan_max_depth', 2),
                'max_seconds' => SettingsStore::get('scan_max_seconds', 25),
            ],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $findings
     * @param  array<string, mixed>        $summary
     * @return array<string, int>
     */
    private static function stats(array $findings, array $summary): array
    {
        $pages = [];
        $stats = [
            'pages'            => (int) ($summary['pages'] ?? 0),
            'cookie_observed'  => 0,
            'cookie_reference' => 0,
            'script'           => 0,
            'iframe'           => 0,
            'known'            => 0,
            'unknown'          => 0,
            'total'            => \count($findings),
        ];

        foreach ($findings as $row) {
            $page = (string) ($row['page_url'] ?? '');

            if ($page !== '') {
                $pages[$page] = true;
            }

            $type = (string) ($row['type'] ?? '');

            if ($type === 'cookie_header' || ((int) ($row['observed'] ?? 0) === 1 && str_contains($type, 'cookie'))) {
                $stats['cookie_observed']++;
            }

            if ($type === 'cookie_reference') {
                $stats['cookie_reference']++;
            }

            if ($type === 'script') {
                $stats['script']++;
            }

            if ($type === 'iframe') {
                $stats['iframe']++;
            }

            if (\in_array($type, ['script', 'iframe'], true)) {
                if ((string) ($row['service_id'] ?? '') !== '') {
                    $stats['known']++;
                } else {
                    $stats['unknown']++;
                }
            }
        }

        if ($stats['pages'] < 1) {
            $stats['pages'] = \count($pages);
        }

        return $stats;
    }
}
