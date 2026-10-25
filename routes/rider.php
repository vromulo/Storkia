<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Rider\AuthController as RiderAuthController;
use App\Http\Controllers\Logistics\RiderApplicationController;

Route::prefix('rider')->name('rider.')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('/register', [RiderAuthController::class, 'showRegister'])->name('register');
    });

    Route::get('/download-app', [RiderAuthController::class, 'downloadApp'])->name('download-app');
    Route::post('/logout', [RiderAuthController::class, 'logout'])->name('logout');
});

Route::middleware(['auth', 'role:Logistics'])
    ->prefix('logistics/riders')
    ->name('logistics.rider-applications.')
    ->group(function () {
        Route::get('/applications', [RiderApplicationController::class, 'index'])->name('index');
        Route::patch('/applications/{rider}/approve', [RiderApplicationController::class, 'approve'])->name('approve');
        Route::patch('/applications/{rider}/reject', [RiderApplicationController::class, 'reject'])->name('reject');
        Route::get('/applications/{rider}/document/{type}', [RiderApplicationController::class, 'document'])->name('document');
    });