<?php

declare(strict_types=1);

namespace App\Integrations\Salla;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class SallaClient
{
    public function __construct(private readonly string $accessToken) {}

    public function createProduct(array $payload): array
    {
        return $this->request()->post('products', $payload)->throw()->json('data');
    }

    public function deleteProduct(int|string $productId): void
    {
        $response = $this->request()->delete("products/{$productId}");

        if ($response->successful() || $response->status() === 404) {
            return;
        }

        throw new RuntimeException("Unable to remove incomplete Salla product {$productId}: HTTP {$response->status()}.");
    }

    public function attachImage(int|string $productId, string $url, bool $default=false, int $sort=1, string $alt=''): array
    {
        return $this->request()->asMultipart()->post("products/{$productId}/images", [
            ['name'=>'original','contents'=>$url],
            ['name'=>'default','contents'=>$default?'1':'0'],
            ['name'=>'main','contents'=>$default?'true':'false'],
            ['name'=>'sort','contents'=>(string)$sort],
            ['name'=>'alt','contents'=>$alt],
        ])->throw()->json('data');
    }

    public function createOption(int|string $productId, array $payload): array
    {
        return $this->request()->post("products/{$productId}/options", $payload)->throw()->json('data');
    }

    public function variants(int|string $productId): array
    {
        return $this->request()->get("products/{$productId}/variants")->throw()->json('data', []);
    }

    public function updateVariant(int|string $variantId, array $payload): array
    {
        return $this->request()->put("products/variants/{$variantId}", $payload)->throw()->json('data');
    }

    public function searchCategories(string $keyword): array
    {
        return $this->request()->get('categories/search', ['keyword' => $keyword])->throw()->json('data', []);
    }

    public function createCategory(array $payload): array
    {
        return $this->request()->post('categories', $payload)->throw()->json('data');
    }

    public function product(int|string $productId): array
    {
        return $this->request()->get("products/{$productId}")->throw()->json('data');
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl(rtrim(config('services.salla.api_base'), '/').'/')
            ->withToken($this->accessToken)
            ->acceptJson()
            ->asJson()
            ->timeout(30)
            ->retry(3, 750, throw: false);
    }
}
