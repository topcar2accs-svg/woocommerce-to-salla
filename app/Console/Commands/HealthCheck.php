<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class HealthCheck extends Command
{
    protected $signature='app:health-check'; protected $description='Check production dependencies without modifying data';
    public function handle(): int
    {
        $ok=true;
        foreach (['APP_KEY'=>config('app.key'),'SALLA_CLIENT_ID'=>config('services.salla.client_id'),'SALLA_CLIENT_SECRET'=>config('services.salla.client_secret'),'SALLA_WEBHOOK_SECRET'=>config('services.salla.webhook_secret')] as $name=>$value) {
            if (!$value) { $this->error("{$name}: missing"); $ok=false; } else $this->info("{$name}: configured");
        }
        try { DB::connection()->getPdo(); $this->info('database: connected'); } catch (\Throwable $e) { $this->error('database: unavailable'); $ok=false; }
        foreach ([storage_path(),base_path('bootstrap/cache')] as $path) { if (is_writable($path)) $this->info("writable: {$path}"); else { $this->error("not writable: {$path}"); $ok=false; } }
        return $ok ? self::SUCCESS : self::FAILURE;
    }
}
