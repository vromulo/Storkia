<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Logistics\AuthController;
use App\Http\Controllers\Logistics\DashboardController;

// Logistics Guest Authentication
Route::middleware('guest')->group(function () {
    Route::get('/logistics/login', [AuthController::class, 'showLogin'])->name('logistics.login');
    Route::post('/logistics/login', [AuthController::class, 'login'])->name('logistics.login.post');
    Route::get('/logistics/register', [AuthController::class, 'showRegister'])->name('logistics.register');
});

// Authenticated Logistics Routes
Route::middleware('auth')->group(function () {
    // Dashboard & Status Gate
    Route::get('/logistics/dashboard', [DashboardController::class, 'index'])->name('logistics.logistics-dashboard');
    Route::get('/logistics/reapply', [DashboardController::class, 'showReapply'])->name('logistics.reapply');

    // Approved Logistics Operations
    Route::prefix('logistics')->name('logistics.')->middleware('logistics.approved')->group(function () {
        // Reserved for parcel sorting, scanning, assignment, and dispatch routes
    });
});