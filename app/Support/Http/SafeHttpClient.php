<?php

namespace App\Support\Http;

use Closure;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Outbound HTTP for user-supplied URLs (SECURITY-ARCHITECTURE.md, SSRF): JSON-import asset
 * downloads now, external providers later (Phase 10).
 *
 * - only http/https, default ports, no credentials in the URL
 * - the host is resolved first and every address must be public (no loopback, private,
 *   link-local, CGNAT, multicast, reserved or IPv4-mapped internal addresses)
 * - the connection is pinned to the checked address (CURLOPT_RESOLVE), so DNS cannot
 *   change between the check and the request (rebinding)
 * - redirects are followed manually (max 3) and every hop is checked again
 * - size and time limits; the caller validates the content (MediaService/UploadGuard)
 */
class SafeHttpClient
{
    private const MAX_REDIRECTS = 3;

    /** @var Closure(string): list<string> */
    private Closure $resolver;

    /**
     * @param  (Closure(string): list<string>)|null  $resolver  host → IP addresses (tests inject one)
     */
    public function __construct(?Closure $resolver = null)
    {
        $this->resolver = $resolver ?? static function (string $host): array {
            $ips = gethostbynamel($host) ?: [];
            foreach (@dns_get_record($host, DNS_AAAA) ?: [] as $record) {
                if (isset($record['ipv6'])) {
                    $ips[] = $record['ipv6'];
                }
            }

            return array_values(array_unique($ips));
        };
    }

    /**
     * Download a file to a temporary path.
     *
     * @return array{path: string, mime: string|null, name: string, size: int}
     *
     * @throws UnsafeUrlException when the URL or a redirect target is not allowed
     * @throws DownloadFailedException on network errors, HTTP errors or limits
     */
    public function download(string $url, int $maxBytes = 20 * 1024 * 1024, int $timeoutSeconds = 20): array
    {
        for ($hop = 0; $hop <= self::MAX_REDIRECTS; $hop++) {
            [$host, $port, $ip] = $this->check($url);

            try {
                $response = $this->request($url, $host, $port, $ip, $maxBytes, $timeoutSeconds);
            } catch (Throwable $e) {
                // Size-limit errors raised in Guzzle callbacks arrive wrapped.
                for ($cause = $e; $cause !== null; $cause = $cause->getPrevious()) {
                    if ($cause instanceof DownloadFailedException) {
                        throw $cause;
                    }
                }
                throw new DownloadFailedException(__('The address could not be reached.'), previous: $e);
            }

            if ($response->redirect()) {
                $location = (string) $response->header('Location');
                if ($location === '') {
                    throw new DownloadFailedException(__('The server sent an empty redirect.'));
                }
                $url = $this->absolute($location, $url);

                continue;
            }

            if (! $response->successful()) {
                throw new DownloadFailedException(__('The server answered with HTTP :status.', ['status' => $response->status()]));
            }

            $body = $response->body();
            if (strlen($body) > $maxBytes) {
                throw new DownloadFailedException(__('The file is larger than :mb MB.', ['mb' => round($maxBytes / 1048576)]));
            }

            $path = (string) tempnam(sys_get_temp_dir(), 'pacms-dl-');
            file_put_contents($path, $body);

            $name = basename((string) parse_url($url, PHP_URL_PATH)) ?: 'download';

            return [
                'path' => $path,
                'mime' => strtok((string) $response->header('Content-Type'), ';') ?: null,
                'name' => mb_substr(rawurldecode($name), 0, 200),
                'size' => strlen($body),
            ];
        }

        throw new DownloadFailedException(__('Too many redirects.'));
    }

    /**
     * Validate a URL and resolve its host to one checked public address.
     *
     * @return array{0: string, 1: int, 2: string} host, port, IP
     *
     * @throws UnsafeUrlException
     */
    public function check(string $url): array
    {
        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower(trim((string) ($parts['host'] ?? ''), '[]'));

        if (! in_array($scheme, ['http', 'https'], true) || $host === '') {
            throw new UnsafeUrlException(__('Only http:// and https:// addresses are allowed.'));
        }
        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new UnsafeUrlException(__('Addresses with a user name or password are not allowed.'));
        }

