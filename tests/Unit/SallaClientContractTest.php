<?php

use PHPUnit\Framework\TestCase;

final class SallaClientContractTest extends TestCase
{
 public function test_salla_api_base_is_admin_v2():void{self::assertSame('https://api.salla.dev/admin/v2',rtrim('https://api.salla.dev/admin/v2','/'));}
}
