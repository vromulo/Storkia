<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Logistics\AuthController;
use App\Http\Controllers\Logistics\DashboardController;
use App\Http\Controllers\Logistics\FinanceController;
use App\Http\Controllers\Logistics\HubSettingsController;
use App\Http\Controllers\Logistics\ShipmentController;
use App\Http\Controllers\Logistics\CourierController;
use App\Http\Controllers\Logistics\RiderApplicationController;
use App\Http\Controllers\Logistics\BranchHubController;
use App\Http\Controllers\Logistics\EmployeeManagementController;

Route::middleware('guest')->group(function () {
    Route::get('/logistics/login', [AuthController::class, 'showLogin'])->name('logistics.login');
    Route::post('/logistics/login', [AuthController::class, 'login'])->name('logistics.login.post');
    Route::get('/logistics/register', [AuthController::class, 'showRegister'])->name('logistics.register');
});

Route::middleware(['auth', 'role:Logistics'])->group(function () {
    Route::get('/logistics/dashboard', [DashboardController::class, 'index'])->name('logistics.logistics-dashboard');
    Route::get('/logistics/reapply', [DashboardController::class, 'showReapply'])->name('logistics.reapply');

    Route::prefix('logistics')->name('logistics.')->middleware('logistics.approved')->group(function () {
        Route::get('/shipments', [ShipmentController::class, 'index'])->name('shipments.index');
        Route::get('/shipments/{shipment}', [ShipmentController::class, 'show'])->name('shipments.show');
        Route::post('/shipments/{shipment}/verify-lookup', [ShipmentController::class, 'verifyPackageLookup'])->name('shipments.verify-lookup');
        Route::post('/shipments/{shipment}/assign-courier', [ShipmentController::class, 'assignCourier'])->name('shipments.assign-courier');
        Route::post('/shipments/{shipment}/verify-hub-arrival', [ShipmentController::class, 'verifyHubArrival'])->name('shipments.verify-hub-arrival');
        Route::post('/shipments/{shipment}/assign-rider', [ShipmentController::class, 'assignRider'])->name('shipments.assign-rider');
        Route::post('/shipments/{shipment}/resolve-failed', [ShipmentController::class, 'resolveFailedDelivery'])->name('shipments.resolve-failed');

        Route::get('/couriers', [CourierController::class, 'index'])->name('couriers.index');
        Route::get('/couriers/create', [CourierController::class, 'create'])->name('couriers.create');
        Route::post('/couriers', [CourierController::class, 'store'])->name('couriers.store');
        Route::patch('/couriers/{courier}/status', [CourierController::class, 'updateStatus'])->name('couriers.update-status');
        Route::get('/couriers/{courier}/documents/{type}', [CourierController::class, 'document'])->name('couriers.document');

        Route::get('/riders/active', [ShipmentController::class, 'ridersIndex'])->name('riders.active');

        Route::get('/rider-applications', [RiderApplicationController::class, 'index'])->name('rider-applications.index');
        Route::get('/riders/create', [RiderApplicationController::class, 'create'])->name('riders.create');
        Route::post('/riders', [RiderApplicationController::class, 'store'])->name('riders.store');
        Route::patch('/rider-applications/{rider}/approve', [RiderApplicationController::class, 'approve'])->name('rider-applications.approve');
        Route::patch('/rider-applications/{rider}/reject', [RiderApplicationController::class, 'reject'])->name('rider-applications.reject');
        Route::get('/rider-applications/{rider}/document/{type}', [RiderApplicationController::class, 'document'])->name('rider-applications.document');

        Route::get('/branches', [BranchHubController::class, 'index'])->name('branches.index');
        Route::post('/branches', [BranchHubController::class, 'store'])->name('branches.store');
        Route::patch('/branches/{hub}/toggle-status', [BranchHubController::class, 'toggleStatus'])->name('branches.toggle-status');
        Route::get('/branches/municipalities', [BranchHubController::class, 'municipalitiesByProvince'])->name('branches.municipalities');
        Route::get('/branches/{hub}/barangays', [BranchHubController::class, 'barangaysByHub'])->name('branches.barangays');

        Route::get('/employees', [EmployeeManagementController::class, 'index'])->name('employees.index');
        Route::post('/employees/couriers/{courier}/reassign', [EmployeeManagementController::class, 'reassignCourier'])->name('employees.couriers.reassign');
        Route::post('/employees/riders/{rider}/reassign', [EmployeeManagementController::class, 'reassignRider'])->name('employees.riders.reassign');
        Route::patch('/employees/status', [EmployeeManagementController::class, 'toggleEmployeeStatus'])->name('employees.toggle-status');

        Route::get('/finance', [FinanceController::class, 'index'])->name('finance.index');
        Route::post('/finance/remittances/{remittance}/verify', [FinanceController::class, 'verifyRemittance'])->name('finance.verify-remittance');
        Route::post('/finance/settlements/settle', [FinanceController::class, 'settleSeller'])->name('finance.settle-seller');

        Route::get('/hub-settings', [HubSettingsController::class, 'index'])->name('hub-settings');
        Route::post('/hub-settings/photo', [HubSettingsController::class, 'updatePhoto'])->name('hub-settings.update-photo');
        Route::delete('/hub-settings/photo', [HubSettingsController::class, 'removePhoto'])->name('hub-settings.remove-photo');
        Route::patch('/hub-settings/hub-name', [HubSettingsController::class, 'updateHubName'])->name('hub-settings.update-hub-name');
        Route::get('/hub-settings/documents/{type}', [HubSettingsController::class, 'viewDocument'])->name('hub-settings.document');
        Route::patch('/hub-settings/name', [HubSettingsController::class, 'updateName'])->name('hub-settings.update-name');
        Route::patch('/hub-settings/phone', [HubSettingsController::class, 'updatePhone'])->name('hub-settings.update-phone');
        Route::post('/hub-settings/email/request-otp', [HubSettingsController::class, 'requestEmailOtp'])->name('hub-settings.request-email-otp');
        Route::post('/hub-settings/email/verify-otp', [HubSettingsController::class, 'verifyEmailOtp'])->name('hub-settings.verify-email-otp');
        Route::post('/hub-settings/password/request-otp', [HubSettingsController::class, 'requestPasswordOtp'])->name('hub-settings.request-password-otp');
        Route::post('/hub-settings/password/verify-otp', [HubSettingsController::class, 'verifyPasswordOtp'])->name('hub-settings.verify-password-otp');
        Route::patch('/hub-settings/password', [HubSettingsController::class, 'updatePassword'])->name('hub-settings.update-password');

        Route::patch('/notifications/mark-all-read', function () {
            auth()->user()->unreadNotifications->markAsRead();
            return back()->with('success', 'All notifications marked as read.');
        })->name('notifications.markAllRead');

        Route::patch('/notifications/{id}/read', function ($id) {
            $notification = auth()->user()->notifications()->findOrFail($id);
            $notification->markAsRead();
            return back();
        })->name('notifications.markRead');
    });
});