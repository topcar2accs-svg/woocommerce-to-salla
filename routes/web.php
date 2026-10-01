<?php

use App\Http\Controllers\SallaOAuthController;
use App\Http\Controllers\WooCommerceAuthController;
use App\Support\ProductionReadiness;
use Illuminate\Support\Facades\Route;

Route::view('/','dashboard');

Route::get('/health/live',fn()=>response()->json([
    'app'=>'woocommerce-to-salla',
    'status'=>'ok',
    'time'=>now()->toIso8601String(),
]));

Route::get('/health/ready',function(ProductionReadiness $readiness){
    $checks=$readiness->checks();
    $ready=!collect($checks)->contains(fn(array $check): bool => !$check['ok']);

    return response()->json([
        'app'=>'woocommerce-to-salla',
        'status'=>$ready?'ready':'not_ready',
        'checks'=>$checks,
        'time'=>now()->toIso8601String(),
    ],$ready?200:503);
});

Route::get('/health',fn()=>redirect('/health/live'));
Route::get('/auth/salla',[SallaOAuthController::class,'redirect'])->name('salla.oauth.redirect');
Route::get('/auth/salla/callback',[SallaOAuthController::class,'callback'])->name('salla.oauth.callback');
Route::post('/auth/woocommerce',[WooCommerceAuthController::class,'start'])->middleware('salla.merchant')->name('woo.auth.start');
Route::post('/auth/woocommerce/callback',[WooCommerceAuthController::class,'callback'])->name('woo.auth.callback');
