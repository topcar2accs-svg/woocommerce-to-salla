<?php

use App\Integrations\WooCommerce\SafeStoreUrl;
use PHPUnit\Framework\TestCase;

final class SafeStoreUrlTest extends TestCase
{
    public function test_rejects_non_https_url(): void
    {
        $this->expectException(InvalidArgumentException::class);
        SafeStoreUrl::assert('http://example.com');
    }

    public function test_rejects_localhost_url(): void
    {
        $this->expectException(InvalidArgumentException::class);
        SafeStoreUrl::assert('https://localhost');
    }
}
