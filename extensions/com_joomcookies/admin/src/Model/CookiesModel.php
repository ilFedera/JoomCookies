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
            ->select($db->quoteName('id'))
            ->from($db->quoteName('#__joomcookies_scans'))
            ->where($db->quoteName('status') . ' = ' . $db->quote('complete'))
            ->order($db->quoteName('id') . ' DESC');
        $db->setQuery($query, 0, 1);
        $scanId = (int) $db->loadResult();

        if ($scanId < 1) {
            return [];
        }

        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__joomcookies_findings'))
            ->where($db->quoteName('scan_id') . ' = ' . $scanId)
            ->where($db->quoteName('type') . ' IN (' . $db->quote('cookie_header') . ',' . $db->quote('cookie_reference') . ',' . $db->quote('storage_reference') . ')')
            ->order($db->quoteName('type') . ' ASC');
        $db->setQuery($query);

        return $db->loadAssocList() ?: [];
    }
}