        $port = (int) ($parts['port'] ?? ($scheme === 'https' ? 443 : 80));
        if (! in_array($port, [80, 443], true)) {
            throw new UnsafeUrlException(__('Only the standard web ports are allowed.'));
        }

        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : ($this->resolver)($host);
        if ($ips === []) {
            throw new UnsafeUrlException(__('The address :host could not be found.', ['host' => $host]));
        }

        foreach ($ips as $ip) {
            if (! self::isPublicIp($ip)) {
                throw new UnsafeUrlException(__('Addresses on private or internal networks are not allowed.'));
            }
        }

        return [$host, $port, $ips[0]];
    }

    public static function isPublicIp(string $ip): bool
    {
        // IPv4-mapped IPv6 (::ffff:127.0.0.1) is checked as IPv4.
        if (preg_match('/^::ffff:(\d+\.\d+\.\d+\.\d+)$/i', $ip, $m)) {
            $ip = $m[1];
        }

        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return false;
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $long = ip2long($ip);
            foreach (['0.0.0.0/8', '100.64.0.0/10', '169.254.0.0/16', '192.0.0.0/24', '198.18.0.0/15', '224.0.0.0/4', '240.0.0.0/4'] as $cidr) {
                [$net, $bits] = explode('/', $cidr);
                $mask = -1 << (32 - (int) $bits);
                if (($long & $mask) === (ip2long($net) & $mask)) {
                    return false;
                }
            }

            return true;
        }

        // IPv6: unique-local fc00::/7, link-local fe80::/10, multicast ff00::/8, loopback/unspecified.
        $packed = (string) inet_pton($ip);

        return ! (in_array($ip, ['::1', '::'], true)
            || (ord($packed[0]) & 0xFE) === 0xFC
            || (ord($packed[0]) === 0xFE && (ord($packed[1]) & 0xC0) === 0x80)
            || ord($packed[0]) === 0xFF);
    }

    private function request(string $url, string $host, int $port, string $ip, int $maxBytes, int $timeoutSeconds): Response
    {
        $pin = str_contains($ip, ':') ? "[{$ip}]" : $ip;

        return Http::withOptions([
            'allow_redirects' => false,
            'connect_timeout' => 5,
            'timeout' => $timeoutSeconds,
            'curl' => [CURLOPT_RESOLVE => ["{$host}:{$port}:{$pin}"]],
            'on_headers' => function ($response) use ($maxBytes) {
                if ((int) $response->getHeaderLine('Content-Length') > $maxBytes) {
                    throw new DownloadFailedException(__('The file is larger than :mb MB.', ['mb' => round($maxBytes / 1048576)]));
                }
            },
            'progress' => function ($total, $downloaded) use ($maxBytes) {
                if ($downloaded > $maxBytes) {
                    throw new DownloadFailedException(__('The file is larger than :mb MB.', ['mb' => round($maxBytes / 1048576)]));
                }
            },
        ])->withHeaders(['User-Agent' => 'PACMS/1.0 (+asset import)', 'Accept' => 'image/*,application/pdf;q=0.9,*/*;q=0.1'])
            ->get($url);
    }

    private function absolute(string $location, string $base): string
    {
        if (preg_match('#^https?://#i', $location)) {
            return $location;
        }

        $parts = parse_url($base);
        $origin = ($parts['scheme'] ?? 'https').'://'.($parts['host'] ?? '').(isset($parts['port']) ? ':'.$parts['port'] : '');

        if (str_starts_with($location, '//')) {
            return ($parts['scheme'] ?? 'https').':'.$location;
        }

        if (str_starts_with($location, '/')) {
            return $origin.$location;
        }

        $dir = rtrim(dirname((string) ($parts['path'] ?? '/')), '/');

        return $origin.$dir.'/'.$location;
    }
}
