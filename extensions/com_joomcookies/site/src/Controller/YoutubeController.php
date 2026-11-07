<?php

namespace JoomCookies\Component\Joomcookies\Site\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\BaseController;

class YoutubeController extends BaseController
{
    /** Public endpoint serves existing local files only; it never fetches remotely. */
    public function thumbnail(): void
    {
        $id = (string) $this->input->getCmd('id', '');
        if (preg_match('/^[A-Za-z0-9_-]{11}$/', $id) !== 1) { throw new \RuntimeException('Not found', 404); }
        $base = rtrim(JPATH_ROOT, '/\\') . '/images/youtube/' . $id;
        $file = is_file($base . '.webp') ? $base . '.webp' : (is_file($base . '.jpg') ? $base . '.jpg' : '');
        if ($file === '') { throw new \RuntimeException('Not found', 404); }
        $app = Factory::getApplication();
        $app->setHeader('Content-Type', str_ends_with($file, '.webp') ? 'image/webp' : 'image/jpeg');
        $app->setHeader('Cache-Control', 'public, max-age=2592000, immutable');
        $app->setHeader('X-Content-Type-Options', 'nosniff'); $app->sendHeaders();
        readfile($file); $app->close();
    }
}
