<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'dashboard');
Route::get('/health', fn () => response()->json(['app'=>'woocommerce-to-salla','status'=>'ok','time'=>now()->toIso8601String()]));
