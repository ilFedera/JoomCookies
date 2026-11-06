<?php

/**
 * @package     JoomCookies
 * @subpackage  com_joomcookies
 */

\defined('_JEXEC') or die;

use JoomCookies\Component\Joomcookies\Administrator\Service\DefaultCopy;
use JoomCookies\Component\Joomcookies\Administrator\Service\Migration\PluginMigrator;
use JoomCookies\Component\Joomcookies\Administrator\Service\SettingsStore;
use Joomla\CMS\Factory;
use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\CMS\Installer\InstallerScriptInterface;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;

class Com_JoomcookiesInstallerScript implements InstallerScriptInterface
{
    private string $minimumJoomla = '5.0.0';
    private string $minimumPhp = '8.1.0';

    public function install(InstallerAdapter $adapter): bool
    {
        return true;
    }

    public function update(InstallerAdapter $adapter): bool
    {
        return true;
    }

    public function uninstall(InstallerAdapter $adapter): bool
    {
        return true;
    }

    public function preflight(string $type, InstallerAdapter $adapter): bool
    {
        if (version_compare(PHP_VERSION, $this->minimumPhp, '<')) {
            Factory::getApplication()->enqueueMessage(sprintf(Text::_('JLIB_INSTALLER_MINIMUM_PHP'), $this->minimumPhp), 'error');

            return false;
        }

        if (!\defined('JVERSION') || version_compare(JVERSION, $this->minimumJoomla, '<')) {
            Factory::getApplication()->enqueueMessage(sprintf(Text::_('JLIB_INSTALLER_MINIMUM_JOOMLA'), $this->minimumJoomla), 'error');

            return false;
        }

        return true;
    }

    public function postflight(string $type, InstallerAdapter $adapter): bool
    {
        if (\in_array($type, ['install', 'update', 'discover_install'], true)) {
            try {
                $this->ensureScanQueueTable();
                $this->seedCategories();
                $this->seedSettings();
                PluginMigrator::run(false);
            } catch (\Throwable) {
                // Seeding/migration can be retried from the dashboard.
            }
        }

        return true;
    }

    private function ensureScanQueueTable(): void
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $db->setQuery(
            'CREATE TABLE IF NOT EXISTS ' . $db->quoteName('#__joomcookies_scan_queue') . ' (
              `id` int unsigned NOT NULL AUTO_INCREMENT,
              `scan_id` int unsigned NOT NULL,
              `url` varchar(1024) NOT NULL DEFAULT \'\',
              `normalized` varchar(512) NOT NULL DEFAULT \'\',
              `depth` smallint unsigned NOT NULL DEFAULT 0,
              `status` varchar(16) NOT NULL DEFAULT \'pending\',
              `error` varchar(255) NOT NULL DEFAULT \'\',
              PRIMARY KEY (`id`),
              UNIQUE KEY `idx_scan_normalized` (`scan_id`, `normalized`(191)),
              KEY `idx_scan_status` (`scan_id`, `status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci'
        );
        $db->execute();
    }

    private function seedCategories(): void
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        foreach (DefaultCopy::categories() as $row) {
            $alias       = $row['alias'];
            $title       = $row['title'];
            $description = $row['description'];
            $required    = $row['required'];
            $ordering    = $row['ordering'];
            $query = $db->getQuery(true)
                ->select('COUNT(*)')
                ->from($db->quoteName('#__joomcookies_categories'))
                ->where($db->quoteName('alias') . ' = ' . $db->quote($alias));
            $db->setQuery($query);

            if ((int) $db->loadResult() > 0) {
                continue;
            }

            $query = $db->getQuery(true)
                ->insert($db->quoteName('#__joomcookies_categories'))
                ->columns(['alias', 'title', 'description', 'required', 'ordering', 'published'])
                ->values(implode(',', [
                    $db->quote($alias),
                    $db->quote($title),
                    $db->quote($description),
                    (int) $required,
                    (int) $ordering,
                    1,
                ]));
            $db->setQuery($query)->execute();
        }
    }

    private function seedSettings(): void
    {
        try {
            foreach (DefaultCopy::settings() as $key => $value) {
                SettingsStore::setIfMissing($key, $value);
            }
        } catch (\Throwable) {
            // Tables may not be ready yet; a later update retries.
        }
    }
}

return new Com_JoomcookiesInstallerScript();
