<?php

/**
 * @package     JoomCookies
 * @subpackage  com_joomcookies
 */

namespace JoomCookies\Component\Joomcookies\Administrator\Service\Scan;

\defined('_JEXEC') or die;

final class HttpCrawler
{
    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function run(string $startUrl, array $options, HttpTransport $transport, SsrfGuard $guard): array
    {
        $maxPages     = max(1, min(50, (int) ($options['max_pages'] ?? 10)));
        $maxDepth     = max(0, min(5, (int) ($options['max_depth'] ?? 2)));
        $timeout      = max(1, min(15, (int) ($options['timeout'] ?? 8)));
        $maxBytes     = max(10_000, min(1_000_000, (int) ($options['max_bytes'] ?? 400_000)));
        $maxSeconds   = max(5, min(90, (int) ($options['max_seconds'] ?? 25)));
        $delayMs      = max(0, min(2000, (int) ($options['delay_ms'] ?? 150)));
        $started      = microtime(true);
        $queue        = [[UrlPolicy::normalize($guard->assertSafe($startUrl)), 0]];
        $seen         = [];
        $pages        = [];
        $findings     = [];
        $errors       = [];
        $startHost    = strtolower((string) parse_url($startUrl, PHP_URL_HOST));

        $this->seedFromSitemap($startUrl, $transport, $guard, $timeout, $maxBytes, $queue, $seen, $errors);

        while ($queue !== [] && \count($pages) < $maxPages) {
            if ((microtime(true) - $started) > $maxSeconds) {
                $errors[] = 'Stopped: time limit.';
                break;
            }

            [$url, $depth] = array_shift($queue);

            if (isset($seen[$url])) {
                continue;
            }

            $seen[$url] = true;

            if ($depth > $maxDepth || !UrlPolicy::isSafeToCrawl($url)) {
                continue;
            }

            try {
                $safe = $guard->assertSafe($url);
                $response = $this->getFollowingRedirects($safe, $transport, $guard, $timeout, $maxBytes, 3);
            } catch (\Throwable $e) {
                $errors[] = 'Skip URL.';
                continue;
            }

            $pages[] = [
                'url'    => $response->url,
                'status' => $response->status,
                'bytes'  => \strlen($response->body),
            ];

            foreach (CookieHeaderParser::parse($response->headers) as $cookie) {
                $findings[] = HtmlAnalyzer::finding(
                    'cookie_header',
                    $cookie['name'],
                    $response->url,
                    $response->url,
                    null,
                    'observed',
                    [
                        'domain'  => $cookie['domain'],
                        'max_age' => $cookie['max_age'],
                        'note'    => 'Observed in Set-Cookie of this site response.',
                    ]
                );
            }

            $type = strtolower((string) ($response->headers['content-type'] ?? ''));

            if (str_contains($type, 'xml') || str_ends_with($response->url, '.xml')) {
                foreach (HtmlAnalyzer::sitemapUrls($response->body, $guard) as $loc) {
                    if (!isset($seen[$loc])) {
                        $queue[] = [$loc, $depth];
                    }
                }
            }

            if ($response->status >= 200 && $response->status < 400) {
                $findings = array_merge(
                    $findings,
                    HtmlAnalyzer::analyze($response->body, $response->url, $startHost)
                );

                if ($depth < $maxDepth) {
                    foreach (HtmlAnalyzer::internalLinks($response->body, $response->url, $guard) as $link) {
                        if (!isset($seen[$link])) {
                            $queue[] = [$link, $depth + 1];
                        }
                    }
                }
            }

            if ($delayMs > 0) {
                usleep($delayMs * 1000);
            }
        }

        return [
            'mode'     => 'http',
            'pages'    => $pages,
            'findings' => $findings,
            'errors'   => $errors,
            'elapsed'  => round(microtime(true) - $started, 3),
            'limits'   => [
                'max_pages'   => $maxPages,
                'max_depth'   => $maxDepth,
                'max_seconds' => $maxSeconds,
            ],
        ];
    }

    /**
     * @param  list<array{0: string, 1: int}>  $queue
     * @param  array<string, bool>             $seen
     * @param  list<string>                    $errors
     */
    private function seedFromSitemap(string $startUrl, HttpTransport $transport, SsrfGuard $guard, int $timeout, int $maxBytes, array &$queue, array &$seen, array &$errors): void
    {
        $origin = parse_url($startUrl, PHP_URL_SCHEME) . '://' . parse_url($startUrl, PHP_URL_HOST);
        $candidates = [$origin . '/sitemap.xml', $origin . '/robots.txt'];

        foreach ($candidates as $url) {
            try {
                $safe = $guard->assertSafe($url);
                $response = $transport->get($safe, $timeout, $maxBytes);
            } catch (\Throwable) {
                continue;
            }

            if ($response->status >= 400) {
                continue;
            }

            if (str_ends_with($url, 'robots.txt')) {
                if (preg_match_all('/^sitemap:\s*(\S+)/im', $response->body, $matches)) {
                    foreach ($matches[1] as $sitemap) {
                        try {
                            $loc = $guard->assertSafe(trim((string) $sitemap));
                            $queue[] = [UrlPolicy::normalize($loc), 0];
                        } catch (\InvalidArgumentException) {
                            $errors[] = 'Ignored off-site sitemap.';
                        }
                    }
                }
                continue;
            }

            foreach (HtmlAnalyzer::sitemapUrls($response->body, $guard) as $loc) {
                $queue[] = [$loc, 0];
            }
        }
    }

    private function getFollowingRedirects(string $url, HttpTransport $transport, SsrfGuard $guard, int $timeout, int $maxBytes, int $maxHops): HttpResponse
    {
        $current = $url;

        for ($i = 0; $i <= $maxHops; $i++) {
            $response = $transport->get($current, $timeout, $maxBytes);

            if (!\in_array($response->status, [301, 302, 303, 307, 308], true)) {
                return $response;
            }

            $location = (string) ($response->headers['location'] ?? '');

            if ($location === '') {
                return $response;
            }

            $next = HtmlAnalyzer::absolutize($location, $current);

            if ($next === null) {
                throw new \RuntimeException('Redirect rejected.');
            }

            $current = $guard->assertSafe($next);

            if (!UrlPolicy::isSafeToCrawl($current)) {
                throw new \RuntimeException('Redirect rejected.');
            }
        }

        throw new \RuntimeException('Too many redirects.');
    }
}
