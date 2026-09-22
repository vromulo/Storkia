<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AdminAuthController;
use App\Http\Controllers\AdminDocumentController;
use App\Livewire\Admin\SellerApplications;
use App\Livewire\Admin\LogisticsApplications;

Route::prefix('admin')->name('admin.')->group(function () {
    // Admin Guest Routes
    Route::middleware('guest:admin')->group(function () {
        Route::get('/login', [AdminAuthController::class, 'showLoginForm'])->name('login');
        Route::post('/login', [AdminAuthController::class, 'login'])->name('login.post');
        Route::get('/verify-login', [AdminAuthController::class, 'showOtpForm'])->name('otp.form');
        Route::post('/verify-login', [AdminAuthController::class, 'verifyOtp'])->name('otp.verify');
        Route::post('/verify-login/resend', [AdminAuthController::class, 'resendOtp'])->name('otp.resend');
    });

    // Admin Authenticated Routes
    Route::middleware('auth:admin')->group(function () {
        Route::post('/logout', [AdminAuthController::class, 'logout'])->name('logout');
        Route::view('/dashboard', 'admin.dashboard')->name('dashboard');

        // Seller Applications Management
        Route::get('/applications/sellers', SellerApplications::class)->name('applications.sellers');

        // Logistics Applications Management
        Route::get('/applications/logistics', LogisticsApplications::class)->name('applications.logistics');

        // Document Inspector
        Route::get('/applications/{entity}/{id}/documents/{type}', [AdminDocumentController::class, 'view'])->name('applications.document');
    });
});