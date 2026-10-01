<?php

use Illuminate\Support\Facades\Route;

Route::get('/', fn () => response()->json([
    'app' => 'WooCommerce to Salla',
    'status' => 'ok',
]));
