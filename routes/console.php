<?php

use App\Jobs\RefreshSallaToken;
use App\Models\Merchant;
use Illuminate\Support\Facades\Schedule;

Schedule::call(function (): void {
    Merchant::query()->whereNotNull('refresh_token')->whereNotNull('token_expires_at')->where('token_expires_at','<=',now()->addDay())->pluck('id')->each(fn($id)=>RefreshSallaToken::dispatch((int)$id));
})->hourly()->withoutOverlapping();
