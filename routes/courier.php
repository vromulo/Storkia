<?php

use App\Http\Controllers\Courier\PickupController;
use App\Http\Controllers\Logistics\CourierController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:Logistics'])->group(function () {
    Route::prefix('logistics')->name('logistics.')->middleware('logistics.approved')->group(function () {
        Route::get('/couriers', [CourierController::class, 'index'])->name('couriers.index');
        Route::get('/couriers/create', [CourierController::class, 'create'])->name('couriers.create');
        Route::patch('/couriers/{courier}/status', [CourierController::class, 'updateStatus'])->name('couriers.update-status');
        Route::get('/couriers/{courier}/documents/{type}', [CourierController::class, 'document'])->name('couriers.document');
    });
});

Route::prefix('courier')->name('courier.')->middleware('auth:courier')->group(function () {
    Route::get('/dashboard', [PickupController::class, 'index'])->name('dashboard');
    Route::post('/pickups/{assignment}/accept', [PickupController::class, 'acceptAssignment'])->name('pickups.accept');
    Route::post('/pickups/{assignment}/verify-lookup', [PickupController::class, 'verifyPackageLookup'])->name('pickups.verify-lookup');
    Route::post('/pickups/{assignment}/confirm-pickup', [PickupController::class, 'confirmPickup'])->name('pickups.confirm-pickup');
    Route::post('/pickups/{assignment}/transport-to-hub', [PickupController::class, 'confirmTransportToHub'])->name('pickups.transport-to-hub');
    Route::post('/logout', [PickupController::class, 'logout'])->name('logout');
});