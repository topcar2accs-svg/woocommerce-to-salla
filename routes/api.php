<?php

use App\Http\Controllers\SallaWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/webhooks/salla', SallaWebhookController::class)
    ->name('webhooks.salla');
