<?php

use App\Http\Controllers\SallaOAuthController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'dashboard');
Route::get('/health', fn () => response()->json(['app'=>'woocommerce-to-salla','status'=>'ok','time'=>now()->toIso8601String()]));
Route::get('/auth/salla', [SallaOAuthController::class,'redirect'])->name('salla.oauth.redirect');
Route::get('/auth/salla/callback', [SallaOAuthController::class,'callback'])->name('salla.oauth.callback');
