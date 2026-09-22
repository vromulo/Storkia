<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LogisticsAuthController;
use App\Http\Controllers\LogisticsController;

// Logistics Guest Authentication
Route::middleware('guest')->group(function () {
    Route::get('/logistics/login', [LogisticsAuthController::class, 'showLogin'])->name('logistics.login');
    Route::post('/logistics/login', [LogisticsAuthController::class, 'login'])->name('logistics.login.post');
    Route::get('/logistics/register', [LogisticsAuthController::class, 'showRegister'])->name('logistics.register');
});

// Authenticated Logistics Routes
Route::middleware('auth')->group(function () {
    // Dashboard & Status Gate
    Route::get('pages/logistics/logistics-dashboard', [LogisticsController::class, 'index'])->name('logistics.logistics-dashboard');
    Route::get('pages/logistics/reapply', [LogisticsController::class, 'showReapply'])->name('logistics.reapply');

    // Approved Logistics Hub Operations
    Route::prefix('logistics')->name('logistics.')->middleware('logistics.approved')->group(function () {
        // Reserved for future parcel sorting, scanning, assignment, and dispatch routes
    });
});