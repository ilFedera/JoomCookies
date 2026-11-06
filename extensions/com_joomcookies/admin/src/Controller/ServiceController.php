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

class ServiceController extends BaseController
{
    public function save(): void
    {
        $this->checkToken();

        if (!$this->app->getIdentity()->authorise('core.edit', 'com_joomcookies')) {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $data = $this->input->post->get('jform', [], 'array');
        $id   = (int) ($data['id'] ?? 0);
        $db   = Factory::getContainer()->get(DatabaseInterface::class);
        $now  = Factory::getDate()->toSql();
        $alias = preg_replace('/[^a-z0-9_-]/', '', strtolower((string) ($data['alias'] ?? '')));
        $row = [
            'alias'        => $alias,
            'name'         => strip_tags((string) ($data['name'] ?? '')),
            'provider'     => strip_tags((string) ($data['provider'] ?? '')),
            'category'     => \in_array($data['category'] ?? '', ['statistics', 'marketing', 'embeds'], true) ? $data['category'] : 'statistics',
            'match_type'   => \in_array($data['match_type'] ?? '', ['domain', 'url_contains', 'script_id', 'content_contains'], true) ? $data['match_type'] : 'domain',
            'match_value'  => trim((string) ($data['match_value'] ?? '')),
            'cookies'      => strip_tags((string) ($data['cookies'] ?? '')),
            'notes'        => strip_tags((string) ($data['notes'] ?? '')),
            'source'       => 'manual',
            'modified'     => $now,
        ];

        if ($alias === '' || $row['match_value'] === '') {
            $this->setMessage(Text::_('COM_JOOMCOOKIES_SERVICE_INVALID'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_joomcookies&view=service&layout=edit&id=' . $id, false));

            return;
        }

        if ($id > 0) {
            $query = $db->getQuery(true)->update($db->quoteName('#__joomcookies_services'))->where($db->quoteName('id') . ' = ' . $id);
            foreach ($row as $key => $value) {
                $query->set($db->quoteName($key) . ' = ' . $db->quote($value));
            }
            $db->setQuery($query)->execute();
        } else {
            $row['approved'] = 0;
            $row['block_enabled'] = 0;
            $row['created'] = $now;
            $columns = array_keys($row);
            $values  = array_map(static fn ($v) => $db->quote($v), array_values($row));
            $query = $db->getQuery(true)
                ->insert($db->quoteName('#__joomcookies_services'))
                ->columns($columns)
                ->values(implode(',', $values));
            $db->setQuery($query)->execute();
        }

        $this->setMessage(Text::_('COM_JOOMCOOKIES_SAVED'));
        $this->setRedirect(Route::_('index.php?option=com_joomcookies&view=services', false));
    }

    public function approve(): void
    {
        $this->mutateFlag('approved', 1, 'joomcookies.approve');
    }

    public function enableBlock(): void
    {
        $this->checkToken();

        if (!$this->app->getIdentity()->authorise('joomcookies.approve', 'com_joomcookies') && !$this->app->getIdentity()->authorise('core.admin', 'com_joomcookies')) {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $id = $this->input->getInt('id');
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select($db->quoteName('approved'))
            ->from($db->quoteName('#__joomcookies_services'))
            ->where($db->quoteName('id') . ' = ' . (int) $id);
        $db->setQuery($query);

        if ((int) $db->loadResult() !== 1) {
            $this->setMessage(Text::_('COM_JOOMCOOKIES_BLOCK_REQUIRES_APPROVAL'), 'warning');
            $this->setRedirect(Route::_('index.php?option=com_joomcookies&view=services', false));

            return;
        }

        $query = $db->getQuery(true)
            ->update($db->quoteName('#__joomcookies_services'))
            ->set($db->quoteName('block_enabled') . ' = 1')
            ->where($db->quoteName('id') . ' = ' . (int) $id);
        $db->setQuery($query)->execute();
        $this->setMessage(Text::_('COM_JOOMCOOKIES_SAVED'));
        $this->setRedirect(Route::_('index.php?option=com_joomcookies&view=services', false));
    }

    public function disableBlock(): void
    {
        $this->mutateFlag('block_enabled', 0, 'core.edit');
    }

    private function mutateFlag(string $column, int $value, string $acl): void
    {
        $this->checkToken();

        if (!$this->app->getIdentity()->authorise($acl, 'com_joomcookies') && !$this->app->getIdentity()->authorise('core.admin', 'com_joomcookies')) {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $id = $this->input->getInt('id');
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->update($db->quoteName('#__joomcookies_services'))
            ->set($db->quoteName($column) . ' = ' . $value)
            ->where($db->quoteName('id') . ' = ' . (int) $id);
        $db->setQuery($query)->execute();
        $this->setMessage(Text::_('COM_JOOMCOOKIES_SAVED'));
        $this->setRedirect(Route::_('index.php?option=com_joomcookies&view=services', false));
    }

    public function checkToken($method = 'post', $redirect = true)
    {
        if (!Session::checkToken($method)) {
            throw new \RuntimeException(Text::_('JINVALID_TOKEN'), 403);
        }

        return true;
    }
}
