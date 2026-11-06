<?php

/**
 * @package     JoomCookies
 * @subpackage  com_joomcookies
 */

namespace JoomCookies\Component\Joomcookies\Administrator\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\Database\DatabaseInterface;

class CategoryController extends BaseController
{
    public function save(): void
    {
        $this->checkToken();

        if (!$this->app->getIdentity()->authorise('core.edit', 'com_joomcookies')) {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $rows = $this->input->post->get('jform', [], 'array');
        $db   = Factory::getContainer()->get(DatabaseInterface::class);

        foreach ($rows as $id => $row) {
            $id = (int) $id;
            $title = strip_tags((string) ($row['title'] ?? ''));
            $description = strip_tags((string) ($row['description'] ?? ''));

            if ($id < 1 || $title === '') {
                continue;
            }

            $query = $db->getQuery(true)
                ->update($db->quoteName('#__joomcookies_categories'))
                ->set($db->quoteName('title') . ' = ' . $db->quote($title))
                ->set($db->quoteName('description') . ' = ' . $db->quote($description))
                ->where($db->quoteName('id') . ' = ' . $id);
            $db->setQuery($query)->execute();
        }

        $this->setMessage(Text::_('COM_JOOMCOOKIES_SAVED'));
        $this->setRedirect(Route::_('index.php?option=com_joomcookies&view=categories', false));
    }

    public function checkToken($method = 'post', $redirect = true)
    {
        if (!Session::checkToken($method)) {
            throw new \RuntimeException(Text::_('JINVALID_TOKEN'), 403);
        }

        return true;
    }
}
