<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\SellerController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\ApiMessageController;
use App\Http\Controllers\Api\Rider\AuthApiController as RiderAuthApiController;
use App\Http\Controllers\Api\Rider\RiderDeliveryApiController;
use App\Http\Controllers\Api\Rider\RiderFinanceApiController;
use App\Http\Controllers\Api\Courier\AuthApiController as CourierAuthApiController;
use App\Http\Controllers\Api\AddressController;
use App\Http\Controllers\Api\CheckoutController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\WishlistController;
use App\Http\Controllers\Api\VoucherController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\Courier\CourierLocationController;
use App\Http\Controllers\Api\Courier\CourierNotificationController;
use App\Http\Controllers\Api\Courier\CourierPackageVerificationController;
use App\Http\Controllers\Api\Courier\CourierPickupController;

Route::prefix('v1')->group(function () {
    Route::middleware('throttle:api-auth')->group(function () {
        Route::post('/send-otp', [AuthController::class, 'sendOtp']);
        Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login']);
    });

    Route::middleware(['auth:sanctum', 'api.actor:buyer'])->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/user', [AuthController::class, 'me']);

        Route::get('/account/sanction', [AuthController::class, 'sanctionStatus']);
        Route::post('/account/sanction/appeal', [AuthController::class, 'submitSanctionAppeal']);
    });

    Route::middleware(['auth:sanctum', 'api.actor:buyer', 'account.active'])->group(function () {
        Route::get('/products', [ProductController::class, 'index']);
        Route::get('/products/{product}', [ProductController::class, 'show']);

        Route::get('/sellers', [SellerController::class, 'index']);
        Route::get('/sellers/{id}', [SellerController::class, 'show']);
        Route::post('/sellers/{id}/follow', [SellerController::class, 'toggleFollow']);
        Route::get('/following', [SellerController::class, 'followingList']);

        Route::get('/category/{slug}', [CategoryController::class, 'show']);
        Route::get('/search', [SearchController::class, 'index']);
        Route::get('/search/suggestions', [SearchController::class, 'suggestions']);

        Route::get('/cart', [CartController::class, 'index']);
        Route::post('/cart', [CartController::class, 'store']);
        Route::put('/cart/{id}', [CartController::class, 'update']);
        Route::delete('/cart/{id}', [CartController::class, 'destroy']);

        Route::get('/wishlist', [WishlistController::class, 'index']);
        Route::post('/wishlist/toggle', [WishlistController::class, 'toggle']);
        Route::delete('/wishlist/{productId}', [WishlistController::class, 'destroy']);

        Route::get('/vouchers', [VoucherController::class, 'index']);
        Route::get('/vouchers/active', [VoucherController::class, 'activeVouchers']);
        Route::post('/vouchers/claim', [VoucherController::class, 'claim']);

        Route::get('/addresses', [AddressController::class, 'index']);
        Route::post('/addresses', [AddressController::class, 'store']);
        Route::put('/addresses/{id}', [AddressController::class, 'update']);
        Route::delete('/addresses/{id}', [AddressController::class, 'destroy']);
        Route::get('/addresses/provinces', [AddressController::class, 'getProvinces']);
        Route::get('/addresses/municipalities/{provinceCode}', [AddressController::class, 'getMunicipalities']);
        Route::get('/addresses/barangays/{municipalityCode}', [AddressController::class, 'getBarangays']);

        Route::post('/checkout/preview', [CheckoutController::class, 'preview']);
        Route::post('/checkout/store', [CheckoutController::class, 'store']);

        Route::get('/messages/contacts', [ApiMessageController::class, 'fetchContacts']);
        Route::get('/messages/unread-count', [ApiMessageController::class, 'unreadCount']);
        Route::get('/messages/fetch/{contactId}', [ApiMessageController::class, 'fetchMessages']);
        Route::post('/messages', [ApiMessageController::class, 'store']);
        Route::patch('/messages/{contactId}/read', [ApiMessageController::class, 'markAsRead']);

        Route::get('/orders', [OrderController::class, 'index']);
        Route::get('/orders/{id}', [OrderController::class, 'show']);
        Route::post('/orders/{id}/cancel', [OrderController::class, 'cancelOrder']);
        Route::post('/orders/{id}/switch-to-cod', [OrderController::class, 'switchToCod']);
        Route::post('/orders/{id}/cancel-flagged-payment', [OrderController::class, 'cancelFlaggedPaymentOrder']);
        Route::post('/orders/{id}/confirm-receipt', [OrderController::class, 'confirmReceipt']);
        Route::post('/orders/{id}/report-dispute', [OrderController::class, 'reportDispute']);
        Route::post('/orders/{id}/escalate-dispute', [OrderController::class, 'escalateDispute']);
        Route::post('/orders/{id}/buy-again', [OrderController::class, 'buyAgain']);
        Route::get('/orders/proof/{proof}', [OrderController::class, 'proofImage']);

        Route::post('/orders/{id}/reviews', [ReviewController::class, 'store']);
        Route::post('/reviews/{review}', [ReviewController::class, 'update']);
        Route::patch('/reviews/{review}', [ReviewController::class, 'update']);

        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::patch('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
        Route::patch('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);

        Route::patch('/account/name', [AccountController::class, 'updateName']);
        Route::patch('/account/phone', [AccountController::class, 'updatePhone']);
        Route::post('/account/email/request-otp', [AccountController::class, 'requestEmailOtp']);
        Route::post('/account/email/verify-otp', [AccountController::class, 'verifyEmailOtp']);
        Route::post('/account/password/request-otp', [AccountController::class, 'requestPasswordOtp']);
        Route::post('/account/password/verify-otp', [AccountController::class, 'verifyPasswordOtp']);
        Route::patch('/account/password', [AccountController::class, 'updatePassword']);
        Route::get('/account/identity-verification', [AccountController::class, 'identityVerificationStatus']);
        Route::post('/account/identity-verification', [AccountController::class, 'submitIdentityVerification']);
    });
});

