<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\ApiMessageController;

Route::prefix('v1')->group(function () {
    Route::post('/send-otp', [AuthController::class, 'sendOtp']);
    Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);
    
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/user', function (Request $request) {
            return response()->json([
                'status' => 'success',
                'data' => $request->user(),
            ]);
        });
        
        Route::get('/products', [ProductController::class, 'index']);

        Route::get('/cart', [CartController::class, 'index']);
        Route::post('/cart', [CartController::class, 'store']);
        Route::put('/cart/{id}', [CartController::class, 'update']);
        Route::delete('/cart/{id}', [CartController::class, 'destroy']);

        Route::get('/messages/contacts', [ApiMessageController::class, 'fetchContacts']);
        Route::get('/messages/unread-count', [ApiMessageController::class, 'unreadCount']);
        Route::get('/messages/fetch/{contactId}', [ApiMessageController::class, 'fetchMessages']);
        Route::post('/messages', [ApiMessageController::class, 'store']);
        Route::patch('/messages/{contactId}/read', [ApiMessageController::class, 'markAsRead']);
    });
});