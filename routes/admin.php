<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\DocumentController;
use App\Livewire\Admin\SellerApplications;
use App\Livewire\Admin\LogisticsApplications;

Route::prefix('admin')->name('admin.')->group(function () {
    // Admin Guest Routes
    Route::middleware('guest:admin')->group(function () {
        Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
        Route::post('/login', [AuthController::class, 'login'])->name('login.post');
        Route::get('/verify-login', [AuthController::class, 'showOtpForm'])->name('otp.form');
        Route::post('/verify-login', [AuthController::class, 'verifyOtp'])->name('otp.verify');
        Route::post('/verify-login/resend', [AuthController::class, 'resendOtp'])->name('otp.resend');
    });

    // Admin Authenticated Routes
    Route::middleware('auth:admin')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
        Route::view('/dashboard', 'admin.dashboard')->name('dashboard');

        // Applications Management
        Route::get('/applications/sellers', SellerApplications::class)->name('applications.sellers');
        Route::get('/applications/logistics', LogisticsApplications::class)->name('applications.logistics');

        // Document Inspector
        Route::get('/applications/{entity}/{id}/documents/{type}', [DocumentController::class, 'view'])->name('applications.document');
    });
});