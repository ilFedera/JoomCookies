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

class ServiceModel extends BaseDatabaseModel
{
    /**
     * @return array<string, mixed>
     */
    public function getItem(): array
    {
        $id = (int) Factory::getApplication()->getInput()->getInt('id');

        if ($id < 1) {
            return [
                'id' => 0,
                'alias' => '',
                'name' => '',
                'provider' => '',
                'category' => 'statistics',
                'match_type' => 'domain',
                'match_value' => '',
                'cookies' => '',
                'notes' => '',
                'approved' => 0,
                'block_enabled' => 0,
                'source' => 'manual',
            ];
        }

        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__joomcookies_services'))
            ->where($db->quoteName('id') . ' = ' . $id);
        $db->setQuery($query);

        return $db->loadAssoc() ?: [];
    }
}
