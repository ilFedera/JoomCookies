<?php

/**
 * @package     JoomCookies
 * @subpackage  com_joomcookies
 */

namespace JoomCookies\Component\Joomcookies\Administrator\Model;

\defined('_JEXEC') or die;

use JoomCookies\Component\Joomcookies\Administrator\Service\Scan\UnavailableBrowserScanDriver;
use JoomCookies\Plugin\System\Joomcookies\Service\ServiceRegistry;
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
            'label' => 'COM_JOOMCOOKIES_CHECK_PLUGIN_INSTALLED',
            'ok'    => (bool) PluginHelper::getPlugin('system', 'joomcookies'),
        ];
        $checks[] = [
            'label' => 'COM_JOOMCOOKIES_CHECK_PLUGIN_ENABLED',
            'ok'    => PluginHelper::isEnabled('system', 'joomcookies'),
        ];

        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__joomcookies_logs'))
            ->order($db->quoteName('id') . ' DESC');
        $db->setQuery($query, 0, 50);
        $logs = $db->loadAssocList() ?: [];

        $catalog = [];

        try {
            $query = $db->getQuery(true)
                ->select('*')
                ->from($db->quoteName('#__joomcookies_services'))
                ->order($db->quoteName('alias') . ' ASC');
            $db->setQuery($query);
            $catalog = $db->loadAssocList('alias') ?: [];
        } catch (\Throwable) {
            $catalog = [];
        }

        $rules = [];

        foreach (ServiceRegistry::builtIn() as $service) {
            $row = $catalog[$service['id']] ?? null;
            $enabled = $row === null
                ? true
                : ((int) ($row['approved'] ?? 0) === 1 && (int) ($row['block_enabled'] ?? 0) === 1);
            $evidence = (string) ($row['last_evidence'] ?? '');
            $rules[] = [
                'service'   => $service['name'],
                'alias'     => $service['id'],
                'category'  => $service['category'],
                'origin'    => 'builtin',
                'enabled'   => $enabled,
                'coverage'  => $service['match_type'] . ': ' . $service['match_value'],
                'verified'  => \in_array($evidence, ['blocked', 'builtin'], true) ? $evidence : '',
                'pages'     => (int) ($row['page_count'] ?? 0),
                'problem'   => $row === null ? 'Catalogue row not seeded yet.' : '',
            ];
        }

        foreach ($catalog as $alias => $row) {
            $known = false;

            foreach ($rules as $rule) {
                if ($rule['alias'] === $alias) {
                    $known = true;
                    break;
                }
            }

            if ($known) {
                continue;
            }

            $evidence = (string) ($row['last_evidence'] ?? '');
            $rules[] = [
                'service'   => (string) $row['name'],
                'alias'     => (string) $alias,
                'category'  => (string) $row['category'],
                'origin'    => (string) ($row['source'] ?? 'scan'),
                'enabled'   => (int) ($row['approved'] ?? 0) === 1 && (int) ($row['block_enabled'] ?? 0) === 1,
                'coverage'  => (string) ($row['match_type'] ?? '') . ': ' . (string) ($row['match_value'] ?? ''),
                'verified'  => \in_array($evidence, ['blocked', 'observed'], true) ? $evidence : '',
                'pages'     => (int) ($row['page_count'] ?? 0),
                'problem'   => ((string) ($row['classification_status'] ?? '') === 'review')
                    ? 'Needs classification. Not treated as necessary.'
                    : '',
            ];
        }

        return [
            'checks' => $checks,
            'logs'   => $logs,
            'rules'  => $rules,
            'browser'=> (new UnavailableBrowserScanDriver())->describe(),
        ];
    }
}
