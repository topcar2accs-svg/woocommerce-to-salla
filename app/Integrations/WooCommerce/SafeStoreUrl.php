<?php

declare(strict_types=1);

namespace App\Integrations\WooCommerce;

use InvalidArgumentException;

final class SafeStoreUrl
{
    public static function assert(string $url): void
    {
        $parts = parse_url($url);
        if (($parts['scheme'] ?? null) !== 'https' || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
            throw new InvalidArgumentException('WooCommerce store URL must be a credential-free HTTPS URL.');
        }

        $host = strtolower(rtrim((string) $parts['host'], '.'));
        if ($host === 'localhost' || str_ends_with($host, '.local')) {
            throw new InvalidArgumentException('Local WooCommerce hosts are not allowed.');
        }

        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : self::resolve($host);
        if ($ips === []) {
            throw new InvalidArgumentException('WooCommerce host could not be resolved.');
        }

        foreach ($ips as $ip) {
            if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw new InvalidArgumentException('WooCommerce host resolves to a private or reserved network.');
            }
        }
    }

    private static function resolve(string $host): array
    {
        $records = dns_get_record($host, DNS_A | DNS_AAAA) ?: [];
        $ips = [];
        foreach ($records as $record) {
            if (isset($record['ip'])) {
                $ips[] = $record['ip'];
            }
            if (isset($record['ipv6'])) {
                $ips[] = $record['ipv6'];
            }
        }
        return array_values(array_unique($ips));
    }
}
