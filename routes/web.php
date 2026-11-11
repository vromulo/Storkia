<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Buyer\AnnouncementController;
use App\Http\Controllers\Buyer\AuthController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Buyer\BundleDealController;
use App\Http\Controllers\Buyer\BuyerMessageController;
use App\Http\Controllers\Buyer\HomeController;
use App\Http\Controllers\Buyer\CategoryController;
use App\Http\Controllers\Buyer\DealController;
use App\Http\Controllers\Buyer\TrendController;
use App\Http\Controllers\Buyer\ProductController;
use App\Http\Controllers\Buyer\SearchController;
use App\Http\Controllers\Buyer\AccountManagementController;
use App\Http\Controllers\Buyer\AddressController;
use App\Http\Controllers\Buyer\CartController;
use App\Http\Controllers\Buyer\ReturnRequestController;
use App\Http\Controllers\Buyer\CheckoutController;
use App\Http\Controllers\Buyer\DeleteAccountController;
use App\Http\Controllers\Buyer\OrderController;
use App\Http\Controllers\Buyer\PointsRewardController;
use App\Http\Controllers\Buyer\ReviewController;
use App\Http\Controllers\Buyer\SellerController;
use App\Http\Controllers\Buyer\SellerReportController;
use App\Http\Controllers\Buyer\VoucherController;
use App\Http\Controllers\Buyer\WishlistController;
use App\Http\Controllers\Support\HelpCenterController;
use App\Livewire\Buyer\IdentityVerificationFlow;
use App\Http\Controllers\Buyer\ProfileController;

