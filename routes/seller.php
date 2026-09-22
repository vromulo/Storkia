<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Seller\AuthController;
use App\Http\Controllers\Seller\DashboardController;
use App\Http\Controllers\Seller\ProductController;

// Seller Guest Authentication
Route::middleware('guest')->group(function () {
    Route::get('/seller/login', [AuthController::class, 'showLogin'])->name('seller.login');
    Route::post('/seller/login', [AuthController::class, 'login'])->name('seller.login.post');
    Route::get('/seller/register', [AuthController::class, 'showRegister'])->name('seller.register');
});

// Authenticated Seller Routes
Route::middleware('auth')->group(function () {
    // Dashboard & Status Gate
    Route::get('/seller/dashboard', [DashboardController::class, 'index'])->name('seller.seller-dashboard');
    Route::get('/seller/reapply', [DashboardController::class, 'showReapply'])->name('seller.reapply');

    // Approved Seller Operations
    Route::prefix('seller')->name('seller.')->middleware('seller.approved')->group(function () {
        // Products
        Route::get('/products', [ProductController::class, 'index'])->name('products.index');
        Route::get('/products/create', [ProductController::class, 'create'])->name('products.create');
        Route::post('/products', [ProductController::class, 'store'])->name('products.store');
        Route::put('/products/{product}', [ProductController::class, 'update'])->name('products.update');
        Route::delete('/products/{product}/archive', [ProductController::class, 'archive'])->name('products.archive');

        // Inventory & Archived Catalog
        Route::get('/products/inventory', [ProductController::class, 'inventory'])->name('products.inventory');
        Route::get('/products/archived', [ProductController::class, 'archived'])->name('products.archived');
        Route::patch('/products/{id}/unarchive', [ProductController::class, 'unarchive'])->name('products.unarchive');
        Route::delete('/products/{id}/force-delete', [ProductController::class, 'forceDelete'])->name('products.forceDelete');
    });
});