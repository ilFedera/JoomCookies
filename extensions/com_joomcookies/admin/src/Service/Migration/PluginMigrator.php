<?php

/**
 * @package     JoomCookies
 * @subpackage  com_joomcookies
 */

namespace JoomCookies\Component\Joomcookies\Administrator\Service\Migration;

\defined('_JEXEC') or die;

use JoomCookies\Component\Joomcookies\Administrator\Service\SettingsStore;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Database\DatabaseInterface;
use Joomla\Registry\Registry;

final class PluginMigrator
{
    public static function run(bool $force = false): bool
    {
        $plugin = PluginHelper::getPlugin('system', 'joomcookies');

        if (!$plugin) {
            return false;
        }

        $params = (new Registry($plugin->params))->toArray();
        $current = SettingsStore::all();

        if (!PluginMigration::shouldMigrate($current, $params, $force)) {
            return false;
        }

        $snapshot = $current;
        $next     = PluginMigration::apply($params, $current, $force);
        SettingsStore::putMany($next);
        self::migrateServices($params);
        self::storeHistory('plugin_to_component', PluginMigration::sourceHash($params), $snapshot);

        return true;
    }

    public static function rollback(): bool
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__joomcookies_migrations'))
            ->where($db->quoteName('name') . ' = ' . $db->quote('plugin_to_component'))
            ->where($db->quoteName('reversible') . ' = 1')
            ->order($db->quoteName('id') . ' DESC');
        $db->setQuery($query, 0, 1);
        $row = $db->loadAssoc();

        if (!$row) {
            return false;
        }

        $payload = json_decode((string) $row['payload'], true);

        if (!\is_array($payload)) {
            return false;
        }

        SettingsStore::putMany(PluginMigration::reverse($payload));

        return true;
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private static function migrateServices(array $params): void
    {
        $raw = $params['services'] ?? [];

        if (\is_string($raw)) {
            $raw = json_decode($raw, true) ?: [];
        }

        if (!\is_array($raw)) {
            return;
        }

        $db  = Factory::getContainer()->get(DatabaseInterface::class);
        $now = Factory::getDate()->toSql();

        foreach ($raw as $row) {
            if (!\is_array($row)) {
                continue;
            }

            $alias = preg_replace('/[^a-z0-9_-]/', '', strtolower((string) ($row['id'] ?? $row['name'] ?? '')));

            if ($alias === '') {
                continue;
            }

            $query = $db->getQuery(true)
                ->select('COUNT(*)')
                ->from($db->quoteName('#__joomcookies_services'))
                ->where($db->quoteName('alias') . ' = ' . $db->quote($alias));
            $db->setQuery($query);

            if ((int) $db->loadResult() > 0) {
                continue;
            }

            $record = [
                'alias'         => $alias,
                'name'          => strip_tags((string) ($row['name'] ?? $alias)),
                'provider'      => '',
                'category'      => (string) ($row['category'] ?? 'statistics'),
                'match_type'    => (string) ($row['match_type'] ?? 'domain'),
                'match_value'   => (string) ($row['match_value'] ?? ''),
                'cookies'       => \is_array($row['cookies'] ?? '') ? implode(',', $row['cookies']) : (string) ($row['cookies'] ?? ''),
                'source'        => 'manual',
                'approved'      => 1,
                'block_enabled' => 1,
                'notes'         => 'Migrated from plugin V1 parameters.',
                'created'       => $now,
                'modified'      => $now,
            ];
            $query = $db->getQuery(true)
                ->insert($db->quoteName('#__joomcookies_services'))
                ->columns(array_keys($record))
                ->values(implode(',', array_map(static fn ($v) => $db->quote($v), array_values($record))));
            $db->setQuery($query)->execute();
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function storeHistory(string $name, string $checksum, array $payload): void
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->insert($db->quoteName('#__joomcookies_migrations'))
            ->columns(['name', 'ran_at', 'checksum', 'reversible', 'payload'])
            ->values(implode(',', [
                $db->quote($name),
                $db->quote(Factory::getDate()->toSql()),
                $db->quote($checksum),
                1,
                $db->quote(json_encode($payload, JSON_UNESCAPED_SLASHES) ?: '{}'),
            ]));
        $db->setQuery($query)->execute();
    }
}
