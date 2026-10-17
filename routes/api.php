<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Models\Product;

Route::prefix('v1')->group(function () {
    Route::get('/products', function () {
        return response()->json([
            'status' => 'success',
            'data' => Product::all(),
        ]);
    });

    Route::post('/cart/add', function () {
        return response()->json([
            'status' => 'success',
            'message' => 'Added to cart',
        ], 200);
    });
});