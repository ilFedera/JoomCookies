<?php

/**
 * @package     JoomCookies
 * @subpackage  com_joomcookies
 */

namespace JoomCookies\Component\Joomcookies\Administrator\Model;

\defined('_JEXEC') or die;

use JoomCookies\Component\Joomcookies\Administrator\Service\SettingsStore;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;

class ConfigModel extends BaseDatabaseModel
{
    /**
     * @return array<string, mixed>
     */
    public function getSettings(): array
    {
        return SettingsStore::all();
    }
}