Route::middleware('throttle:120,1')->group(function () {
    Route::view('/', 'buyer.landing-page')->name('landing');
    Route::view('/terms-and-conditions', 'buyer.terms-and-conditions')->name('terms');
    Route::view('/privacy-policy', 'buyer.privacy-policy')->name('privacy');
    Route::view('/cookie-policy', 'buyer.cookie-policy')->name('cookies');

    Route::get('/shop', [HomeController::class, 'index'])->name('home');
    Route::get('/super-deals', [DealController::class, 'index'])->name('deals.index');
    Route::get('/bundles', [BundleDealController::class, 'index'])->name('bundles.index');
    Route::get('/announcements', [AnnouncementController::class, 'index'])->name('announcements.index');
    Route::get('/trends', [TrendController::class, 'index'])->name('trends.index');
    Route::get('/sellers', [SellerController::class, 'index'])->name('sellers.index');
    Route::get('/sellers/{seller}', [SellerController::class, 'show'])->name('sellers.show');
    Route::get('/product/{product}', [ProductController::class, 'show'])->name('product.show');
    Route::get('/category/{slug}', [CategoryController::class, 'show'])->name('category.show');
    Route::get('/search', [SearchController::class, 'index'])->name('search.search');
    Route::get('/search/suggestions', [SearchController::class, 'suggestions'])->name('search.suggestions');
    Route::get('/user/orders/proof/{proof}', [OrderController::class, 'proofImage'])->name('user.orders.proof-image');

    Route::middleware(['guest', 'throttle:30,1'])->group(function () {
        Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
        Route::post('/login', [AuthController::class, 'login'])->name('login.post');
        Route::get('/register', [AuthController::class, 'showRegister'])->name('register');

        Route::get('/forgot-password', [PasswordResetController::class, 'showRequestForm'])->name('password.request');
        Route::post('/forgot-password', [PasswordResetController::class, 'sendResetLink'])
            ->middleware('throttle:5,1')
            ->name('password.email');
        Route::get('/reset-password/{token}', [PasswordResetController::class, 'showResetForm'])->name('password.reset');
        Route::post('/reset-password', [PasswordResetController::class, 'reset'])
            ->middleware('throttle:10,1')
            ->name('password.update');
    });

    Route::get('/account/suspended', [AuthController::class, 'showSuspendedNotice'])->name('buyer.suspended');
    Route::post('/account/suspended/appeal', [AuthController::class, 'submitSuspensionAppeal'])
        ->middleware('throttle:15,1')
        ->name('buyer.suspended.appeal');

    Route::middleware('auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

        Route::post('/sellers/{seller}/follow', [SellerController::class, 'toggleFollow'])
            ->middleware('throttle:30,1')
            ->name('sellers.follow');

        Route::post('/product/{product}/restock-subscribe', [ProductController::class, 'subscribeRestock'])
            ->middleware('throttle:30,1')
            ->name('product.restock.subscribe');

        Route::post('/product/{product}/questions', [ProductController::class, 'askQuestion'])
            ->middleware('throttle:20,1')
            ->name('product.questions.store');

        Route::post('/user/reviews/{review}/helpful', [ProductController::class, 'toggleReviewHelpful'])
            ->middleware('throttle:60,1')
            ->name('user.reviews.helpful');

        Route::post('/bundles/{id}/add', [BundleDealController::class, 'addBundleToCart'])
            ->middleware('throttle:30,1')
            ->name('bundles.add');
        Route::post('/bundles/{id}/cart', [BundleDealController::class, 'addBundleToCart'])
            ->middleware('throttle:30,1')
            ->name('bundles.add-to-cart');

        Route::match(['post', 'patch'], '/announcements/read-all', [AnnouncementController::class, 'markAllAsRead'])
            ->name('announcements.markAllRead');
        Route::match(['post', 'patch'], '/announcements/{id}/read', [AnnouncementController::class, 'markAsRead'])
            ->name('announcements.markRead');

        Route::middleware('role:Buyer')->group(function () {
            Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
            Route::post('/cart', [CartController::class, 'store'])->middleware('throttle:60,1')->name('cart.store');
            Route::delete('/cart/{cart}', [CartController::class, 'destroy'])->name('cart.destroy');
            Route::patch('/cart/{cart}', [CartController::class, 'update'])->name('cart.update');

            Route::post('/checkout/buy-now', [CheckoutController::class, 'buyNow'])->middleware('throttle:30,1')->name('checkout.buy-now');
            Route::get('/checkout', [CheckoutController::class, 'index'])->name('checkout.index');
            Route::post('/checkout', [CheckoutController::class, 'store'])->middleware('throttle:20,1')->name('checkout.store');
            Route::get('/checkout/success/{order}', [CheckoutController::class, 'success'])->name('checkout.success');

            Route::get('/sellers/{seller}/report/context', [SellerReportController::class, 'context'])
                ->middleware('throttle:30,1')
                ->name('sellers.report.context');
            Route::post('/sellers/{seller}/report', [SellerReportController::class, 'store'])
                ->middleware('throttle:5,1')
                ->name('sellers.report.store');

            Route::prefix('user')->name('user.')->group(function () {
                Route::view('/account', 'buyer.option.account.personal-center')->name('account');
                Route::get('/profile', [ProfileController::class, 'index'])->name('profile');
                Route::patch('/profile/username', [ProfileController::class, 'updateUsername'])->name('profile.update-username');
                Route::post('/profile/avatar', [ProfileController::class, 'updateAvatar'])->name('profile.update-avatar');
                Route::delete('/profile/avatar', [ProfileController::class, 'removeAvatar'])->name('profile.remove-avatar');

                Route::get('/account-management', [AccountManagementController::class, 'index'])->name('account-management');
                Route::patch('/account-management/name', [AccountManagementController::class, 'updateName'])->name('account-management.update-name');
                Route::patch('/account-management/phone', [AccountManagementController::class, 'updatePhone'])->name('account-management.update-phone');
                Route::post('/account-management/email/request-otp', [AccountManagementController::class, 'requestEmailOtp'])->middleware('throttle:10,1')->name('account-management.request-email-otp');
                Route::post('/account-management/email/verify-otp', [AccountManagementController::class, 'verifyEmailOtp'])->middleware('throttle:15,1')->name('account-management.verify-otp');
                Route::post('/account-management/password/request-otp', [AccountManagementController::class, 'requestPasswordOtp'])->middleware('throttle:10,1')->name('account-management.request-password-otp');
                Route::post('/account-management/password/verify-otp', [AccountManagementController::class, 'verifyPasswordOtp'])->middleware('throttle:15,1')->name('account-management.verify-password-otp');
                Route::patch('/account-management/password', [AccountManagementController::class, 'updatePassword'])->name('account-management.update-password');
                Route::get('/account-management/delete/eligibility', [DeleteAccountController::class, 'eligibility'])->middleware('throttle:30,1')->name('account-management.delete.eligibility');
                Route::post('/account-management/delete', [DeleteAccountController::class, 'destroy'])->middleware('throttle:10,1')->name('account-management.delete');

                Route::get('/addresses', [AddressController::class, 'index'])->name('addresses');
                Route::post('/addresses', [AddressController::class, 'store'])->name('addresses.store');
                Route::put('/addresses/{id}', [AddressController::class, 'update'])->name('addresses.update');
                Route::patch('/addresses/{id}/default', [AddressController::class, 'setDefault'])->name('addresses.set-default');
                Route::delete('/addresses/{id}', [AddressController::class, 'destroy'])->name('addresses.destroy');
                Route::get('/addresses/psgc/municipalities/{provinceCode}', [AddressController::class, 'getMunicipalities'])->name('addresses.psgc.municipalities');
                Route::get('/addresses/psgc/barangays/{municipalityCode}', [AddressController::class, 'getBarangays'])->name('addresses.psgc.barangays');

                Route::get('/messages', [BuyerMessageController::class, 'index'])->name('messages.buyer-messages');
                Route::get('/messages/contacts', [BuyerMessageController::class, 'fetchContacts'])->name('messages.contacts');
                Route::get('/messages/fetch/{contactId}', [BuyerMessageController::class, 'fetchMessages'])->whereNumber('contactId')->name('messages.fetch');
                Route::post('/messages', [BuyerMessageController::class, 'store'])->middleware('throttle:60,1')->name('messages.store');
                Route::patch('/messages/{contactId}/read', [BuyerMessageController::class, 'markAsRead'])->whereNumber('contactId')->name('messages.read');

                Route::get('/help-center', [HelpCenterController::class, 'index'])->name('help-center.index');
                Route::get('/help-center/messages', [HelpCenterController::class, 'messages'])->name('help-center.messages');
                Route::post('/help-center/messages', [HelpCenterController::class, 'store'])->middleware('throttle:60,1')->name('help-center.store');
                Route::patch('/help-center/read', [HelpCenterController::class, 'markAsRead'])->name('help-center.read');

                Route::get('/orders', [OrderController::class, 'index'])->name('orders');
                Route::get('/orders/{sellerOrder}', [OrderController::class, 'show'])->name('orders.show');
                Route::get('/orders/{sellerOrder}/invoice', [OrderController::class, 'downloadInvoice'])->name('orders.invoice');
                Route::get('/orders/{sellerOrder}/live-tracking', [OrderController::class, 'liveTracking'])->name('orders.live-tracking');
                Route::get('/orders/{sellerOrder}/delivery-chat', [OrderController::class, 'fetchDeliveryChat'])->name('orders.delivery-chat.fetch');
                Route::post('/orders/{sellerOrder}/delivery-chat', [OrderController::class, 'sendDeliveryChat'])->middleware('throttle:60,1')->name('orders.delivery-chat.send');
                Route::post('/orders/{sellerOrder}/cancel', [OrderController::class, 'cancelOrder'])->name('orders.cancel');
                Route::post('/orders/{sellerOrder}/switch-to-cod', [OrderController::class, 'switchToCod'])->name('orders.switch-to-cod');
                Route::post('/orders/{sellerOrder}/cancel-flagged-payment', [OrderController::class, 'cancelFlaggedPaymentOrder'])->name('orders.cancel-flagged-payment');
                Route::post('/orders/{sellerOrder}/confirm-receipt', [OrderController::class, 'confirmReceipt'])->name('orders.confirm-receipt');
                Route::post('/orders/{sellerOrder}/report-dispute', [ReturnRequestController::class, 'legacyReport'])->name('orders.report-dispute');
                Route::post('/orders/{sellerOrder}/escalate-dispute', [ReturnRequestController::class, 'legacyEscalate'])->name('orders.escalate-dispute');

                Route::get('/orders/{sellerOrder}/return', [ReturnRequestController::class, 'create'])->name('orders.return.create');
                Route::post('/orders/{sellerOrder}/return', [ReturnRequestController::class, 'store'])->middleware('throttle:10,1')->name('orders.return.store');
                Route::get('/returns/{returnRequest}', [ReturnRequestController::class, 'show'])->name('returns.show');
                Route::post('/returns/{returnRequest}/escalate', [ReturnRequestController::class, 'escalate'])->middleware('throttle:10,1')->name('returns.escalate');

                Route::get('/orders/{sellerOrder}/rate', [OrderController::class, 'showRatePage'])->name('orders.rate.show');
                Route::post('/orders/{sellerOrder}/rate', [OrderController::class, 'submitRatings'])->name('orders.rate.submit');
                Route::post('/orders/{sellerOrder}/buy-again', [OrderController::class, 'buyAgain'])->name('orders.buy-again');
                Route::patch('/reviews/{review}', [ReviewController::class, 'update'])->name('reviews.update');

                Route::get('/coupons', [VoucherController::class, 'index'])->name('coupons');
                Route::post('/coupons/claim', [VoucherController::class, 'claim'])->middleware('throttle:30,1')->name('coupons.claim');

                Route::get('/points', [PointsRewardController::class, 'index'])->name('points');
                Route::post('/points/wallet-topup', [PointsRewardController::class, 'topupWallet'])->middleware('throttle:15,1')->name('points.wallet-topup');
                Route::post('/points/spin', [PointsRewardController::class, 'spin'])->middleware('throttle:30,1')->name('points.spin');
                Route::post('/points/daily-chest', [PointsRewardController::class, 'openChest'])->middleware('throttle:30,1')->name('points.chest');
                Route::post('/points/redeem', [PointsRewardController::class, 'redeemVoucher'])->middleware('throttle:30,1')->name('points.redeem');

                Route::get('/wishlist', [WishlistController::class, 'index'])->name('wishlist');
                Route::post('/wishlist/toggle/{product}', [WishlistController::class, 'toggle'])->middleware('throttle:60,1')->name('wishlist.toggle');
                Route::delete('/wishlist/{product}', [WishlistController::class, 'destroy'])->name('wishlist.destroy');

                Route::redirect('/change-password', '/user/account-management')->name('change-password');
                Route::get('/recently-viewed', [ProductController::class, 'recentlyViewed'])->name('recently-viewed');
                Route::get('/following', [SellerController::class, 'followingList'])->name('following');
                Route::get('/seller-reports', [SellerReportController::class, 'index'])->name('seller-reports');
            });

            Route::get('/identity-verification', IdentityVerificationFlow::class)->name('user.identity-verification');

            Route::patch('/user/notifications/mark-all-read', function () {
                auth()->user()->unreadNotifications->markAsRead();
                return back();
            })->name('user.notifications.markAllRead');

            Route::patch('/user/notifications/{id}/read', function ($id) {
                auth()->user()->notifications()->findOrFail($id)->markAsRead();
                return back();
            })->name('user.notifications.markRead');

            Route::get('/user/notifications/{id}/open', function ($id) {
                $notification = auth()->user()->notifications()->findOrFail($id);
                $notification->markAsRead();
                $target = parse_url((string) ($notification->data['url'] ?? ''));
                if ($target && (empty($target['host']) || $target['host'] === request()->getHost())) {
                    return redirect(($target['path'] ?? '/') . (isset($target['query']) ? '?' . $target['query'] : ''));
                }
                return redirect()->route('user.orders');
            })->name('user.notifications.open');
        });
    });
});

require __DIR__ . '/rider.php';