<?php

/**
 * @package     JoomCookies
 * @subpackage  com_joomcookies
 */

namespace JoomCookies\Component\Joomcookies\Administrator\Service\Scan;

\defined('_JEXEC') or die;

interface HttpTransport
{
    public function get(string $url, int $timeout, int $maxBytes): HttpResponse;
}
