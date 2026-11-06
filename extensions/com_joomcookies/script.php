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
                PluginMigrator::run(false);
            } catch (\Throwable) {
                // Migration is retried from the dashboard.
            }
        }

        return true;
    }
}

return new Com_JoomcookiesInstallerScript();
