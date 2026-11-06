<?php

/**
 * @package     JoomCookies
 * @subpackage  com_joomcookies
 */

namespace JoomCookies\Component\Joomcookies\Administrator\Service;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;

final class SettingsStore
{
    /**
     * @return array<string, mixed>
     */
    public static function all(?DatabaseInterface $db = null): array
    {
        $db    = $db ?? Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select([$db->quoteName('setting_key'), $db->quoteName('setting_value')])
            ->from($db->quoteName('#__joomcookies_settings'));
        $db->setQuery($query);
        $out = [];

        foreach ($db->loadAssocList() ?: [] as $row) {
            $key = (string) $row['setting_key'];
            $raw = (string) $row['setting_value'];
            $decoded = json_decode($raw, true);
            $out[$key] = json_last_error() === JSON_ERROR_NONE ? $decoded : $raw;
        }

        return $out;
    }

    public static function get(string $key, mixed $default = null, ?DatabaseInterface $db = null): mixed
    {
        $all = self::all($db);

        return $all[$key] ?? $default;
    }

    public static function setIfMissing(string $key, mixed $value, ?DatabaseInterface $db = null): void
    {
        $all = self::all($db);

        if (!array_key_exists($key, $all)) {
            self::set($key, $value, $db);
        }
    }

    public static function set(string $key, mixed $value, ?DatabaseInterface $db = null): void
    {
        $db    = $db ?? Factory::getContainer()->get(DatabaseInterface::class);
        $now   = Factory::getDate()->toSql();
        $json  = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__joomcookies_settings'))
            ->where($db->quoteName('setting_key') . ' = ' . $db->quote($key));
        $db->setQuery($query);
        $exists = (int) $db->loadResult() > 0;

        if ($exists) {
            $query = $db->getQuery(true)
                ->update($db->quoteName('#__joomcookies_settings'))
                ->set($db->quoteName('setting_value') . ' = ' . $db->quote((string) $json))
                ->set($db->quoteName('updated_at') . ' = ' . $db->quote($now))
                ->where($db->quoteName('setting_key') . ' = ' . $db->quote($key));
        } else {
            $query = $db->getQuery(true)
                ->insert($db->quoteName('#__joomcookies_settings'))
                ->columns([$db->quoteName('setting_key'), $db->quoteName('setting_value'), $db->quoteName('updated_at')])
                ->values($db->quote($key) . ',' . $db->quote((string) $json) . ',' . $db->quote($now));
        }

        $db->setQuery($query)->execute();
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public static function putMany(array $values, ?DatabaseInterface $db = null): void
    {
        foreach ($values as $key => $value) {
            self::set((string) $key, $value, $db);
        }

        self::set('_cache_token', gmdate('c'), $db);
    }
}
