<?php

/**
 * @package     JoomCookies
 * @subpackage  com_joomcookies
 */

\defined('_JEXEC') or die;

use JoomCookies\Component\Joomcookies\Administrator\Service\Migration\PluginMigrator;
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
                $this->seedCategories();
                PluginMigrator::run(false);
            } catch (\Throwable) {
                // Seeding/migration can be retried from the dashboard.
            }
        }

        return true;
    }

    private function seedCategories(): void
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $defaults = [
            ['necessary', 'Necessary', 'Required for the site to work, including storage of this consent choice.', 1, 1],
            ['statistics', 'Statistics', 'Measurement tools. Off until accepted.', 0, 2],
            ['marketing', 'Marketing', 'Advertising and similar tools. Off until accepted.', 0, 3],
            ['embeds', 'External content', 'Videos, maps and embeds. Off until accepted.', 0, 4],
        ];

        foreach ($defaults as [$alias, $title, $description, $required, $ordering]) {
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
}

return new Com_JoomcookiesInstallerScript();
