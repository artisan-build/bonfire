<?php

declare(strict_types=1);

namespace ArtisanBuild\Bonfire\Support;

/**
 * Validates outbound URLs to defend against SSRF. A URL is only considered safe
 * when it uses http(s), has a resolvable public host, and every IP the host
 * resolves to is a public, routable address (no loopback, private, link-local,
 * or cloud-metadata ranges).
 */
class SafeUrlGuard
{
    /**
     * Decide whether the given URL is safe to fetch.
     */
    public function isSafe(string $url): bool
    {
        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            return false;
        }

        $scheme = strtolower($parts['scheme']);

        if ($scheme !== 'http' && $scheme !== 'https') {
            return false;
        }

        // Reject embedded credentials (user:pass@host) — a common SSRF bypass.
        if (isset($parts['user']) || isset($parts['pass'])) {
            return false;
        }

        return $this->hostResolvesToPublicIps($parts['host']);
    }

    /**
     * Resolve a host and confirm every resulting IP is public/routable.
     */
    public function hostResolvesToPublicIps(string $host): bool
    {
        $host = trim($host, '[]');

        // A literal IP must itself be public.
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return $this->isPublicIp($host);
        }

        $ips = $this->resolveHost($host);

        if ($ips === []) {
            return false;
        }

        foreach ($ips as $ip) {
            if (! $this->isPublicIp($ip)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<string>
     */
    private function resolveHost(string $host): array
    {
        $ips = [];

        $records = @dns_get_record($host, DNS_A | DNS_AAAA);

        if (is_array($records)) {
            foreach ($records as $record) {
                if (isset($record['ip'])) {
                    $ips[] = (string) $record['ip'];
                } elseif (isset($record['ipv6'])) {
                    $ips[] = (string) $record['ipv6'];
                }
            }
        }

        if ($ips === []) {
            $resolved = gethostbynamel($host);

            if (is_array($resolved)) {
                $ips = array_map('strval', $resolved);
            }
        }

        return array_values(array_unique($ips));
    }

    private function isPublicIp(string $ip): bool
    {
        // filter_var rejects private and reserved ranges (loopback, link-local,
        // RFC1918, RFC4193, etc.) when these flags are set.
        if (filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        ) === false) {
            return false;
        }

        // Explicitly block the cloud metadata endpoint and IPv6 forms of it,
        // which are not all covered by the reserved-range filter.
        $blocked = [
            '169.254.169.254', // AWS/GCP/Azure IMDS
            '100.100.100.200', // Alibaba Cloud metadata
            'fd00:ec2::254',   // AWS IMDSv2 over IPv6
        ];

        return ! in_array(strtolower($ip), $blocked, true);
    }
}
