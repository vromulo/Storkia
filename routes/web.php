<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

use App\Http\Controllers\AdminAuthController;
use App\Http\Controllers\AdminDocumentController;

use App\Http\Controllers\AuthController;
use App\Http\Controllers\HomeController;

use App\Http\Controllers\SellerAuthController;
use App\Livewire\Admin\SellerApplications;
use App\Http\Controllers\SellerController;
use App\Http\Controllers\SellerProductController;

use App\Http\Controllers\LogisticsAuthController;
use App\Livewire\Admin\LogisticsApplications;
use App\Http\Controllers\LogisticsController;

use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ProductController;

Route::prefix('admin')->name('admin.')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('/login', [AdminAuthController::class, 'showLoginForm'])->name('login');
        Route::post('/login', [AdminAuthController::class, 'login']);
        Route::get('/verify-login', [AdminAuthController::class, 'showOtpForm'])->name('otp.form');
        Route::post('/verify-login', [AdminAuthController::class, 'verifyOtp'])->name('otp.verify');
        Route::post('/verify-login/resend', [AdminAuthController::class, 'resendOtp'])->name('otp.resend');
    });

    Route::middleware(['auth:admin'])->group(function () {
        Route::post('/logout', [AdminAuthController::class, 'logout'])->name('logout');
        Route::view('/dashboard', 'admin.dashboard')->name('dashboard');

        // Seller Applications Management
        Route::get('/applications/sellers', SellerApplications::class)->name('applications.sellers');

        // Logistics Applications
        Route::get('/applications/logistics', LogisticsApplications::class)->name('applications.logistics');
        
        // Unified document viewer for both seller and logistics applications
        Route::get('/applications/{entity}/{id}/documents/{type}', [AdminDocumentController::class, 'view'])->name('applications.document');
    });
});

// Buyer / Public Routes
Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/product/{product}', [ProductController::class, 'show'])->name('product.show'); // New product detail route
Route::get('/category/{slug}', [CategoryController::class, 'show'])->name('category.show');

Route::middleware('guest')->group(function () {
    // Buyer Auth
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.post');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');

    // Seller Auth
    Route::get('/seller/login', [SellerAuthController::class, 'showLogin'])->name('seller.login');
    Route::post('/seller/login', [SellerAuthController::class, 'login'])->name('seller.login.post');
    Route::get('/seller/register', [SellerAuthController::class, 'showRegister'])->name('seller.register');

    // Logistics Auth
    Route::get('/logistics/login', [LogisticsAuthController::class, 'showLogin'])->name('logistics.login');
    Route::post('/logistics/login', [LogisticsAuthController::class, 'login'])->name('logistics.login.post');
    Route::get('/logistics/register', [LogisticsAuthController::class, 'showRegister'])->name('logistics.register');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    
    // Seller
    Route::get('pages/seller/seller-dashboard', [SellerController::class, 'index'])->name('seller.seller-dashboard');
    Route::get('pages/seller/reapply', [SellerController::class, 'showReapply'])->name('seller.reapply');

    Route::prefix('seller')->name('seller.')->middleware('seller.approved')->group(function () {
        // Products
        Route::get('/products', [SellerProductController::class, 'index'])->name('products.index');
        Route::get('/products/create', [SellerProductController::class, 'create'])->name('products.create');
        Route::post('/products', [SellerProductController::class, 'store'])->name('products.store');
        Route::put('/products/{product}', [SellerProductController::class, 'update'])->name('products.update');
        Route::delete('/products/{product}/archive', [SellerProductController::class, 'archive'])->name('products.archive');
        
        // Inventory & Archived
        Route::get('/products/inventory', [SellerProductController::class, 'inventory'])->name('products.inventory');
        Route::get('/products/archived', [SellerProductController::class, 'archived'])->name('products.archived');
        Route::patch('/products/{id}/unarchive', [SellerProductController::class, 'unarchive'])->name('products.unarchive');
        Route::delete('/products/{id}/force-delete', [SellerProductController::class, 'forceDelete'])->name('products.forceDelete');
    });

    // Logistics
    Route::get('pages/logistics/logistics-dashboard', [LogisticsController::class, 'index'])->name('logistics.logistics-dashboard');
    Route::get('pages/logistics/reapply', [LogisticsController::class, 'showReapply'])->name('logistics.reapply');

    // Operations Routes (Guarded by approval)
    Route::prefix('logistics')->name('logistics.')->middleware('logistics.approved')->group(function () {
        // Protected dispatch, hub sorting, and parcel assignments routes
    });
});