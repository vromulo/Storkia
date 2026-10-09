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
use App\Livewire\Buyer\IdentityVerificationFlow;

// Storefront & Public Routes
Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/product/{product}', [ProductController::class, 'show'])->name('product.show');
Route::get('/category/{slug}', [CategoryController::class, 'show'])->name('category.show');

// Search Routes
Route::get('/search', [SearchController::class, 'index'])->name('search.search');
Route::get('/search/suggestions', [SearchController::class, 'suggestions'])->name('search.suggestions');

// Buyer Authentication Routes
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.post');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
});

// Authenticated Actions (Requires Login)
Route::middleware('auth')->group(function () {
    
    // Cart Routes (Connected to DB & Controller)
    Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
    Route::post('/cart', [CartController::class, 'store'])->name('cart.store');
    Route::delete('/cart/{cart}', [CartController::class, 'destroy'])->name('cart.destroy');
    Route::patch('/cart/{cart}', [CartController::class, 'update'])->name('cart.update');
    
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // Buyer Account Area (RESTful /user/* routes)
    Route::prefix('user')->name('user.')->group(function () {
        Route::get('/account', function () {
            return redirect()->route('user.account-management');
        })->name('account');

        // Implemented Section
        Route::get('/account-management', [AccountManagementController::class, 'index'])->name('account-management');

        // Patch Route (routes that UPDATE user data)
        Route::patch('/account-management/name', [AccountManagementController::class, 'updateName'])->name('account-management.update-name');

        Route::post('/account-management/email/request-otp', [AccountManagementController::class, 'requestEmailOtp'])->name('account-management.request-email-otp');
        Route::post('/account-management/email/verify-otp', [AccountManagementController::class, 'verifyEmailOtp'])->name('account-management.verify-email-otp');
        

        // Future Sections inheriting personal-center via buyer.option.coming-soon
        Route::view('/profile', 'buyer.option.coming-soon', ['title' => 'Profile'])->name('profile');
        Route::get('/addresses', [AddressController::class, 'index'])->name('addresses');
        Route::view('/change-password', 'buyer.option.coming-soon', ['title' => 'Change Password'])->name('change-password');

        Route::view('/orders', 'buyer.option.coming-soon', ['title' => 'My Orders'])->name('orders');

        Route::view('/coupons', 'buyer.option.coming-soon', ['title' => 'My Coupons'])->name('coupons');
        Route::view('/points', 'buyer.option.coming-soon', ['title' => 'My Points/Coins'])->name('points');

        Route::view('/wishlist', 'buyer.option.coming-soon', ['title' => 'Wish List'])->name('wishlist');
        Route::view('/recently-viewed', 'buyer.option.coming-soon', ['title' => 'Recently Viewed'])->name('recently-viewed');
        Route::view('/following', 'buyer.option.coming-soon', ['title' => 'Following'])->name('following');
    });

    Route::get('/identity-verification', IdentityVerificationFlow::class)->name('user.identity-verification');
});

Route::get('/tunnel-test', function () {
    return response('Tunnel is working', 200)
        ->header('Content-Type', 'text/plain');
});