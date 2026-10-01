<?php

use App\Domain\Products\SallaProductMapper;
use PHPUnit\Framework\TestCase;

final class SallaPayloadMapperTest extends TestCase
{
    public function test_maps_sale_price_and_stock(): void
    {
        $payload=(new SallaProductMapper())->map(['name'=>'A','sku'=>'X','regular_price'=>'100.00','sale_price'=>'80.00','stock'=>['quantity'=>3]]);
        self::assertSame(80.0,$payload['price']); self::assertSame(3,$payload['quantity']); self::assertSame('product',$payload['product_type']);
    }
}
