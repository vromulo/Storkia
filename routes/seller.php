<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Seller\AuthController;
use App\Http\Controllers\Seller\DashboardController;
use App\Http\Controllers\Seller\OrderController;
use App\Http\Controllers\Seller\ProductController;
use App\Http\Controllers\Seller\StoreSettingsController;
use App\Http\Controllers\Seller\VoucherController;

Route::middleware('guest')->group(function () {
    Route::get('/seller/login', [AuthController::class, 'showLogin'])->name('seller.login');
    Route::post('/seller/login', [AuthController::class, 'login'])->name('seller.login.post');
    Route::get('/seller/register', [AuthController::class, 'showRegister'])->name('seller.register');
});

Route::middleware(['auth', 'role:Seller'])->group(function () {
    Route::get('/seller/dashboard', [DashboardController::class, 'index'])->name('seller.seller-dashboard');
    Route::get('/seller/reapply', [DashboardController::class, 'showReapply'])->name('seller.reapply');

    Route::prefix('seller')->name('seller.')->middleware('seller.approved')->group(function () {
        Route::get('/products', [ProductController::class, 'index'])->name('products.index');
        Route::post('/products/categories', [ProductController::class, 'storeCategory'])->name('products.categories.store');
        Route::delete('/products/categories/{sellerCategory}', [ProductController::class, 'destroyCategory'])->name('products.categories.destroy');
        Route::get('/products/create', [ProductController::class, 'create'])->name('products.create');
        Route::post('/products', [ProductController::class, 'store'])->name('products.store');
        Route::put('/products/{product}', [ProductController::class, 'update'])->name('products.update');
        Route::delete('/products/{product}/archive', [ProductController::class, 'archive'])->name('products.archive');
        Route::get('/products/inventory', [ProductController::class, 'inventory'])->name('products.inventory');
        Route::get('/products/archived', [ProductController::class, 'archived'])->name('products.archived');
        Route::patch('/products/{id}/unarchive', [ProductController::class, 'unarchive'])->name('products.unarchive');
        Route::delete('/products/{id}/force-delete', [ProductController::class, 'forceDelete'])->name('products.forceDelete');

        Route::get('/vouchers', [VoucherController::class, 'index'])->name('vouchers.index');
        Route::post('/vouchers', [VoucherController::class, 'store'])->name('vouchers.store');
        Route::patch('/vouchers/{voucher}', [VoucherController::class, 'update'])->name('vouchers.update');
        Route::post('/vouchers/{voucher}/end', [VoucherController::class, 'endNow'])->name('vouchers.end');

        Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
        Route::get('/orders/{sellerOrder}', [OrderController::class, 'show'])->name('orders.show');
        Route::post('/orders/{sellerOrder}/accept', [OrderController::class, 'accept'])->name('orders.accept');
        Route::post('/orders/{sellerOrder}/reject', [OrderController::class, 'reject'])->name('orders.reject');
        Route::post('/orders/{sellerOrder}/ready-for-pickup', [OrderController::class, 'markReadyForPickup'])->name('orders.ready-for-pickup');
        Route::post('/orders/{sellerOrder}/report-suspicious-payment', [OrderController::class, 'reportSuspiciousPayment'])->name('orders.report-suspicious-payment');
        Route::post('/orders/{sellerOrder}/verify-online-payment', [OrderController::class, 'verifyOnlinePayment'])->name('orders.verify-online-payment');
        Route::post('/orders/{sellerOrder}/respond-return', [OrderController::class, 'respondToReturnRequest'])->name('orders.respond-return');
        Route::get('/orders/{sellerOrder}/shipping-label', [OrderController::class, 'shippingLabel'])->name('orders.shipping-label');
        Route::get('/orders/{sellerOrder}/packing-slip', [OrderController::class, 'packingSlip'])->name('orders.packing-slip');
        Route::get('/sales/settlements', [OrderController::class, 'settlements'])->name('settlements.index');

        Route::get('/store-settings', [StoreSettingsController::class, 'index'])->name('store-settings');
        Route::post('/store-settings/gcash', [StoreSettingsController::class, 'updateGcashAccount'])->name('store-settings.update-gcash');
        Route::delete('/store-settings/gcash/qr', [StoreSettingsController::class, 'removeGcashQr'])->name('store-settings.remove-gcash-qr');
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

        Route::get('/messages', [\App\Http\Controllers\Seller\SellerMessageController::class, 'index'])->name('messages.seller-messages');
        Route::get('/messages/contacts', [\App\Http\Controllers\Seller\SellerMessageController::class, 'fetchContacts'])->name('messages.contacts');
        Route::get('/messages/fetch/{userId}', [\App\Http\Controllers\Seller\SellerMessageController::class, 'fetchMessages'])->name('messages.fetch');
        Route::post('/messages', [\App\Http\Controllers\Seller\SellerMessageController::class, 'store'])->name('messages.store');
        Route::patch('/messages/{contactId}/read', [\App\Http\Controllers\Seller\SellerMessageController::class, 'markAsRead'])->name('messages.read');

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