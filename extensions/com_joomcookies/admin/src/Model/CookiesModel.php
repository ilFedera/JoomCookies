<?php

/**
 * @package     JoomCookies
 * @subpackage  com_joomcookies
 */

namespace JoomCookies\Component\Joomcookies\Administrator\Model;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\Database\DatabaseInterface;

class CookiesModel extends BaseDatabaseModel
{
    /**
     * @return list<array<string, mixed>>
     */
    public function getItems(): array
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__joomcookies_cookie_registry'))
            ->order($db->quoteName('last_observed_at') . ' DESC');
        $db->setQuery($query);
        return $db->loadAssocList() ?: [];
    }
}
