<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\DocumentController;
use App\Http\Controllers\Admin\OrderFinanceController;
use App\Http\Controllers\Admin\PlatformSettingsController;
use App\Livewire\Admin\IdentityVerifications;
use App\Livewire\Admin\SellerCompliance;
use App\Livewire\Admin\SellerApplications;
use App\Livewire\Admin\LogisticsApplications;

Route::prefix('admin')->name('admin.')->middleware('throttle:120,1')->group(function () {
    Route::middleware(['guest:admin', 'throttle:30,1'])->group(function () {
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
        Route::post('/vouchers', [OrderFinanceController::class, 'storePlatformVoucher'])->name('vouchers.store');
        Route::post('/vouchers/{voucher}/end', [OrderFinanceController::class, 'endPlatformVoucher'])->name('vouchers.end');

        Route::get('/reports/sales', [OrderFinanceController::class, 'salesReport'])->name('reports.sales');
        Route::get('/reports/formal-pdf', [PlatformSettingsController::class, 'formalSalesReport'])->name('reports.formal-pdf');

        Route::get('/accounts', [OrderFinanceController::class, 'accountsIndex'])->name('accounts.index');
        Route::post('/accounts/toggle-status', [OrderFinanceController::class, 'toggleAccountStatus'])->name('accounts.toggle-status');
        Route::post('/accounts/appeals/{appeal}/resolve', [OrderFinanceController::class, 'resolveBuyerAppeal'])->name('accounts.resolve-appeal');

        Route::get('/audit-logs', [PlatformSettingsController::class, 'auditLogs'])->name('audit-logs.index');

        Route::get('/platform-settings', [PlatformSettingsController::class, 'index'])->name('settings.index');
        Route::post('/platform-settings/hero-banners', [PlatformSettingsController::class, 'storeHeroBanner'])->name('settings.hero-banners.store');
        Route::patch('/platform-settings/hero-banners/{id}/toggle', [PlatformSettingsController::class, 'toggleHeroBanner'])->name('settings.hero-banners.toggle');
        Route::delete('/platform-settings/hero-banners/{id}', [PlatformSettingsController::class, 'destroyHeroBanner'])->name('settings.hero-banners.destroy');
        Route::post('/platform-settings/announcements', [PlatformSettingsController::class, 'storeAnnouncement'])->name('settings.announcements.store');
        Route::patch('/platform-settings/announcements/{id}/toggle', [PlatformSettingsController::class, 'toggleAnnouncement'])->name('settings.announcements.toggle');

        Route::get('/complaints', [OrderFinanceController::class, 'disputes'])->name('complaints.index');
        Route::post('/complaints/{sellerOrder}/resolve', [OrderFinanceController::class, 'resolveDispute'])->name('complaints.resolve');
    });
});