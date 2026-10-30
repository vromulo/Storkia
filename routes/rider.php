<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Rider\AuthController as RiderAuthController;
use App\Http\Controllers\Rider\DeliveryController;
use App\Http\Controllers\Courier\PickupController;
use App\Http\Controllers\Logistics\RiderApplicationController;

Route::prefix('rider')->name('rider.')->group(function () {
    Route::middleware(['auth', 'role:Logistics', 'logistics.approved'])->group(function () {
        Route::get('/register', [RiderAuthController::class, 'showRegister'])->name('register');
    });

    Route::get('/download-app', [RiderAuthController::class, 'downloadApp'])->name('download-app');
    Route::post('/logout', [RiderAuthController::class, 'logout'])->name('logout');

    Route::middleware('auth:rider')->group(function () {
        Route::get('/dashboard', [DeliveryController::class, 'index'])->name('dashboard');
        Route::get('/reports', [DeliveryController::class, 'report'])->name('report');
        Route::post('/deliveries/{assignment}/accept', [DeliveryController::class, 'acceptAssignment'])->name('deliveries.accept');
        Route::post('/deliveries/{assignment}/verify-lookup', [DeliveryController::class, 'verifyPackageLookup'])->name('deliveries.verify-lookup');
        Route::post('/deliveries/{assignment}/out-for-delivery', [DeliveryController::class, 'startDelivery'])->name('deliveries.out-for-delivery');
        Route::post('/deliveries/{assignment}/confirm-delivered', [DeliveryController::class, 'completeDelivery'])->name('deliveries.confirm-delivered');
        Route::post('/deliveries/{assignment}/report-failed', [DeliveryController::class, 'reportFailedDelivery'])->name('deliveries.report-failed');
        Route::post('/remittances/{codCollection}/remit', [DeliveryController::class, 'remitCod'])->name('remittances.remit');
    });
});

Route::prefix('courier')->name('courier.')->middleware('auth:courier')->group(function () {
    Route::get('/reports', [PickupController::class, 'report'])->name('report');
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