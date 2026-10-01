<?php

use App\Domain\Products\VariantSignature;
use PHPUnit\Framework\TestCase;

final class VariantSignatureTest extends TestCase
{
    public function test_signature_is_stable_for_attribute_order(): void
    {
        $a=[['name'=>'Size','option'=>'L'],['name'=>'Color','option'=>'Black']];
        $b=array_reverse($a);
        self::assertSame(VariantSignature::make($a), VariantSignature::make($b));
    }
}
