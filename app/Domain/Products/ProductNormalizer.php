<?php

declare(strict_types=1);

namespace App\Domain\Products;

final class ProductNormalizer
{
    public function fromWooCommerce(array $product, array $variations = []): array
    {
        return [
            'source_id' => (int) $product['id'],
            'type' => $product['type'] ?? 'simple',
            'name' => trim((string) ($product['name'] ?? '')),
            'description' => (string) ($product['description'] ?? ''),
            'short_description' => (string) ($product['short_description'] ?? ''),
            'sku' => $this->nullableString($product['sku'] ?? null),
            'regular_price' => $this->money($product['regular_price'] ?? null),
            'sale_price' => $this->money($product['sale_price'] ?? null),
            'stock' => [
                'managed' => (bool) ($product['manage_stock'] ?? false),
                'quantity' => isset($product['stock_quantity']) ? (int) $product['stock_quantity'] : null,
                'status' => $product['stock_status'] ?? null,
            ],
            'weight' => $this->nullableString($product['weight'] ?? null),
            'categories' => array_values($product['categories'] ?? []),
            'images' => array_values($product['images'] ?? []),
            'attributes' => array_values($product['attributes'] ?? []),
            'variants' => array_map(fn (array $variant) => $this->variant($variant), $variations),
        ];
    }

    private function variant(array $variant): array
    {
        return [
            'source_id' => (int) $variant['id'],
            'sku' => $this->nullableString($variant['sku'] ?? null),
            'regular_price' => $this->money($variant['regular_price'] ?? null),
            'sale_price' => $this->money($variant['sale_price'] ?? null),
            'stock_quantity' => isset($variant['stock_quantity']) ? (int) $variant['stock_quantity'] : null,
            'weight' => $this->nullableString($variant['weight'] ?? null),
            'image' => $variant['image'] ?? null,
            'attributes' => array_values($variant['attributes'] ?? []),
        ];
    }

    private function money(mixed $value): ?string
    {
        return $value === null || $value === '' ? null : number_format((float) $value, 2, '.', '');
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));
        return $value === '' ? null : $value;
    }
}
