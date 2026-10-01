<?php

use App\Domain\Products\SallaProductMapper;
use PHPUnit\Framework\TestCase;

final class SallaPayloadMapperTest extends TestCase
{
    public function test_maps_sale_price_stock_weight_and_images(): void
    {
        $payload=(new SallaProductMapper())->map(['name'=>'A','sku'=>'X','regular_price'=>'100.00','sale_price'=>'80.00','stock'=>['quantity'=>3],'weight'=>'1.5','images'=>[['src'=>'https://example.test/a.jpg','alt'=>'A','position'=>0]]]);
        self::assertSame(80.0,$payload['price']); self::assertSame(3,$payload['quantity']); self::assertSame(1.5,$payload['weight']); self::assertSame('product',$payload['product_type']); self::assertSame('https://example.test/a.jpg',$payload['images'][0]['original']); self::assertTrue($payload['images'][0]['default']);
    }

    public function test_maps_only_variation_attributes_to_options(): void
    {
        $options=(new SallaProductMapper())->options(['attributes'=>[
            ['name'=>'Color','variation'=>true,'options'=>['Red','Blue']],
            ['name'=>'Brand','variation'=>false,'options'=>['Acme']],
        ]]);
        self::assertCount(1,$options); self::assertSame('Color',$options[0]['name']); self::assertSame([['name'=>'Red'],['name'=>'Blue']],$options[0]['values']);
    }
}
