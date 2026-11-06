<?php

/**
 * @package     JoomCookies
 * @subpackage  com_joomcookies
 */

namespace JoomCookies\Component\Joomcookies\Administrator\Controller;

\defined('_JEXEC') or die;

use JoomCookies\Component\Joomcookies\Administrator\Service\Migration\PluginMigrator;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

class MigrationController extends BaseController
{
    public function run(): void
    {
        $this->checkToken();

        if (!$this->app->getIdentity()->authorise('core.admin', 'com_joomcookies')) {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $force = $this->input->getInt('force', 0) === 1;
        $result = PluginMigrator::run($force);
        $this->setMessage($result ? Text::_('COM_JOOMCOOKIES_MIGRATION_DONE') : Text::_('COM_JOOMCOOKIES_MIGRATION_SKIPPED'));
        $this->setRedirect(Route::_('index.php?option=com_joomcookies&view=dashboard', false));
    }

    public function rollback(): void
    {
        $this->checkToken();

        if (!$this->app->getIdentity()->authorise('core.admin', 'com_joomcookies')) {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $ok = PluginMigrator::rollback();
        $this->setMessage($ok ? Text::_('COM_JOOMCOOKIES_MIGRATION_REVERSED') : Text::_('COM_JOOMCOOKIES_MIGRATION_NO_SNAPSHOT'), $ok ? 'message' : 'warning');
        $this->setRedirect(Route::_('index.php?option=com_joomcookies&view=dashboard', false));
    }

    public function checkToken($method = 'post', $redirect = true)
    {
        if (!Session::checkToken($method)) {
            throw new \RuntimeException(Text::_('JINVALID_TOKEN'), 403);
        }

        return true;
    }
}
