<?php

use App\Http\Controllers\Courier\PickupController;
use App\Http\Controllers\Courier\ReturnDeliveryController;
use Illuminate\Support\Facades\Route;

Route::prefix('courier')->name('courier.')->middleware('auth:courier')->group(function () {
    Route::get('/dashboard', [PickupController::class, 'index'])->name('dashboard');
    Route::get('/ratings', [PickupController::class, 'ratings'])->name('ratings');
    Route::post('/withdrawals', [PickupController::class, 'requestWithdrawal'])->name('withdrawals.store');
    Route::post('/pickups/{assignment}/accept', [PickupController::class, 'acceptAssignment'])->name('pickups.accept');
    Route::post('/pickups/{assignment}/verify-lookup', [PickupController::class, 'verifyPackageLookup'])->name('pickups.verify-lookup');
    Route::post('/pickups/{assignment}/confirm-pickup', [PickupController::class, 'confirmPickup'])->name('pickups.confirm-pickup');
    Route::post('/pickups/{assignment}/transport-to-hub', [PickupController::class, 'confirmTransportToHub'])->name('pickups.transport-to-hub');

    // Return & Refund: carry the returned parcel from the sorting hub back to the seller
    Route::get('/returns', [ReturnDeliveryController::class, 'index'])->name('returns.index');
    Route::post('/returns/{returnShipment}/start-delivery', [ReturnDeliveryController::class, 'startDelivery'])->middleware('throttle:30,1')->name('returns.start-delivery');
    Route::post('/returns/{returnShipment}/confirm-delivered', [ReturnDeliveryController::class, 'confirmDelivered'])->middleware('throttle:30,1')->name('returns.confirm-delivered');

    Route::post('/logout', [PickupController::class, 'logout'])->name('logout');
});