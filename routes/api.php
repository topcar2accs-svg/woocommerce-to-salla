<?php

use App\Http\Controllers\SaasController;
use App\Http\Controllers\SallaWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/webhooks/salla', SallaWebhookController::class)->name('webhooks.salla');
Route::prefix('v1')->middleware(['web','salla.merchant'])->group(function () {
    Route::get('/dashboard',[SaasController::class,'dashboard']);
    Route::post('/woocommerce/connections',[SaasController::class,'connectWooCommerce']);
    Route::post('/imports',[SaasController::class,'createImport']);
    Route::get('/imports/{importId}',[SaasController::class,'showImport']);
    Route::post('/imports/{importId}/run',[SaasController::class,'importProducts']);
    Route::post('/imports/{importId}/retry-failed',[SaasController::class,'retryFailed']);
});
