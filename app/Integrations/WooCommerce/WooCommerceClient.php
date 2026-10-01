<?php

declare(strict_types=1);

namespace App\Integrations\WooCommerce;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

final class WooCommerceClient
{
    public function __construct(
        private readonly string $storeUrl,
        private readonly string $consumerKey,
        private readonly string $consumerSecret,
    ) {
        SafeStoreUrl::assert($storeUrl);
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
        // Re-resolve immediately before each outbound request to reduce DNS-rebinding exposure.
        SafeStoreUrl::assert($this->storeUrl);
        $base = rtrim($this->storeUrl, '/').'/wp-json/'.config('services.woocommerce.version', 'wc/v3').'/';

        return Http::baseUrl($base)
            ->withBasicAuth($this->consumerKey, $this->consumerSecret)
            ->acceptJson()
            ->timeout((int) config('services.woocommerce.timeout', 30))
            ->withOptions(['allow_redirects' => false])
            ->retry(3, 500, throw: false);
    }
}
