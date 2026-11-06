<?php

/**
 * @package     JoomCookies
 * @subpackage  plg_system_joomcookies
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\CMS\Installer\InstallerScriptInterface;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;

/**
 * Installer script compatible with Joomla 5 (named class) and Joomla 6
 * (InstallerScriptInterface returned from this file).
 */
class PlgSystemJoomcookiesInstallerScript implements InstallerScriptInterface
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
        if (\in_array($type, ['install', 'discover_install'], true)) {
            $this->enablePlugin();
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
}

return new PlgSystemJoomcookiesInstallerScript();
