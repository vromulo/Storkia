<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\AddressController;
use App\Http\Controllers\Api\AnnouncementController;
use App\Http\Controllers\Api\ApiMessageController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BundleDealController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CheckoutController;
use App\Http\Controllers\Api\DealController;
use App\Http\Controllers\Api\DeleteAccountController;
use App\Http\Controllers\Api\HelpCenterController;
use App\Http\Controllers\Api\HomeFeedController;
use App\Http\Controllers\Api\IdentityVerificationController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PasswordResetController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ReturnRequestController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\RewardController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\SellerController;
use App\Http\Controllers\Api\SellerReportController;
use App\Http\Controllers\Api\TrendController;
use App\Http\Controllers\Api\VoucherController;
use App\Http\Controllers\Api\WishlistController;
use App\Http\Controllers\Api\Rider\AuthApiController as RiderAuthApiController;
use App\Http\Controllers\Api\Rider\RiderDeliveryApiController;
use App\Http\Controllers\Api\Rider\RiderFinanceApiController;
use App\Http\Controllers\Api\Rider\RiderReportApiController;
use App\Http\Controllers\Api\Rider\RiderReturnPickupController;
use App\Http\Controllers\Api\Courier\AuthApiController as CourierAuthApiController;
use App\Http\Controllers\Api\Courier\CourierLocationController;
use App\Http\Controllers\Api\Courier\CourierNotificationController;
use App\Http\Controllers\Api\Courier\CourierPackageVerificationController;
use App\Http\Controllers\Api\Courier\CourierPickupController;
use App\Http\Controllers\Api\Courier\CourierReportApiController;
use App\Http\Controllers\Api\Courier\CourierReturnDeliveryController;

