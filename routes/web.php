<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Buyer\AuthController;
use App\Http\Controllers\Buyer\HomeController;
use App\Http\Controllers\Buyer\CategoryController;
use App\Http\Controllers\Buyer\ProductController;
use App\Http\Controllers\Buyer\SearchController;
use App\Http\Controllers\Buyer\AccountManagementController;
use App\Http\Controllers\Buyer\AddressController;
use App\Http\Controllers\Buyer\CartController;
use App\Http\Controllers\Buyer\SellerController;
use App\Livewire\Buyer\IdentityVerificationFlow;

Route::view('/', 'buyer.landing-page')->name('landing');

Route::get('/shop', [HomeController::class, 'index'])->name('home');
Route::get('/sellers', [SellerController::class, 'index'])->name('sellers.index');
Route::get('/sellers/{seller}', [SellerController::class, 'show'])->name('sellers.show');
Route::get('/product/{product}', [ProductController::class, 'show'])->name('product.show');
Route::get('/category/{slug}', [CategoryController::class, 'show'])->name('category.show');
Route::get('/search', [SearchController::class, 'index'])->name('search.search');
Route::get('/search/suggestions', [SearchController::class, 'suggestions'])->name('search.suggestions');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.post');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::middleware('role:Buyer')->group(function () {
        Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
        Route::post('/cart', [CartController::class, 'store'])->name('cart.store');
        Route::delete('/cart/{cart}', [CartController::class, 'destroy'])->name('cart.destroy');
        Route::patch('/cart/{cart}', [CartController::class, 'update'])->name('cart.update');

        Route::prefix('user')->name('user.')->group(function () {
            Route::view('/account', 'buyer.option.account.personal-center')->name('account');
            
            Route::get('/account-management', [AccountManagementController::class, 'index'])->name('account-management');
            Route::patch('/account-management/name', [AccountManagementController::class, 'updateName'])->name('account-management.update-name');
            Route::patch('/account-management/phone', [AccountManagementController::class, 'updatePhone'])->name('account-management.update-phone');
            Route::post('/account-management/email/request-otp', [AccountManagementController::class, 'requestEmailOtp'])->name('account-management.request-email-otp');
            Route::post('/account-management/email/verify-otp', [AccountManagementController::class, 'verifyEmailOtp'])->name('account-management.verify-email-otp');
            Route::post('/account-management/password/request-otp', [AccountManagementController::class, 'requestPasswordOtp'])->name('account-management.request-password-otp');
            Route::post('/account-management/password/verify-otp', [AccountManagementController::class, 'verifyPasswordOtp'])->name('account-management.verify-password-otp');
            Route::patch('/account-management/password', [AccountManagementController::class, 'updatePassword'])->name('account-management.update-password');

            Route::get('/addresses', [AddressController::class, 'index'])->name('addresses');
            Route::post('/addresses', [AddressController::class, 'store'])->name('addresses.store');
            Route::put('/addresses/{id}', [AddressController::class, 'update'])->name('addresses.update');
            Route::delete('/addresses/{id}', [AddressController::class, 'destroy'])->name('addresses.destroy');
            Route::get('/addresses/psgc/municipalities/{provinceCode}', [AddressController::class, 'getMunicipalities'])->name('addresses.psgc.municipalities');

            Route::get('/messages', [\App\Http\Controllers\Buyer\BuyerMessageController::class, 'index'])->name('messages.buyer-messages');
            Route::get('/messages/contacts', [\App\Http\Controllers\Buyer\BuyerMessageController::class, 'fetchContacts'])->name('messages.contacts');
            Route::get('/messages/fetch/{userId}', [\App\Http\Controllers\Buyer\BuyerMessageController::class, 'fetchMessages'])->name('messages.fetch');
            Route::post('/messages', [\App\Http\Controllers\Buyer\BuyerMessageController::class, 'store'])->name('messages.store');
            Route::patch('/messages/{contactId}/read', [\App\Http\Controllers\Buyer\BuyerMessageController::class, 'markAsRead'])->name('messages.read');

            Route::view('/profile', 'buyer.option.coming-soon', ['title' => 'Profile'])->name('profile');
            Route::view('/change-password', 'buyer.option.coming-soon', ['title' => 'Change Password'])->name('change-password');
            Route::view('/orders', 'buyer.option.coming-soon', ['title' => 'My Orders'])->name('orders');
            Route::view('/coupons', 'buyer.option.coming-soon', ['title' => 'My Coupons'])->name('coupons');
            Route::view('/points', 'buyer.option.coming-soon', ['title' => 'My Points/Coins'])->name('points');
            Route::view('/wishlist', 'buyer.option.coming-soon', ['title' => 'Wish List'])->name('wishlist');
            Route::view('/recently-viewed', 'buyer.option.coming-soon', ['title' => 'Recently Viewed'])->name('recently-viewed');
            Route::view('/following', 'buyer.option.coming-soon', ['title' => 'Following'])->name('following');
        });

        Route::get('/identity-verification', IdentityVerificationFlow::class)->name('user.identity-verification');

        Route::patch('/user/notifications/mark-all-read', function () {
            auth()->user()->unreadNotifications->markAsRead();
            return back();
        })->name('user.notifications.markAllRead');

        Route::patch('/user/notifications/{id}/read', function ($id) {
            auth()->user()->notifications()->findOrFail($id)->markAsRead();
            return back();
        })->name('user.notifications.markRead');
    });
});