<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Rider\AuthController as RiderAuthController;
use App\Http\Controllers\Rider\DeliveryController;
use App\Http\Controllers\Rider\ReturnPickupController;
use App\Http\Controllers\Courier\PickupController;

Route::prefix('rider')->name('rider.')->group(function () {
    Route::middleware(['auth', 'role:Logistics', 'logistics.approved'])->group(function () {
        Route::get('/register', [RiderAuthController::class, 'showRegister'])->name('register');
    });

    Route::get('/download-app', [RiderAuthController::class, 'downloadApp'])->name('download-app');
    Route::post('/logout', [RiderAuthController::class, 'logout'])->name('logout');

    Route::middleware('auth:rider')->group(function () {
        Route::get('/dashboard', [DeliveryController::class, 'index'])->name('dashboard');
        Route::get('/reports', [DeliveryController::class, 'report'])->name('report');
        Route::get('/ratings', [DeliveryController::class, 'ratings'])->name('ratings');
        Route::post('/withdrawals', [DeliveryController::class, 'requestWithdrawal'])->name('withdrawals.store');
        Route::post('/deliveries/{assignment}/accept', [DeliveryController::class, 'acceptAssignment'])->name('deliveries.accept');
        Route::post('/deliveries/{assignment}/verify-lookup', [DeliveryController::class, 'verifyPackageLookup'])->name('deliveries.verify-lookup');
        Route::post('/deliveries/{assignment}/out-for-delivery', [DeliveryController::class, 'startDelivery'])->name('deliveries.out-for-delivery');
        Route::post('/deliveries/{assignment}/confirm-delivered', [DeliveryController::class, 'completeDelivery'])->name('deliveries.confirm-delivered');
        Route::post('/deliveries/{assignment}/report-failed', [DeliveryController::class, 'reportFailedDelivery'])->name('deliveries.report-failed');
        Route::post('/remittances', [DeliveryController::class, 'remitAllCod'])->name('remittances.store');
        Route::post('/remittances/{codCollection}/remit', [DeliveryController::class, 'remitCod'])->name('remittances.remit');

        // Return & Refund: collect the parcel from the buyer and bring it to the sorting hub
        Route::get('/returns', [ReturnPickupController::class, 'index'])->name('returns.index');
        Route::post('/returns/{returnShipment}/confirm-pickup', [ReturnPickupController::class, 'confirmPickup'])->middleware('throttle:30,1')->name('returns.confirm-pickup');
        Route::post('/returns/{returnShipment}/report-failed', [ReturnPickupController::class, 'reportFailed'])->middleware('throttle:30,1')->name('returns.report-failed');
    });
});

Route::prefix('courier')->name('courier.')->middleware('auth:courier')->group(function () {
    Route::get('/reports', [PickupController::class, 'report'])->name('report');
});