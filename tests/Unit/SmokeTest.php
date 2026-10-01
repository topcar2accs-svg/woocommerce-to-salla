<?php

use PHPUnit\Framework\TestCase;

final class SmokeTest extends TestCase
{
    public function test_runtime_is_supported(): void
    {
        self::assertTrue(PHP_VERSION_ID >= 80200);
    }
}