Route::prefix('v1/rider')->group(function () {
    Route::post('/login', [RiderAuthApiController::class, 'login'])->middleware('throttle:api-auth');

    Route::middleware(['auth:sanctum', 'api.actor:rider'])->group(function () {
        Route::post('/logout', [RiderAuthApiController::class, 'logout']);
        Route::get('/user', [RiderAuthApiController::class, 'me']);
        Route::get('/profile', [RiderAuthApiController::class, 'me']);

        Route::middleware('account.active')->group(function () {
            Route::get('/dashboard', [RiderDeliveryApiController::class, 'dashboard']);

            Route::patch('/availability', [RiderAuthApiController::class, 'updateAvailability']);
            Route::post('/location', [RiderAuthApiController::class, 'updateLocation']);
            Route::post('/profile/vehicle', [RiderAuthApiController::class, 'updateVehicleAndDocuments']);

            Route::get('/deliveries', [RiderDeliveryApiController::class, 'index']);
            Route::get('/deliveries/{assignmentId}', [RiderDeliveryApiController::class, 'show']);
            Route::post('/deliveries/{assignmentId}/accept', [RiderDeliveryApiController::class, 'accept']);
            Route::post('/deliveries/{assignmentId}/decline', [RiderDeliveryApiController::class, 'decline']);
            Route::post('/deliveries/{assignmentId}/verify', [RiderDeliveryApiController::class, 'verifyPackage']);
            Route::post('/deliveries/{assignmentId}/start', [RiderDeliveryApiController::class, 'startDelivery']);
            Route::post('/deliveries/{assignmentId}/complete', [RiderDeliveryApiController::class, 'completeDelivery']);
            Route::post('/deliveries/{assignmentId}/fail', [RiderDeliveryApiController::class, 'failDelivery']);

            Route::get('/cod', [RiderFinanceApiController::class, 'codOverview']);
            Route::post('/cod/remit-all', [RiderFinanceApiController::class, 'remitAllCod']);
            Route::post('/cod/{codCollectionId}/remit', [RiderFinanceApiController::class, 'remitSingleCod']);
            Route::post('/cod/{remittanceId}/confirm', [RiderFinanceApiController::class, 'confirmRemittanceBlocked']);

            Route::get('/earnings', [RiderFinanceApiController::class, 'earningsAndWallet']);
            Route::post('/withdrawals', [RiderFinanceApiController::class, 'requestWithdrawal']);
            Route::get('/ratings', [RiderFinanceApiController::class, 'ratings']);

            Route::get('/notifications', [RiderFinanceApiController::class, 'notifications']);
            Route::patch('/notifications/read-all', [RiderFinanceApiController::class, 'markAllNotificationsRead']);
            Route::patch('/notifications/{id}/read', [RiderFinanceApiController::class, 'markNotificationRead']);
        });
    });
});

