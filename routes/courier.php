<?php

use App\Http\Controllers\Courier\PickupController;
use Illuminate\Support\Facades\Route;

Route::prefix('courier')->name('courier.')->middleware('auth:courier')->group(function () {
    Route::get('/dashboard', [PickupController::class, 'index'])->name('dashboard');
    Route::get('/ratings', [PickupController::class, 'ratings'])->name('ratings');
    Route::post('/withdrawals', [PickupController::class, 'requestWithdrawal'])->name('withdrawals.store');
    Route::post('/pickups/{assignment}/accept', [PickupController::class, 'acceptAssignment'])->name('pickups.accept');
    Route::post('/pickups/{assignment}/verify-lookup', [PickupController::class, 'verifyPackageLookup'])->name('pickups.verify-lookup');
    Route::post('/pickups/{assignment}/confirm-pickup', [PickupController::class, 'confirmPickup'])->name('pickups.confirm-pickup');
    Route::post('/pickups/{assignment}/transport-to-hub', [PickupController::class, 'confirmTransportToHub'])->name('pickups.transport-to-hub');
    Route::post('/logout', [PickupController::class, 'logout'])->name('logout');
});