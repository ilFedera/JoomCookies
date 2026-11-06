<?php

/**
 * @package     JoomCookies
 * @subpackage  com_joomcookies
 */

namespace JoomCookies\Component\Joomcookies\Administrator\Controller;

\defined('_JEXEC') or die;

use JoomCookies\Component\Joomcookies\Administrator\Service\SettingsStore;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

class ConfigController extends BaseController
{
    public function save(): void
    {
        $this->checkToken();

        if (!$this->app->getIdentity()->authorise('core.edit', 'com_joomcookies')) {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $view = $this->input->getCmd('return_view', 'banner');
        $data = $this->input->post->get('jform', [], 'array');
        $allowed = match ($view) {
            'banner' => ['position', 'color_background', 'color_text', 'color_accent', 'color_button_text', 'color_muted', 'show_reopen_button'],
            'texts' => ['text_title', 'text_message', 'text_accept', 'text_reject', 'text_customize', 'text_save', 'text_close', 'text_reopen', 'text_prefs_title', 'text_cookie_link', 'text_privacy_link', 'cookie_policy_url', 'privacy_policy_url'],
            'youtube' => ['youtube_nocookie', 'youtube_thumbnails', 'text_youtube_blocked', 'text_youtube_load', 'text_youtube_help', 'text_embed_blocked', 'text_embed_load'],
            'dashboard' => ['enabled', 'consent_version', 'diagnostic', 'cookie_lifetime_days'],
            'scan' => ['scan_max_pages', 'scan_max_depth', 'scan_max_seconds', 'scan_start_url'],
            'settings' => ['enabled', 'consent_version', 'cookie_lifetime_days', 'diagnostic', 'scan_max_pages', 'scan_max_depth', 'scan_max_seconds', 'scan_start_url'],
            default => [],
        };

        $filtered = [];

        foreach ($allowed as $key) {
            if (array_key_exists($key, $data)) {
                $filtered[$key] = \is_string($data[$key]) ? trim($data[$key]) : $data[$key];
            }
        }

        if ($filtered !== []) {
            SettingsStore::putMany($filtered);
        }

        $this->setMessage(Text::_('COM_JOOMCOOKIES_SAVED'));
        $this->setRedirect(Route::_('index.php?option=com_joomcookies&view=' . $view, false));
    }

    public function checkToken($method = 'post', $redirect = true)
    {
        if (!Session::checkToken($method)) {
            throw new \RuntimeException(Text::_('JINVALID_TOKEN'), 403);
        }

        return true;
    }
}