Route::prefix('v1/courier')->group(function () {
    Route::post('/login', [CourierAuthApiController::class, 'login'])->middleware('throttle:api-auth');

    Route::middleware(['auth:sanctum', 'api.actor:courier'])->group(function () {
        Route::post('/logout', [CourierAuthApiController::class, 'logout']);
        Route::get('/user', [CourierAuthApiController::class, 'me']);
        Route::get('/profile', [CourierAuthApiController::class, 'me']);
        Route::get('/me', [CourierAuthApiController::class, 'me']);
        Route::get('/profile/document/{type}', [CourierAuthApiController::class, 'viewDocument']);

        Route::middleware('account.active')->group(function () {
            Route::get('/dashboard', [CourierPickupController::class, 'dashboard']);

            Route::patch('/availability', [CourierAuthApiController::class, 'updateAvailability']);
            Route::post('/availability', [CourierAuthApiController::class, 'updateAvailability']);
            Route::post('/shift/checklist', [CourierAuthApiController::class, 'submitPreTripChecklist']);
            Route::post('/profile/vehicle', [CourierAuthApiController::class, 'updateVehicleAndDocuments']);

            Route::post('/location', [CourierLocationController::class, 'updateLocation']);
            Route::post('/location/batch', [CourierLocationController::class, 'batchUpdateLocations']);
            Route::get('/location/latest', [CourierLocationController::class, 'latestLocation']);

            Route::get('/pickups', [CourierPickupController::class, 'index']);
            Route::get('/pickups/{id}', [CourierPickupController::class, 'show']);
            Route::post('/pickups/{id}/accept', [CourierPickupController::class, 'accept']);
            Route::post('/pickups/{id}/decline', [CourierPickupController::class, 'decline']);
            Route::post('/pickups/{id}/verify', [CourierPackageVerificationController::class, 'verifyPackage']);
            Route::post('/pickups/{id}/verify-package', [CourierPackageVerificationController::class, 'verifyPackage']);
            Route::post('/pickups/{id}/collect', [CourierPickupController::class, 'confirmCollection']);
            Route::post('/pickups/{id}/handover', [CourierPickupController::class, 'handoverToHub']);
            Route::post('/pickups/{id}/report-delay', [CourierPickupController::class, 'reportDelay']);
            Route::post('/pickups/{id}/report-breakdown', [CourierPickupController::class, 'reportBreakdown']);
            Route::post('/pickups/{id}/discrepancy-note', [CourierPickupController::class, 'submitDiscrepancyNote']);

            Route::get('/earnings', [CourierNotificationController::class, 'earningsAndWallet']);
            Route::post('/withdrawals', [CourierNotificationController::class, 'requestWithdrawal']);
            Route::get('/ratings', [CourierNotificationController::class, 'ratings']);

            Route::get('/notifications/counts', [CourierNotificationController::class, 'badges']);
            Route::get('/badge-counts', [CourierNotificationController::class, 'badges']);
            Route::get('/notifications', [CourierNotificationController::class, 'index']);
            Route::patch('/notifications/read-all', [CourierNotificationController::class, 'markAllAsRead']);
            Route::patch('/notifications/{id}/read', [CourierNotificationController::class, 'markAsRead']);
        });
    });
});