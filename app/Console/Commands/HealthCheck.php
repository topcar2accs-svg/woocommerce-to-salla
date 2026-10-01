<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\ProductionReadiness;
use Illuminate\Console\Command;

final class HealthCheck extends Command
{
    protected $signature='app:health-check';
    protected $description='Check production dependencies without modifying data';

    public function handle(ProductionReadiness $readiness): int
    {
        $checks=$readiness->checks();

        foreach($checks as $name=>$check){
            $line=$name.': '.$check['detail'];
            $check['ok'] ? $this->info($line) : $this->error($line);
        }

        return $readiness->ready() ? self::SUCCESS : self::FAILURE;
    }
}