Route::prefix('v1')->group(function () {
    Route::middleware('throttle:api-auth')->group(function () {
        Route::post('/send-otp', [AuthController::class, 'sendOtp']);
        Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/forgot-password', [PasswordResetController::class, 'forgot'])->middleware('throttle:5,1');
        Route::post('/reset-password', [PasswordResetController::class, 'reset'])->middleware('throttle:10,1');
    });

    Route::middleware(['auth:sanctum', 'api.actor:buyer'])->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/user', [AuthController::class, 'me']);
        Route::get('/account/sanction', [AuthController::class, 'sanctionStatus']);
        Route::post('/account/sanction/appeal', [AuthController::class, 'submitSanctionAppeal'])->middleware('throttle:15,1');
    });

    Route::middleware(['auth:sanctum', 'api.actor:buyer', 'account.active'])->group(function () {
        Route::get('/home', [HomeFeedController::class, 'index']);
        Route::get('/super-deals', [DealController::class, 'index']);
        Route::get('/bundles', [BundleDealController::class, 'index']);
        Route::post('/bundles/{id}/add', [BundleDealController::class, 'addToCart'])->whereNumber('id')->middleware('throttle:30,1');
        Route::get('/trends', [TrendController::class, 'index']);

        Route::get('/announcements', [AnnouncementController::class, 'index']);
        Route::get('/announcements/unread-count', [AnnouncementController::class, 'unreadCount']);
        Route::match(['post', 'patch'], '/announcements/read-all', [AnnouncementController::class, 'markAllAsRead']);
        Route::match(['post', 'patch'], '/announcements/{id}/read', [AnnouncementController::class, 'markAsRead'])->whereNumber('id');

        Route::get('/products', [ProductController::class, 'index']);
        Route::get('/products/{product}', [ProductController::class, 'show']);
        Route::post('/products/{id}/restock-subscribe', [ProductController::class, 'subscribeRestock'])->whereNumber('id')->middleware('throttle:30,1');
        Route::post('/products/{id}/questions', [ProductController::class, 'askQuestion'])->whereNumber('id')->middleware('throttle:20,1');

        Route::get('/sellers', [SellerController::class, 'index']);
        Route::get('/sellers/{id}', [SellerController::class, 'show']);
        Route::post('/sellers/{id}/follow', [SellerController::class, 'toggleFollow'])->middleware('throttle:30,1');
        Route::get('/sellers/{id}/report/context', [SellerReportController::class, 'context'])->whereNumber('id')->middleware('throttle:30,1');
        Route::post('/sellers/{id}/report', [SellerReportController::class, 'store'])->whereNumber('id')->middleware('throttle:5,1');
        Route::get('/following', [SellerController::class, 'followingList']);
        Route::get('/seller-reports', [SellerReportController::class, 'index']);

        Route::get('/category/{slug}', [CategoryController::class, 'show']);
        Route::get('/search', [SearchController::class, 'index']);
        Route::get('/search/suggestions', [SearchController::class, 'suggestions']);

        Route::get('/cart', [CartController::class, 'index']);
        Route::post('/cart', [CartController::class, 'store'])->middleware('throttle:60,1');
        Route::put('/cart/{id}', [CartController::class, 'update'])->whereNumber('id');
        Route::patch('/cart/{id}', [CartController::class, 'update'])->whereNumber('id');
        Route::delete('/cart/{id}', [CartController::class, 'destroy'])->whereNumber('id');

        Route::get('/wishlist', [WishlistController::class, 'index']);
        Route::post('/wishlist/toggle', [WishlistController::class, 'toggle'])->middleware('throttle:60,1');
        Route::delete('/wishlist/{productId}', [WishlistController::class, 'destroy'])->whereNumber('productId');

        Route::get('/vouchers', [VoucherController::class, 'index']);
        Route::get('/vouchers/active', [VoucherController::class, 'activeVouchers']);
        Route::post('/vouchers/claim', [VoucherController::class, 'claim'])->middleware('throttle:30,1');

        Route::get('/rewards', [RewardController::class, 'overview']);
        Route::get('/rewards/chest', [RewardController::class, 'chestStatus']);
        Route::post('/rewards/chest/open', [RewardController::class, 'openChest'])->middleware('throttle:30,1');
        Route::post('/rewards/spin', [RewardController::class, 'spin'])->middleware('throttle:30,1');
        Route::post('/rewards/redeem', [RewardController::class, 'redeem'])->middleware('throttle:30,1');
        Route::get('/rewards/transactions', [RewardController::class, 'transactions']);
        Route::get('/wallet', [RewardController::class, 'walletOverview']);
        Route::post('/wallet/topup', [RewardController::class, 'submitWalletTopup'])->middleware('throttle:15,1');

        Route::get('/addresses', [AddressController::class, 'index']);
        Route::post('/addresses', [AddressController::class, 'store']);
        Route::get('/addresses/provinces', [AddressController::class, 'getProvinces']);
        Route::get('/addresses/municipalities/{provinceCode}', [AddressController::class, 'getMunicipalities']);
        Route::get('/addresses/barangays/{municipalityCode}', [AddressController::class, 'getBarangays']);
        Route::put('/addresses/{id}', [AddressController::class, 'update'])->whereNumber('id');
        Route::patch('/addresses/{id}/default', [AddressController::class, 'setDefault'])->whereNumber('id');
        Route::delete('/addresses/{id}', [AddressController::class, 'destroy'])->whereNumber('id');

        Route::post('/checkout/preview', [CheckoutController::class, 'preview'])->middleware('throttle:60,1');
        Route::post('/checkout/store', [CheckoutController::class, 'store'])->middleware('throttle:20,1');

        Route::get('/messages/contacts', [ApiMessageController::class, 'contacts']);
        Route::get('/messages/fetch/{contactId}', [ApiMessageController::class, 'messages'])->whereNumber('contactId');
        Route::post('/messages', [ApiMessageController::class, 'store'])->middleware('throttle:60,1');
        Route::patch('/messages/{contactId}/read', [ApiMessageController::class, 'markAsRead'])->whereNumber('contactId');

        Route::get('/help-center', [HelpCenterController::class, 'index']);
        Route::get('/help-center/messages', [HelpCenterController::class, 'messages']);
        Route::post('/help-center/messages', [HelpCenterController::class, 'store'])->middleware('throttle:60,1');
        Route::patch('/help-center/read', [HelpCenterController::class, 'markAsRead']);

        Route::get('/orders', [OrderController::class, 'index']);
        Route::get('/orders/proof/{proof}', [OrderController::class, 'proofImage']);
        Route::get('/orders/{id}', [OrderController::class, 'show'])->whereNumber('id');
        Route::get('/orders/{id}/invoice', [OrderController::class, 'invoice'])->whereNumber('id');
        Route::get('/orders/{id}/live-tracking', [OrderController::class, 'liveTracking'])->whereNumber('id');
        Route::get('/orders/{id}/delivery-chat', [OrderController::class, 'deliveryChatMessages'])->whereNumber('id');
        Route::post('/orders/{id}/delivery-chat', [OrderController::class, 'sendDeliveryChatMessage'])->whereNumber('id')->middleware('throttle:60,1');
        Route::post('/orders/{id}/cancel', [OrderController::class, 'cancelOrder'])->whereNumber('id');
        Route::post('/orders/{id}/switch-to-cod', [OrderController::class, 'switchToCod'])->whereNumber('id');
        Route::post('/orders/{id}/cancel-flagged-payment', [OrderController::class, 'cancelFlaggedPaymentOrder'])->whereNumber('id');
        Route::post('/orders/{id}/confirm-receipt', [OrderController::class, 'confirmReceipt'])->whereNumber('id');
        Route::post('/orders/{id}/buy-again', [OrderController::class, 'buyAgain'])->whereNumber('id');
        Route::post('/orders/{id}/report-dispute', [ReturnRequestController::class, 'legacyReport'])->whereNumber('id');
        Route::post('/orders/{id}/escalate-dispute', [ReturnRequestController::class, 'legacyEscalate'])->whereNumber('id');
        Route::get('/orders/{id}/return', [ReturnRequestController::class, 'options'])->whereNumber('id');
        Route::post('/orders/{id}/return', [ReturnRequestController::class, 'store'])->whereNumber('id')->middleware('throttle:10,1');
        Route::get('/returns/{id}', [ReturnRequestController::class, 'show'])->whereNumber('id');
        Route::post('/returns/{id}/escalate', [ReturnRequestController::class, 'escalate'])->whereNumber('id')->middleware('throttle:10,1');

        Route::post('/orders/{id}/reviews', [ReviewController::class, 'store'])->whereNumber('id');
        Route::post('/reviews/{review}', [ReviewController::class, 'update'])->whereNumber('review');
        Route::patch('/reviews/{review}', [ReviewController::class, 'update'])->whereNumber('review');
        Route::post('/reviews/{review}/helpful', [ReviewController::class, 'toggleHelpful'])->whereNumber('review')->middleware('throttle:60,1');

        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
        Route::patch('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
        Route::patch('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);

        Route::get('/profile', [ProfileController::class, 'show']);
        Route::patch('/profile/username', [ProfileController::class, 'updateUsername']);
        Route::post('/profile/avatar', [ProfileController::class, 'updateAvatar']);
        Route::delete('/profile/avatar', [ProfileController::class, 'removeAvatar']);

        Route::patch('/account/name', [AccountController::class, 'updateName']);
        Route::patch('/account/phone', [AccountController::class, 'updatePhone']);
        Route::post('/account/email/request-otp', [AccountController::class, 'requestEmailOtp'])->middleware('throttle:10,1');
        Route::post('/account/email/verify-otp', [AccountController::class, 'verifyEmailOtp'])->middleware('throttle:15,1');
        Route::post('/account/password/request-otp', [AccountController::class, 'requestPasswordOtp'])->middleware('throttle:10,1');
        Route::post('/account/password/verify-otp', [AccountController::class, 'verifyPasswordOtp'])->middleware('throttle:15,1');
        Route::patch('/account/password', [AccountController::class, 'updatePassword']);
        Route::get('/account/identity-verification', [IdentityVerificationController::class, 'show']);
        Route::post('/account/identity-verification', [IdentityVerificationController::class, 'store'])->middleware('throttle:10,1');
        Route::get('/account/delete/eligibility', [DeleteAccountController::class, 'eligibility'])->middleware('throttle:30,1');
        Route::post('/account/delete', [DeleteAccountController::class, 'destroy'])->middleware('throttle:10,1');
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
            Route::post('/availability', [RiderAuthApiController::class, 'updateAvailability']);
            Route::post('/shift/checklist', [RiderAuthApiController::class, 'submitPreTripChecklist']);
            Route::post('/location', [RiderAuthApiController::class, 'updateLocation']);
            Route::post('/location/batch', [RiderAuthApiController::class, 'batchUpdateLocations']);
            Route::post('/profile/vehicle', [RiderAuthApiController::class, 'updateVehicleAndDocuments']);

            Route::get('/deliveries', [RiderDeliveryApiController::class, 'index']);
            Route::get('/deliveries/{assignmentId}', [RiderDeliveryApiController::class, 'show'])->whereNumber('assignmentId');
            Route::post('/deliveries/{assignmentId}/accept', [RiderDeliveryApiController::class, 'accept'])->whereNumber('assignmentId');
            Route::post('/deliveries/{assignmentId}/decline', [RiderDeliveryApiController::class, 'decline'])->whereNumber('assignmentId');
            Route::post('/deliveries/{assignmentId}/verify', [RiderDeliveryApiController::class, 'verifyPackage'])->whereNumber('assignmentId');
            Route::post('/deliveries/{assignmentId}/start', [RiderDeliveryApiController::class, 'startDelivery'])->whereNumber('assignmentId');
            Route::post('/deliveries/{assignmentId}/complete', [RiderDeliveryApiController::class, 'completeDelivery'])->whereNumber('assignmentId');
            Route::post('/deliveries/{assignmentId}/fail', [RiderDeliveryApiController::class, 'failDelivery'])->whereNumber('assignmentId');
            Route::post('/deliveries/{assignmentId}/report-delay', [RiderDeliveryApiController::class, 'reportDelay'])->whereNumber('assignmentId');
            Route::post('/deliveries/{assignmentId}/report-breakdown', [RiderDeliveryApiController::class, 'reportBreakdown'])->whereNumber('assignmentId');
            Route::get('/deliveries/{assignmentId}/chat', [RiderDeliveryApiController::class, 'chatMessages'])->whereNumber('assignmentId');
            Route::post('/deliveries/{assignmentId}/chat', [RiderDeliveryApiController::class, 'sendChatMessage'])->whereNumber('assignmentId')->middleware('throttle:60,1');

            Route::get('/returns', [RiderReturnPickupController::class, 'index']);
            Route::post('/returns/{id}/confirm-pickup', [RiderReturnPickupController::class, 'confirmPickup'])->whereNumber('id')->middleware('throttle:30,1');
            Route::post('/returns/{id}/report-failed', [RiderReturnPickupController::class, 'reportFailed'])->whereNumber('id')->middleware('throttle:30,1');

            Route::get('/cod', [RiderFinanceApiController::class, 'codOverview']);
            Route::post('/cod/remit-all', [RiderFinanceApiController::class, 'remitAllCod']);
            Route::post('/cod/{codCollectionId}/remit', [RiderFinanceApiController::class, 'remitSingleCod'])->whereNumber('codCollectionId');
            Route::post('/cod/{remittanceId}/confirm', [RiderFinanceApiController::class, 'confirmRemittanceBlocked']);

            Route::get('/earnings', [RiderFinanceApiController::class, 'earningsAndWallet']);
            Route::post('/withdrawals', [RiderFinanceApiController::class, 'requestWithdrawal']);
            Route::get('/ratings', [RiderFinanceApiController::class, 'ratings']);
            Route::get('/reports', [RiderReportApiController::class, 'index']);

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
            Route::get('/pickups/{id}', [CourierPickupController::class, 'show'])->whereNumber('id');
            Route::post('/pickups/{id}/accept', [CourierPickupController::class, 'accept'])->whereNumber('id');
            Route::post('/pickups/{id}/decline', [CourierPickupController::class, 'decline'])->whereNumber('id');
            Route::post('/pickups/{id}/verify', [CourierPackageVerificationController::class, 'verifyPackage'])->whereNumber('id');
            Route::post('/pickups/{id}/verify-package', [CourierPackageVerificationController::class, 'verifyPackage'])->whereNumber('id');
            Route::post('/pickups/{id}/collect', [CourierPickupController::class, 'confirmCollection'])->whereNumber('id');
            Route::post('/pickups/{id}/handover', [CourierPickupController::class, 'handoverToHub'])->whereNumber('id');
            Route::post('/pickups/{id}/report-delay', [CourierPickupController::class, 'reportDelay'])->whereNumber('id');
            Route::post('/pickups/{id}/report-breakdown', [CourierPickupController::class, 'reportBreakdown'])->whereNumber('id');
            Route::post('/pickups/{id}/discrepancy-note', [CourierPickupController::class, 'submitDiscrepancyNote'])->whereNumber('id');

            Route::get('/returns', [CourierReturnDeliveryController::class, 'index']);
            Route::post('/returns/{id}/start-delivery', [CourierReturnDeliveryController::class, 'startDelivery'])->whereNumber('id')->middleware('throttle:30,1');
            Route::post('/returns/{id}/confirm-delivered', [CourierReturnDeliveryController::class, 'confirmDelivered'])->whereNumber('id')->middleware('throttle:30,1');
            Route::post('/returns/{id}/report-failed', [CourierReturnDeliveryController::class, 'reportFailed'])->whereNumber('id')->middleware('throttle:30,1');

            Route::get('/earnings', [CourierNotificationController::class, 'earningsAndWallet']);
            Route::post('/withdrawals', [CourierNotificationController::class, 'requestWithdrawal']);
            Route::get('/ratings', [CourierNotificationController::class, 'ratings']);
            Route::get('/reports', [CourierReportApiController::class, 'index']);

            Route::get('/notifications/counts', [CourierNotificationController::class, 'badges']);
            Route::get('/badge-counts', [CourierNotificationController::class, 'badges']);
            Route::get('/notifications', [CourierNotificationController::class, 'index']);
            Route::patch('/notifications/read-all', [CourierNotificationController::class, 'markAllAsRead']);
            Route::patch('/notifications/{id}/read', [CourierNotificationController::class, 'markAsRead']);
        });
    });
});