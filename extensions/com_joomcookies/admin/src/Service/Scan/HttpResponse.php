<?php

/**
 * @package     JoomCookies
 * @subpackage  com_joomcookies
 */

namespace JoomCookies\Component\Joomcookies\Administrator\Service\Scan;

\defined('_JEXEC') or die;

final class HttpResponse
{
    public int $status;

    public string $body;

    /**
     * @var array<string, mixed>
     */
    public array $headers;

    public string $url;

    /**
     * @param  array<string, mixed>  $headers
     */
    public function __construct(int $status, string $body, array $headers, string $url)
    {
        $this->status  = $status;
        $this->body    = $body;
        $this->headers = $headers;
        $this->url     = $url;
    }
}
