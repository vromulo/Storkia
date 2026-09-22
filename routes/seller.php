<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SellerAuthController;
use App\Http\Controllers\SellerController;
use App\Http\Controllers\SellerProductController;

// Seller Guest Authentication
Route::middleware('guest')->group(function () {
    Route::get('/seller/login', [SellerAuthController::class, 'showLogin'])->name('seller.login');
    Route::post('/seller/login', [SellerAuthController::class, 'login'])->name('seller.login.post');
    Route::get('/seller/register', [SellerAuthController::class, 'showRegister'])->name('seller.register');
});

// Authenticated Seller Routes
Route::middleware('auth')->group(function () {
    // Dashboard & Status Gate
    Route::get('pages/seller/seller-dashboard', [SellerController::class, 'index'])->name('seller.seller-dashboard');
    Route::get('pages/seller/reapply', [SellerController::class, 'showReapply'])->name('seller.reapply');

    // Approved Seller Operations
    Route::prefix('seller')->name('seller.')->middleware('seller.approved')->group(function () {
        // Products
        Route::get('/products', [SellerProductController::class, 'index'])->name('products.index');
        Route::get('/products/create', [SellerProductController::class, 'create'])->name('products.create');
        Route::post('/products', [SellerProductController::class, 'store'])->name('products.store');
        Route::put('/products/{product}', [SellerProductController::class, 'update'])->name('products.update');
        Route::delete('/products/{product}/archive', [SellerProductController::class, 'archive'])->name('products.archive');

        // Inventory & Archived Catalog
        Route::get('/products/inventory', [SellerProductController::class, 'inventory'])->name('products.inventory');
        Route::get('/products/archived', [SellerProductController::class, 'archived'])->name('products.archived');
        Route::patch('/products/{id}/unarchive', [SellerProductController::class, 'unarchive'])->name('products.unarchive');
        Route::delete('/products/{id}/force-delete', [SellerProductController::class, 'forceDelete'])->name('products.forceDelete');
    });
});