<?php

declare(strict_types=1);

namespace App\Domain\Products;

final class SallaProductMapper
{
    public function map(array $n): array
    {
        $price=$n['sale_price'] ?? $n['regular_price'] ?? null;
        $quantity=$n['stock']['quantity'] ?? null;
        return array_filter([
            'name'=>$n['name'] ?? null,'sku'=>$n['sku'] ?? null,'description'=>$n['description'] ?? null,
            'price'=>$price !== null ? (float)$price : null,'quantity'=>$quantity !== null ? (int)$quantity : null,'product_type'=>'product',
        ],static fn($v)=>$v!==null&&$v!=='');
    }
}
