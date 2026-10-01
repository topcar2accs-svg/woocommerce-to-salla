<?php

use App\Models\Merchant;
use App\Models\WooCommerceConnection;
use PHPUnit\Framework\TestCase;

final class EncryptionBoundaryTest extends TestCase
{
    public function test_sensitive_models_define_encrypted_casts(): void
    {
        $merchant=new Merchant(); $woo=new WooCommerceConnection();
        $m=(new ReflectionClass($merchant))->getMethod('casts')->invoke($merchant); $w=(new ReflectionClass($woo))->getMethod('casts')->invoke($woo);
        self::assertSame('encrypted',$m['access_token']); self::assertSame('encrypted',$m['refresh_token']); self::assertSame('encrypted',$w['consumer_key']); self::assertSame('encrypted',$w['consumer_secret']);
    }
}
