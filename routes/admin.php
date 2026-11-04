<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ApplicationController;
use App\Http\Controllers\Admin\AccountController;
use App\Http\Controllers\Admin\ComplianceController;
use App\Http\Controllers\Admin\ComplaintController;
use App\Http\Controllers\Admin\DocumentController;
use App\Http\Controllers\Admin\OrderFinanceController;
use App\Http\Controllers\Admin\PlatformSettingsController;

Route::prefix('admin')->name('admin.')->group(function () {

    Route::middleware('guest:admin')->group(function () {
        Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
        Route::post('/login', [AuthController::class, 'login'])->name('login.post');

        Route::get('/verify-2fa', [AuthController::class, 'showVerify2fa'])->name('verify-2fa');
        Route::post('/verify-2fa', [AuthController::class, 'verify2fa'])->name('verify-2fa.post');
        Route::post('/resend-2fa', [AuthController::class, 'resend2fa'])->name('resend-2fa');

        Route::get('/otp', [AuthController::class, 'showVerify2fa'])->name('otp.show');
        Route::post('/otp/verify', [AuthController::class, 'verify2fa'])->name('otp.verify');
        Route::post('/otp/resend', [AuthController::class, 'resend2fa'])->name('otp.resend');
    });

    Route::middleware('auth:admin')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

        Route::prefix('applications')->name('applications.')->group(function () {
            Route::get('/document/{entity}/{id}/{type}', [DocumentController::class, 'view'])->name('document');

            Route::get('/identity', [ApplicationController::class, 'identityIndex'])->name('identity');
            Route::patch('/identity/{verification}/approve', [ApplicationController::class, 'approveIdentity'])->name('identity.approve');
            Route::patch('/identity/{verification}/reject', [ApplicationController::class, 'rejectIdentity'])->name('identity.reject');
            Route::get('/identity/{verification}/document/{side}', [ApplicationController::class, 'viewIdentityDocument'])->name('identity.document');

            Route::get('/sellers', [ApplicationController::class, 'sellerIndex'])->name('sellers');
            Route::patch('/sellers/{application}/approve', [ApplicationController::class, 'approveSeller'])->name('sellers.approve');
            Route::patch('/sellers/{application}/reject', [ApplicationController::class, 'rejectSeller'])->name('sellers.reject');
            Route::get('/sellers/{application}/document/{type}', [ApplicationController::class, 'viewSellerDocument'])->name('sellers.document');

            Route::get('/logistics', [ApplicationController::class, 'logisticsIndex'])->name('logistics');
            Route::patch('/logistics/{application}/approve', [ApplicationController::class, 'approveLogistics'])->name('logistics.approve');
            Route::patch('/logistics/{application}/reject', [ApplicationController::class, 'rejectLogistics'])->name('logistics.reject');
            Route::get('/logistics/{application}/document/{type}', [ApplicationController::class, 'viewLogisticsDocument'])->name('logistics.document');
        });

        Route::get('/accounts', [AccountController::class, 'index'])->name('accounts.index');
        Route::post('/accounts/toggle-status', [AccountController::class, 'toggleStatus'])->name('accounts.toggle-status');
        Route::patch('/accounts/{id}/toggle-ban', [AccountController::class, 'toggleBan'])->name('accounts.toggle-ban');
        Route::post('/accounts/appeals/{appeal}/resolve', [AccountController::class, 'resolveAppeal'])->name('accounts.appeals.resolve');
        Route::post('/accounts/{appeal}/resolve-appeal', [AccountController::class, 'resolveAppeal'])->name('accounts.resolve-appeal');
        Route::match(['post', 'patch'], '/accounts/{id}/restore', [AccountController::class, 'restoreArchived'])->name('accounts.restore');

        Route::get('/compliance', [ComplianceController::class, 'index'])->name('compliance.index');
        Route::patch('/compliance/{product}/approve', [ComplianceController::class, 'approve'])->name('compliance.approve');
        Route::patch('/compliance/{product}/disapprove', [ComplianceController::class, 'disapprove'])->name('compliance.disapprove');
        Route::patch('/compliance/{product}/variants/review', [ComplianceController::class, 'reviewVariants'])->name('compliance.variants.review');

        Route::get('/complaints', [ComplaintController::class, 'index'])->name('complaints.index');
        Route::post('/complaints/{sellerOrder}/resolve', [ComplaintController::class, 'resolveDispute'])->name('complaints.resolve');
        Route::post('/complaints/rts/{claim}/resolve', [ComplaintController::class, 'resolveRtsClaim'])->name('complaints.rts.resolve');

        Route::get('/commissions', [OrderFinanceController::class, 'index'])->name('commissions.index');
        Route::post('/commissions/vouchers', [OrderFinanceController::class, 'storePlatformVoucher'])->name('commissions.vouchers.store');
        Route::patch('/commissions/vouchers/{voucher}', [OrderFinanceController::class, 'updatePlatformVoucher'])->name('commissions.vouchers.update');
        Route::post('/commissions/vouchers/{voucher}/end', [OrderFinanceController::class, 'endPlatformVoucher'])->name('commissions.vouchers.end');

        Route::post('/vouchers', [OrderFinanceController::class, 'storePlatformVoucher'])->name('vouchers.store');
        Route::post('/vouchers/{voucher}/end', [OrderFinanceController::class, 'endPlatformVoucher'])->name('vouchers.end');

        Route::post('/commissions/payouts/{id}/process', [OrderFinanceController::class, 'processSellerPayout'])->name('commissions.payouts.process');
        Route::post('/commissions/withdrawals/{id}/decide', [OrderFinanceController::class, 'decideWithdrawalRequest'])->name('commissions.withdrawals.decide');
        Route::post('/commissions/arrears/{id}/verify', [OrderFinanceController::class, 'verifyArrearsRemittance'])->name('commissions.arrears.verify');
        Route::post('/commissions/gcash-payments/{submission}/verify', [OrderFinanceController::class, 'verifyBuyerGcashPayment'])->name('commissions.gcash-payments.verify');
        Route::post('/commissions/gcash-payments/{submission}/refund', [OrderFinanceController::class, 'refundBuyerGcashPayment'])->name('commissions.gcash-payments.refund');

        Route::get('/reports/sales', [OrderFinanceController::class, 'salesReport'])->name('reports.sales');
        Route::get('/reports/formal-pdf', [PlatformSettingsController::class, 'formalPdfReport'])->name('reports.formal-pdf');

        Route::get('/audit-logs', [PlatformSettingsController::class, 'auditLogsIndex'])->name('audit-logs.index');

        Route::get('/settings', [PlatformSettingsController::class, 'index'])->name('settings.index');
        Route::post('/settings/hero-banners', [PlatformSettingsController::class, 'storeHeroBanner'])->name('settings.hero.store');
        Route::patch('/settings/hero-banners/{id}/toggle', [PlatformSettingsController::class, 'toggleHeroBanner'])->name('settings.hero.toggle');
        Route::delete('/settings/hero-banners/{id}', [PlatformSettingsController::class, 'deleteHeroBanner'])->name('settings.hero.delete');
        Route::post('/settings/announcements', [PlatformSettingsController::class, 'storeAnnouncement'])->name('settings.announcements.store');
        Route::patch('/settings/announcements/{id}/toggle', [PlatformSettingsController::class, 'toggleAnnouncement'])->name('settings.announcements.toggle');
        Route::delete('/settings/announcements/{id}', [PlatformSettingsController::class, 'deleteAnnouncement'])->name('settings.announcements.delete');
        Route::post('/settings/gcash', [PlatformSettingsController::class, 'savePlatformGcashSettings'])->name('settings.gcash.save');
        Route::delete('/settings/gcash/qr', [PlatformSettingsController::class, 'removePlatformGcashQr'])->name('settings.gcash.remove-qr');

        Route::patch('/notifications/mark-all-read', function () {
            auth('admin')->user()->unreadNotifications->markAsRead();
            return back()->with('success', 'All notifications marked as read.');
        })->name('notifications.markAllRead');

        Route::patch('/notifications/{id}/read', function ($id) {
            $notification = auth('admin')->user()->notifications()->findOrFail($id);
            $notification->markAsRead();
            return back();
        })->name('notifications.markRead');

        Route::get('/notifications/{id}/open', function ($id) {
            $notification = auth('admin')->user()->notifications()->findOrFail($id);
            $notification->markAsRead();
            $target = parse_url((string) ($notification->data['url'] ?? ''));
            if ($target && (empty($target['host']) || $target['host'] === request()->getHost())) {
                return redirect(($target['path'] ?? '/admin/dashboard') . (isset($target['query']) ? '?' . $target['query'] : ''));
            }
            return redirect()->route('admin.dashboard');
        })->name('notifications.open');
    });
});