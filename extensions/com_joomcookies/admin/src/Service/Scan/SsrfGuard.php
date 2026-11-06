<?php

/**
 * @package     JoomCookies
 * @subpackage  com_joomcookies
 */

namespace JoomCookies\Component\Joomcookies\Administrator\Service\Scan;

\defined('_JEXEC') or die;

/**
 * Allows HTTP crawls only to authorised public hosts. Does not execute JavaScript.
 */
final class SsrfGuard
{
    /**
     * @var list<string>
     */
    private array $allowedHosts;

    private bool $resolveDns;

    /**
     * @param  list<string>  $allowedHosts
     */
    public function __construct(array $allowedHosts, bool $resolveDns = true)
    {
        $this->allowedHosts = array_values(array_filter(array_map(static function ($host) {
            return strtolower(preg_replace('/:\d+$/', '', (string) $host) ?? '');
        }, $allowedHosts)));
        $this->resolveDns = $resolveDns;
    }

    public function assertSafe(string $url): string
    {
        $url = trim($url);

        if ($url === '' || \strlen($url) > 2048) {
            throw new \InvalidArgumentException('URL rejected.');
        }

        $parts = parse_url($url);

        if (!\is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            throw new \InvalidArgumentException('URL rejected.');
        }

        $scheme = strtolower((string) $parts['scheme']);

        if (!\in_array($scheme, ['http', 'https'], true)) {
            throw new \InvalidArgumentException('URL rejected.');
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new \InvalidArgumentException('URL rejected.');
        }

        $host = strtolower((string) $parts['host']);

        if ($host === '' || str_contains($host, '%') || str_contains($host, '\\')) {
            throw new \InvalidArgumentException('URL rejected.');
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            if (self::isBlockedIp($host)) {
                throw new \InvalidArgumentException('URL rejected.');
            }
        } elseif (self::isBlockedHostname($host)) {
            throw new \InvalidArgumentException('URL rejected.');
        }

        if (!$this->hostAllowed($host)) {
            throw new \InvalidArgumentException('URL rejected.');
        }

        $port = isset($parts['port']) ? (int) $parts['port'] : ($scheme === 'https' ? 443 : 80);

        if (!\in_array($port, [80, 443], true)) {
            throw new \InvalidArgumentException('URL rejected.');
        }

        if ($this->resolveDns && !filter_var($host, FILTER_VALIDATE_IP)) {
            $records = @dns_get_record($host, DNS_A + DNS_AAAA);

            if (!\is_array($records) || $records === []) {
                $ips = gethostbynamel($host) ?: [];
            } else {
                $ips = [];

                foreach ($records as $record) {
                    if (!empty($record['ip'])) {
                        $ips[] = $record['ip'];
                    }

                    if (!empty($record['ipv6'])) {
                        $ips[] = $record['ipv6'];
                    }
                }
            }

            if ($ips === []) {
                throw new \InvalidArgumentException('URL rejected.');
            }

            foreach ($ips as $ip) {
                if (self::isBlockedIp((string) $ip)) {
                    throw new \InvalidArgumentException('URL rejected.');
                }
            }
        }

        $path = $parts['path'] ?? '/';
        $query = isset($parts['query']) ? '?' . $parts['query'] : '';

        return $scheme . '://' . $host . ($port !== ($scheme === 'https' ? 443 : 80) ? ':' . $port : '') . $path . $query;
    }

    public function hostAllowed(string $host): bool
    {
        $host = strtolower($host);

        foreach ($this->allowedHosts as $allowed) {
            if ($host === $allowed || $host === 'www.' . $allowed || $allowed === 'www.' . $host) {
                return true;
            }
        }

        return false;
    }

    public static function isBlockedHostname(string $host): bool
    {
        $host = strtolower($host);

        return \in_array($host, ['localhost', 'localhost.localdomain', 'metadata.google.internal'], true)
            || str_ends_with($host, '.internal')
            || str_ends_with($host, '.localhost')
            || str_ends_with($host, '.local');
    }

    public static function isBlockedIp(string $ip): bool
    {
        $ip = strtolower(trim($ip, '[]'));

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $long = ip2long($ip);

            if ($long === false) {
                return true;
            }

            $ranges = [
                ['0.0.0.0', '0.255.255.255'],
                ['10.0.0.0', '10.255.255.255'],
                ['100.64.0.0', '100.127.255.255'],
                ['127.0.0.0', '127.255.255.255'],
                ['169.254.0.0', '169.254.255.255'],
                ['172.16.0.0', '172.31.255.255'],
                ['192.0.0.0', '192.0.0.255'],
                ['192.168.0.0', '192.168.255.255'],
                ['198.18.0.0', '198.19.255.255'],
                ['224.0.0.0', '255.255.255.255'],
            ];

            foreach ($ranges as [$start, $end]) {
                $s = ip2long($start);
                $e = ip2long($end);

                if ($s !== false && $e !== false && $long >= $s && $long <= $e) {
                    return true;
                }
            }

            return false;
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $packed = @inet_pton($ip);

            if ($packed === false) {
                return true;
            }

            $unspecified = inet_pton('::');
            $loopback    = inet_pton('::1');
            $ipv4mapped  = substr($packed, 0, 12) === hex2bin('00000000000000000000ffff');

            if ($packed === $unspecified || $packed === $loopback) {
                return true;
            }

            if ($ipv4mapped) {
                $v4 = inet_ntop(substr($packed, 12));

                return $v4 !== false && self::isBlockedIp($v4);
            }

            $ord0 = \ord($packed[0]);

            return ($ord0 & 0xfe) === 0xfc || ($ord0 === 0xfe && (\ord($packed[1]) & 0xc0) === 0x80);
        }

        return true;
    }
}
