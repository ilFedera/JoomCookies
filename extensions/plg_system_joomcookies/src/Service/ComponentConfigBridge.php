<?php

/**
 * @package     JoomCookies
 * @subpackage  plg_system_joomcookies
 */

namespace JoomCookies\Plugin\System\Joomcookies\Service;

\defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;

/**
 * Reads approved component configuration for the system plugin.
 * Falls back silently when the component or tables are missing.
 */
final class ComponentConfigBridge
{
    /**
     * @return array{settings: array<string, mixed>, services: list<array<string, mixed>>, categories: list<array<string, mixed>>}|null>
     */
    public static function load(): ?array
    {
        try {
            if (!class_exists(ComponentHelper::class) || !ComponentHelper::isEnabled('com_joomcookies')) {
                return null;
            }

            $db = Factory::getContainer()->get(DatabaseInterface::class);

            if (!self::tableExists($db, '#__joomcookies_settings')) {
                return null;
            }

            $settings = self::settings($db);

            if ($settings === []) {
                return null;
            }

            $services = [];
            $suppressed = [];
            $categories = [];

            if (self::tableExists($db, '#__joomcookies_services')) {
                $services = self::approvedBlockServices($db);
                $suppressed = self::suppressedBuiltinIds($db);
            }

            if (self::tableExists($db, '#__joomcookies_categories')) {
                $categories = self::categories($db);
            }

            return [
                'settings'    => $settings,
                'services'    => $services,
                'suppressed'  => $suppressed,
                'categories'  => $categories,
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    public static function invalidate(): void
    {
        // Request-level cache lives in the plugin; component updates bump settings.updated.
    }

    /**
     * @return array<string, mixed>
     */
    private static function settings(DatabaseInterface $db): array
    {
        $query = $db->getQuery(true)
            ->select([$db->quoteName('setting_key'), $db->quoteName('setting_value')])
            ->from($db->quoteName('#__joomcookies_settings'));
        $db->setQuery($query);
        $rows = $db->loadAssocList() ?: [];
        $out  = [];

        foreach ($rows as $row) {
            $key = (string) ($row['setting_key'] ?? '');
            $raw = (string) ($row['setting_value'] ?? '');

            if ($key === '') {
                continue;
            }

            $decoded = json_decode($raw, true);
            $out[$key] = json_last_error() === JSON_ERROR_NONE ? $decoded : $raw;
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function categories(DatabaseInterface $db): array
    {
        $query = $db->getQuery(true)
            ->select(['alias', 'title', 'description', 'required', 'ordering'])
            ->from($db->quoteName('#__joomcookies_categories'))
            ->where($db->quoteName('published') . ' = 1')
            ->order($db->quoteName('ordering') . ' ASC');
        $db->setQuery($query);

        return $db->loadAssocList() ?: [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function approvedBlockServices(DatabaseInterface $db): array
    {
        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__joomcookies_services'))
            ->where($db->quoteName('approved') . ' = 1')
            ->where($db->quoteName('block_enabled') . ' = 1');
        $db->setQuery($query);
        $rows = $db->loadAssocList() ?: [];
        $out  = [];

        foreach ($rows as $row) {
            $out[] = [
                'id'          => (string) ($row['alias'] ?? ''),
                'name'        => (string) ($row['name'] ?? ''),
                'category'    => (string) ($row['category'] ?? ''),
                'match_type'  => (string) ($row['match_type'] ?? 'domain'),
                'match_value' => (string) ($row['match_value'] ?? ''),
                'cookies'     => (string) ($row['cookies'] ?? ''),
            ];
        }

        return $out;
    }

    /**
     * Built-in IDs the administrator disabled in the catalogue, so the plugin
     * does not apply the same signature twice or after an explicit disable.
     *
     * @return list<string>
     */
    private static function suppressedBuiltinIds(DatabaseInterface $db): array
    {
        $query = $db->getQuery(true)
            ->select($db->quoteName('alias'))
            ->from($db->quoteName('#__joomcookies_services'))
            ->where($db->quoteName('block_enabled') . ' = 0')
            ->where(
                '(' . $db->quoteName('approved') . ' = 1 OR ' . $db->quoteName('source') . ' = ' . $db->quote('builtin') . ')'
            );
        $db->setQuery($query);
        $rows = $db->loadColumn() ?: [];

        return array_values(array_filter(array_map('strval', $rows)));
    }

    private static function tableExists(DatabaseInterface $db, string $table): bool
    {
        $name = str_replace('#__', $db->getPrefix(), $table);
        $tables = $db->getTableList();

        return \in_array(strtolower($name), array_map('strtolower', $tables), true);
    }
}
