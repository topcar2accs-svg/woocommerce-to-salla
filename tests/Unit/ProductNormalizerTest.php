<?php

use App\Domain\Products\ProductNormalizer;

test('normalizes a simple WooCommerce product', function () {
    $result = (new ProductNormalizer())->fromWooCommerce([
        'id'=>10,'name'=>'Test','type'=>'simple','sku'=>'ABC','regular_price'=>'99.50','sale_price'=>'','description'=>'Body','short_description'=>'Short','manage_stock'=>true,'stock_quantity'=>7,'images'=>[['src'=>'https://example.com/a.jpg']], 'categories'=>[['id'=>1,'name'=>'Cat']], 'attributes'=>[]
    ], []);
    expect($result['name'])->toBe('Test')->and($result['sku'])->toBe('ABC');
});
