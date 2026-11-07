<?php

namespace JoomCookies\Component\Joomcookies\Administrator\Controller;

\defined('_JEXEC') or die;

use JoomCookies\Component\Joomcookies\Administrator\Service\YoutubeThumbnailCache;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

class YoutubeController extends BaseController
{
    public function fetchThumbnail(): void
    {
        if (!Session::checkToken('post') || !$this->app->getIdentity()->authorise('core.edit', 'com_joomcookies')) { throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403); }
        try {
            YoutubeThumbnailCache::fetch((string) $this->input->post->getCmd('video_id', ''));
            $this->setMessage('Miniatura locale disponibile.');
        } catch (\Throwable $e) {
            $this->setMessage('Miniatura non disponibile: ' . $e->getMessage(), 'error');
        }
        $this->setRedirect(Route::_('index.php?option=com_joomcookies&view=youtube', false));
    }
}
