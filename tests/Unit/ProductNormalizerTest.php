<?php

use App\Domain\Products\ProductNormalizer;
use PHPUnit\Framework\TestCase;

final class ProductNormalizerTest extends TestCase
{
    public function test_normalizes_a_simple_product(): void
    {
        $result = (new ProductNormalizer())->fromWooCommerce([
            'id'=>10,'name'=>'Test','type'=>'simple','sku'=>'ABC','regular_price'=>'99.50','sale_price'=>'','description'=>'Body','short_description'=>'Short','manage_stock'=>true,'stock_quantity'=>7,'images'=>[['src'=>'https://example.com/a.jpg']], 'categories'=>[['id'=>1,'name'=>'Cat']], 'attributes'=>[]
        ], []);
        self::assertSame('Test', $result['name']);
        self::assertSame('ABC', $result['sku']);
    }
}
