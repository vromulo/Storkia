<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\DocumentController;
use App\Http\Controllers\Admin\OrderFinanceController;
use App\Livewire\Admin\IdentityVerifications;
use App\Livewire\Admin\SellerCompliance;
use App\Livewire\Admin\SellerApplications;
use App\Livewire\Admin\LogisticsApplications;

Route::prefix('admin')->name('admin.')->group(function () {
    Route::middleware('guest:admin')->group(function () {
        Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
        Route::post('/login', [AuthController::class, 'login'])->name('login.post');
        Route::get('/verify-login', [AuthController::class, 'showOtpForm'])->name('otp.form');
        Route::post('/verify-login', [AuthController::class, 'verifyOtp'])->name('otp.verify');
        Route::post('/verify-login/resend', [AuthController::class, 'resendOtp'])->name('otp.resend');
    });

    Route::middleware('auth:admin')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('/dashboard', \App\Livewire\Admin\Dashboard::class)->name('dashboard');

        Route::get('/applications/sellers', SellerApplications::class)->name('applications.sellers');
        Route::get('/applications/logistics', LogisticsApplications::class)->name('applications.logistics');
        Route::get('/applications/identity', IdentityVerifications::class)->name('applications.identity');
        
        Route::get('/applications/{entity}/{id}/documents/{type}', [DocumentController::class, 'view'])->name('applications.document');

        Route::get('/compliance', SellerCompliance::class)->name('compliance.index');

        Route::get('/commissions', [OrderFinanceController::class, 'commissions'])->name('commissions.index');
        Route::get('/complaints', [OrderFinanceController::class, 'disputes'])->name('complaints.index');
        Route::post('/complaints/{sellerOrder}/resolve', [OrderFinanceController::class, 'resolveDispute'])->name('complaints.resolve');
    });
});