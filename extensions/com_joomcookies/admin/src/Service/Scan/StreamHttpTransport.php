<?php

/**
 * @package     JoomCookies
 * @subpackage  com_joomcookies
 */

namespace JoomCookies\Component\Joomcookies\Administrator\Service\Scan;

\defined('_JEXEC') or die;

/**
 * Read-only GET client. Redirects are not followed here so the crawler can check each hop.
 */
final class StreamHttpTransport implements HttpTransport
{
    public function get(string $url, int $timeout, int $maxBytes): HttpResponse
    {
        $timeout  = max(1, min(20, $timeout));
        $maxBytes = max(1024, min(2_000_000, $maxBytes));
        $ctx      = stream_context_create([
            'http' => [
                'method'          => 'GET',
                'timeout'         => $timeout,
                'follow_location' => 0,
                'ignore_errors'   => true,
                'header'          => "User-Agent: JoomCookiesScanner/2.0 (read-only)\r\nAccept: text/html,application/xml;q=0.9,*/*;q=0.1\r\n",
            ],
            'ssl' => [
                'verify_peer'      => true,
                'verify_peer_name' => true,
            ],
        ]);

        $body    = @file_get_contents($url, false, $ctx, 0, $maxBytes + 1);
        $headers = [];
        $status  = 0;

        if (isset($http_response_header) && \is_array($http_response_header)) {
            foreach ($http_response_header as $line) {
                if (preg_match('#^HTTP/\S+\s+(\d+)#', $line, $m) === 1) {
                    $status = (int) $m[1];
                    continue;
                }

                $pos = strpos($line, ':');

                if ($pos === false) {
                    continue;
                }

                $name = strtolower(trim(substr($line, 0, $pos)));
                $val  = trim(substr($line, $pos + 1));

                if (!isset($headers[$name])) {
                    $headers[$name] = $val;
                } elseif ($name === 'set-cookie') {
                    $headers[$name] = array_merge((array) $headers[$name], [$val]);
                }
            }
        }

        if ($body === false) {
            throw new \RuntimeException('Fetch failed.');
        }

        if (\strlen($body) > $maxBytes) {
            $body = substr($body, 0, $maxBytes);
        }

        return new HttpResponse($status !== 0 ? $status : 200, $body, $headers, $url);
    }
}
