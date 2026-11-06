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

class CategoriesModel extends BaseDatabaseModel
{
    /**
     * @return list<array<string, mixed>>
     */
    public function getItems(): array
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__joomcookies_categories'))
            ->order($db->quoteName('ordering') . ' ASC');
        $db->setQuery($query);

        return $db->loadAssocList() ?: [];
    }
}
