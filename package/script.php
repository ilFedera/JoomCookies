<?php

/**
 * @package     JoomCookies
 * @subpackage  pkg_joomcookies
 */

\defined('_JEXEC') or die;

use JoomCookies\Component\Joomcookies\Administrator\Service\Migration\PluginMigrator;
use Joomla\CMS\Factory;
use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\CMS\Installer\InstallerScriptInterface;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;

class Pkg_joomcookiesInstallerScript implements InstallerScriptInterface
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
            Factory::getApplication()->enqueueMessage(
                sprintf(Text::_('JLIB_INSTALLER_MINIMUM_PHP'), $this->minimumPhp),
                'error'
            );

            return false;
        }

        if (!\defined('JVERSION') || version_compare(JVERSION, $this->minimumJoomla, '<')) {
            Factory::getApplication()->enqueueMessage(
                sprintf(Text::_('JLIB_INSTALLER_MINIMUM_JOOMLA'), $this->minimumJoomla),
                'error'
            );

            return false;
        }

        return true;
    }

    public function postflight(string $type, InstallerAdapter $adapter): bool
    {
        if (\in_array($type, ['install', 'update', 'discover_install'], true)) {
            $this->enablePlugin();
            $this->enableComponent();

            try {
                if (class_exists(PluginMigrator::class)) {
                    PluginMigrator::run(false);
                }
            } catch (\Throwable) {
                // Retry from Components → JoomCookies → Overview.
            }
        }

        return true;
    }

    private function enablePlugin(): void
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->update($db->quoteName('#__extensions'))
            ->set($db->quoteName('enabled') . ' = 1')
            ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
            ->where($db->quoteName('folder') . ' = ' . $db->quote('system'))
            ->where($db->quoteName('element') . ' = ' . $db->quote('joomcookies'));
        $db->setQuery($query)->execute();
    }

    private function enableComponent(): void
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->update($db->quoteName('#__extensions'))
            ->set($db->quoteName('enabled') . ' = 1')
            ->where($db->quoteName('type') . ' = ' . $db->quote('component'))
            ->where($db->quoteName('element') . ' = ' . $db->quote('com_joomcookies'));
        $db->setQuery($query)->execute();
    }
}

return new Pkg_joomcookiesInstallerScript();
