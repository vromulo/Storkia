<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Logistics\AuthController;
use App\Http\Controllers\Logistics\DashboardController;
use App\Http\Controllers\Logistics\HubSettingsController;

// Logistics Guest Authentication
Route::middleware('guest')->group(function () {
    Route::get('/logistics/login', [AuthController::class, 'showLogin'])->name('logistics.login');
    Route::post('/logistics/login', [AuthController::class, 'login'])->name('logistics.login.post');
    Route::get('/logistics/register', [AuthController::class, 'showRegister'])->name('logistics.register');
});

// Authenticated Logistics Routes (Strictly Restricted to Role: Logistics)
Route::middleware(['auth', 'role:Logistics'])->group(function () {
    // Dashboard & Status Gate
    Route::get('/logistics/dashboard', [DashboardController::class, 'index'])->name('logistics.logistics-dashboard');
    Route::get('/logistics/reapply', [DashboardController::class, 'showReapply'])->name('logistics.reapply');

    // Approved Logistics Operations
    Route::prefix('logistics')->name('logistics.')->middleware('logistics.approved')->group(function () {
        // Hub Settings & Account Management (Approved Logistics Hubs Only)
        Route::get('/hub-settings', [HubSettingsController::class, 'index'])->name('hub-settings');
        Route::post('/hub-settings/photo', [HubSettingsController::class, 'updatePhoto'])->name('hub-settings.update-photo');
        Route::delete('/hub-settings/photo', [HubSettingsController::class, 'removePhoto'])->name('hub-settings.remove-photo');
        Route::patch('/hub-settings/hub-name', [HubSettingsController::class, 'updateHubName'])->name('hub-settings.update-hub-name');
        Route::get('/hub-settings/documents/{type}', [HubSettingsController::class, 'viewDocument'])->name('hub-settings.document');
        Route::patch('/hub-settings/name', [HubSettingsController::class, 'updateName'])->name('hub-settings.update-name');
        Route::patch('/hub-settings/phone', [HubSettingsController::class, 'updatePhone'])->name('hub-settings.update-phone');
        Route::post('/hub-settings/email/request-otp', [HubSettingsController::class, 'requestEmailOtp'])->name('hub-settings.request-email-otp');
        Route::post('/hub-settings/email/verify-otp', [HubSettingsController::class, 'verifyEmailOtp'])->name('hub-settings.verify-email-otp');
        Route::post('/hub-settings/password/request-otp', [HubSettingsController::class, 'requestPasswordOtp'])->name('hub-settings.request-password-otp');
        Route::post('/hub-settings/password/verify-otp', [HubSettingsController::class, 'verifyPasswordOtp'])->name('hub-settings.verify-password-otp');
        Route::patch('/hub-settings/password', [HubSettingsController::class, 'updatePassword'])->name('hub-settings.update-password');

        // Notification Routes
        Route::patch('/notifications/mark-all-read', function () {
            auth()->user()->unreadNotifications->markAsRead();
            return back()->with('success', 'All notifications marked as read.');
        })->name('notifications.markAllRead');

        Route::patch('/notifications/{id}/read', function ($id) {
            $notification = auth()->user()->notifications()->findOrFail($id);
            $notification->markAsRead();
            return back();
        })->name('notifications.markRead');
    });
});