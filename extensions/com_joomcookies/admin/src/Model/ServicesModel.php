<?php

/**
 * @package     JoomCookies
 * @subpackage  com_joomcookies
 */

namespace JoomCookies\Component\Joomcookies\Administrator\Model;

\defined('_JEXEC') or die;

use JoomCookies\Component\Joomcookies\Administrator\Service\Catalog\CatalogSync;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\Database\DatabaseInterface;

class ServicesModel extends BaseDatabaseModel
{
    /**
     * @return list<array<string, mixed>>
     */
    public function getItems(): array
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);

        try {
            (new CatalogSync($db))->seedKnownServices();
        } catch (\Throwable) {
        }

        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__joomcookies_services'))
            ->order($db->quoteName('name') . ' ASC');
        $db->setQuery($query);

        return $db->loadAssocList() ?: [];
    }
}
