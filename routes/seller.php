<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Seller\AuthController;
use App\Http\Controllers\Seller\DashboardController;
use App\Http\Controllers\Seller\ProductController;
use App\Http\Controllers\Seller\StoreSettingsController;

Route::middleware('guest')->group(function () {
    Route::get('/seller/login', [AuthController::class, 'showLogin'])->name('seller.login');
    Route::post('/seller/login', [AuthController::class, 'login'])->name('seller.login.post');
    Route::get('/seller/register', [AuthController::class, 'showRegister'])->name('seller.register');
});

// Authenticated Seller Routes (Strictly Restricted to Role: Seller)
Route::middleware(['auth', 'role:Seller'])->group(function () {
    // Dashboard & Status Gate
    Route::get('/seller/dashboard', [DashboardController::class, 'index'])->name('seller.seller-dashboard');
    Route::get('/seller/reapply', [DashboardController::class, 'showReapply'])->name('seller.reapply');

    // Approved Seller Operations
    Route::prefix('seller')->name('seller.')->middleware('seller.approved')->group(function () {
        Route::get('/products', [ProductController::class, 'index'])->name('products.index');
        Route::get('/products/create', [ProductController::class, 'create'])->name('products.create');
        Route::post('/products', [ProductController::class, 'store'])->name('products.store');
        Route::put('/products/{product}', [ProductController::class, 'update'])->name('products.update');
        Route::delete('/products/{product}/archive', [ProductController::class, 'archive'])->name('products.archive');
        Route::get('/products/inventory', [ProductController::class, 'inventory'])->name('products.inventory');
        Route::get('/products/archived', [ProductController::class, 'archived'])->name('products.archived');
        Route::patch('/products/{id}/unarchive', [ProductController::class, 'unarchive'])->name('products.unarchive');
        Route::delete('/products/{id}/force-delete', [ProductController::class, 'forceDelete'])->name('products.forceDelete');

        // Store Settings & Account Management (Approved Sellers Only)
        Route::get('/store-settings', [StoreSettingsController::class, 'index'])->name('store-settings');
        Route::post('/store-settings/photo', [StoreSettingsController::class, 'updatePhoto'])->name('store-settings.update-photo');
        Route::delete('/store-settings/photo', [StoreSettingsController::class, 'removePhoto'])->name('store-settings.remove-photo');
        Route::patch('/store-settings/store-name', [StoreSettingsController::class, 'updateStoreName'])->name('store-settings.update-store-name');
        Route::get('/store-settings/documents/{type}', [StoreSettingsController::class, 'viewDocument'])->name('store-settings.document');

        Route::patch('/store-settings/name', [StoreSettingsController::class, 'updateName'])->name('store-settings.update-name');
        Route::patch('/store-settings/phone', [StoreSettingsController::class, 'updatePhone'])->name('store-settings.update-phone');
        Route::post('/store-settings/email/request-otp', [StoreSettingsController::class, 'requestEmailOtp'])->name('store-settings.request-email-otp');
        Route::post('/store-settings/email/verify-otp', [StoreSettingsController::class, 'verifyEmailOtp'])->name('store-settings.verify-email-otp');
        Route::post('/store-settings/password/request-otp', [StoreSettingsController::class, 'requestPasswordOtp'])->name('store-settings.request-password-otp');
        Route::post('/store-settings/password/verify-otp', [StoreSettingsController::class, 'verifyPasswordOtp'])->name('store-settings.verify-password-otp');
        Route::patch('/store-settings/password', [StoreSettingsController::class, 'updatePassword'])->name('store-settings.update-password');
        
        // Advanced Real-Time Messaging Routes
        Route::get('/messages', [\App\Http\Controllers\Seller\SellerMessageController::class, 'index'])->name('messages.seller-messages');
        Route::get('/messages/contacts', [\App\Http\Controllers\Seller\SellerMessageController::class, 'fetchContacts'])->name('messages.contacts');
        Route::get('/messages/fetch/{userId}', [\App\Http\Controllers\Seller\SellerMessageController::class, 'fetchMessages'])->name('messages.fetch');
        Route::post('/messages', [\App\Http\Controllers\Seller\SellerMessageController::class, 'store'])->name('messages.store');
        Route::patch('/messages/{contactId}/read', [\App\Http\Controllers\Seller\SellerMessageController::class, 'markAsRead'])->name('messages.read');

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