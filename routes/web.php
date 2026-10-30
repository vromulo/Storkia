<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Buyer\AuthController;
use App\Http\Controllers\Buyer\HomeController;
use App\Http\Controllers\Buyer\CategoryController;
use App\Http\Controllers\Buyer\ProductController;
use App\Http\Controllers\Buyer\SearchController;
use App\Http\Controllers\Buyer\AccountManagementController;
use App\Http\Controllers\Buyer\AddressController;
use App\Http\Controllers\Buyer\CartController;
use App\Http\Controllers\Buyer\CheckoutController;
use App\Http\Controllers\Buyer\OrderController;
use App\Http\Controllers\Buyer\ReviewController;
use App\Http\Controllers\Buyer\SellerController;
use App\Http\Controllers\Buyer\VoucherController;
use App\Http\Controllers\Buyer\WishlistController;
use App\Livewire\Buyer\IdentityVerificationFlow;
use App\Http\Controllers\Buyer\ProfileController;

Route::middleware('throttle:120,1')->group(function () {
    Route::view('/', 'buyer.landing-page')->name('landing');
    Route::get('/shop', [HomeController::class, 'index'])->name('home');
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

        Route::middleware('role:Buyer')->group(function () {
            Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
            Route::post('/cart', [CartController::class, 'store'])->middleware('throttle:60,1')->name('cart.store');
            Route::delete('/cart/{cart}', [CartController::class, 'destroy'])->name('cart.destroy');
            Route::patch('/cart/{cart}', [CartController::class, 'update'])->name('cart.update');

            Route::post('/checkout/buy-now', [CheckoutController::class, 'buyNow'])->middleware('throttle:30,1')->name('checkout.buy-now');
            Route::get('/checkout', [CheckoutController::class, 'index'])->name('checkout.index');
            Route::post('/checkout', [CheckoutController::class, 'store'])->middleware('throttle:20,1')->name('checkout.store');
            Route::get('/checkout/success/{order}', [CheckoutController::class, 'success'])->name('checkout.success');

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

                Route::get('/addresses', [AddressController::class, 'index'])->name('addresses');
                Route::post('/addresses', [AddressController::class, 'store'])->name('addresses.store');
                Route::put('/addresses/{id}', [AddressController::class, 'update'])->name('addresses.update');
                Route::delete('/addresses/{id}', [AddressController::class, 'destroy'])->name('addresses.destroy');
                Route::get('/addresses/psgc/municipalities/{provinceCode}', [AddressController::class, 'getMunicipalities'])->name('addresses.psgc.municipalities');
                Route::get('/addresses/psgc/barangays/{municipalityCode}', [AddressController::class, 'getBarangays'])->name('addresses.psgc.barangays');

                Route::get('/messages', [\App\Http\Controllers\Buyer\BuyerMessageController::class, 'index'])->name('messages.buyer-messages');
                Route::get('/messages/contacts', [\App\Http\Controllers\Buyer\BuyerMessageController::class, 'fetchContacts'])->name('messages.contacts');
                Route::get('/messages/fetch/{userId}', [\App\Http\Controllers\Buyer\BuyerMessageController::class, 'fetchMessages'])->name('messages.fetch');
                Route::post('/messages', [\App\Http\Controllers\Buyer\BuyerMessageController::class, 'store'])->middleware('throttle:60,1')->name('messages.store');
                Route::patch('/messages/{contactId}/read', [\App\Http\Controllers\Buyer\BuyerMessageController::class, 'markAsRead'])->name('messages.read');

                Route::get('/orders', [OrderController::class, 'index'])->name('orders');
                Route::get('/orders/{sellerOrder}', [OrderController::class, 'show'])->name('orders.show');
                Route::post('/orders/{sellerOrder}/cancel', [OrderController::class, 'cancelOrder'])->name('orders.cancel');
                Route::post('/orders/{sellerOrder}/switch-to-cod', [OrderController::class, 'switchToCod'])->name('orders.switch-to-cod');
                Route::post('/orders/{sellerOrder}/cancel-flagged-payment', [OrderController::class, 'cancelFlaggedPaymentOrder'])->name('orders.cancel-flagged-payment');
                Route::post('/orders/{sellerOrder}/confirm-receipt', [OrderController::class, 'confirmReceipt'])->name('orders.confirm-receipt');
                Route::post('/orders/{sellerOrder}/report-dispute', [OrderController::class, 'reportDispute'])->name('orders.report-dispute');
                Route::post('/orders/{sellerOrder}/escalate-dispute', [OrderController::class, 'escalateDispute'])->name('orders.escalate-dispute');
                Route::get('/orders/{sellerOrder}/rate', [OrderController::class, 'showRatePage'])->name('orders.rate.show');
                Route::post('/orders/{sellerOrder}/rate', [OrderController::class, 'submitRatings'])->name('orders.rate.submit');
                Route::post('/orders/{sellerOrder}/buy-again', [OrderController::class, 'buyAgain'])->name('orders.buy-again');

                Route::patch('/reviews/{review}', [ReviewController::class, 'update'])->name('reviews.update');

                Route::get('/coupons', [VoucherController::class, 'index'])->name('coupons');
                Route::post('/coupons/claim', [VoucherController::class, 'claim'])->middleware('throttle:30,1')->name('coupons.claim');

                Route::get('/wishlist', [WishlistController::class, 'index'])->name('wishlist');
                Route::post('/wishlist/toggle/{product}', [WishlistController::class, 'toggle'])->middleware('throttle:60,1')->name('wishlist.toggle');
                Route::delete('/wishlist/{product}', [WishlistController::class, 'destroy'])->name('wishlist.destroy');

                Route::view('/change-password', 'buyer.option.coming-soon', ['title' => 'Change Password'])->name('change-password');
                Route::view('/points', 'buyer.option.coming-soon', ['title' => 'My Points/Coins'])->name('points');
                Route::view('/recently-viewed', 'buyer.option.coming-soon', ['title' => 'Recently Viewed'])->name('recently-viewed');
                Route::get('/following', [SellerController::class, 'followingList'])->name('following');
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
        });
    });
});

require __DIR__ . '/rider.php';