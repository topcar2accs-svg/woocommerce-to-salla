<?php

use App\Integrations\WooCommerce\SafeStoreUrl;

it('rejects non https WooCommerce urls', function () {
    SafeStoreUrl::assertAllowed('http://example.com');
})->throws(InvalidArgumentException::class);

it('rejects localhost WooCommerce urls', function () {
    SafeStoreUrl::assertAllowed('https://localhost');
})->throws(InvalidArgumentException::class);
