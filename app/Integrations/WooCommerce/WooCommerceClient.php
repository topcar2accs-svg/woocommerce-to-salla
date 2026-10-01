<?php

declare(strict_types=1);

namespace App\Integrations\WooCommerce;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

final class WooCommerceClient
{
    public function __construct(
        private readonly string $storeUrl,
        private readonly string $consumerKey,
        private readonly string $consumerSecret,
    ) {
        $this->assertSafeStoreUrl($storeUrl);
    }

    public function testConnection(): array
    {
        return $this->request()->get('products', ['per_page' => 1])->throw()->json();
    }

    public function products(int $page = 1, int $perPage = 100): array
    {
        return $this->request()->get('products', ['page' => $page, 'per_page' => min($perPage, 100)])->throw()->json();
    }

    public function variations(int $productId, int $page = 1, int $perPage = 100): array
    {
        return $this->request()->get("products/{$productId}/variations", ['page' => $page, 'per_page' => min($perPage, 100)])->throw()->json();
    }

    public function categories(int $page = 1, int $perPage = 100): array
    {
        return $this->request()->get('products/categories', ['page' => $page, 'per_page' => min($perPage, 100)])->throw()->json();
    }

    private function request(): PendingRequest
    {
        $base = rtrim($this->storeUrl, '/').'/wp-json/'.config('services.woocommerce.version', 'wc/v3').'/';

        return Http::baseUrl($base)
            ->withBasicAuth($this->consumerKey, $this->consumerSecret)
            ->acceptJson()
            ->timeout((int) config('services.woocommerce.timeout', 30))
            ->retry(3, 500, throw: false);
    }

    private function assertSafeStoreUrl(string $url): void
    {
        $parts = parse_url($url);
        if (($parts['scheme'] ?? null) !== 'https' || empty($parts['host'])) {
            throw new InvalidArgumentException('WooCommerce store URL must be a valid HTTPS URL.');
        }

        $host = strtolower($parts['host']);
        if ($host === 'localhost' || str_ends_with($host, '.local')) {
            throw new InvalidArgumentException('Local WooCommerce hosts are not allowed.');
        }

        // Production must additionally resolve DNS and reject private/link-local IPs after
        // resolution and after every redirect to prevent SSRF/DNS-rebinding attacks.
    }
}
