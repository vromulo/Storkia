<?php

namespace App\Providers;

use App\Http\Controllers\Buyer\AnnouncementController;
use App\Http\Controllers\Buyer\BundleDealController;
use App\Models\Admin;
use App\Models\BuyerAccountSanction;
use App\Models\BuyerBanAppeal;
use App\Models\CodCollection;
use App\Models\CodRemittance;
use App\Models\Courier;
use App\Models\IdentityVerification;
use App\Models\LogisticsApplication;
use App\Models\Message;
use App\Models\OrderPaymentSubmission;
use App\Models\Product;
use App\Models\Rider;
use App\Models\SellerApplication;
use App\Models\SellerOrder;
use App\Models\SellerRtsClaim;
use App\Models\Shipment;
use App\Models\User;
use App\Models\WithdrawalRequest;
use App\Observers\AccountSecurityObserver;
use App\Services\CourierMobileSchemaService;
use App\Services\RiderMobileSchemaService;
use App\Services\StorkSchemaBootstrapService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }

    public function boot(): void
    {
        if ($this->app->environment('production') || request()->header('X-Forwarded-Proto') === 'https') {
            URL::forceScheme('https');
        }

        // Ensure Courier allowed statuses include active, on_delivery, and off_duty so Going Off Duty never revokes API tokens
        $existingCourierAllowed = (array) config('security.allowed_statuses.courier', []);
        config([
            'security.allowed_statuses.courier' => array_values(array_unique(array_merge(
                $existingCourierAllowed,
                ['active', 'Active', 'on_delivery', 'off_duty']
            ))),
        ]);

        Blade::anonymousComponentPath(resource_path('views/layouts'), 'layouts');

        $this->registerRateLimiters();

        if (! $this->app->runningInConsole()) {
            StorkSchemaBootstrapService::ensureTablesExist();
            RiderMobileSchemaService::ensureTablesExist();
            CourierMobileSchemaService::ensureTablesExist();
        }

        User::observe(AccountSecurityObserver::class);
        Admin::observe(AccountSecurityObserver::class);
        Rider::observe(AccountSecurityObserver::class);
        Courier::observe(AccountSecurityObserver::class);
        BuyerAccountSanction::observe(AccountSecurityObserver::class);

        $this->registerAdditiveStorefrontRoutes();

        View::composer('components.storefront.navbar', function ($view) {
            $unreadAnnouncementsCount = 0;
            try {
                if (Schema::hasTable('platform_announcements')) {
                    $annQuery = DB::table('platform_announcements')->where('is_active', true);
                    if (Schema::hasColumn('platform_announcements', 'target_audience')) {
                        $annQuery->whereIn('target_audience', ['all', 'buyers']);
                    }
                    $activeIds = $annQuery->pluck('id')->map(fn ($id) => (int) $id)->all();
                    if (! empty($activeIds)) {
                        if (Auth::check() && Schema::hasTable('buyer_announcement_reads')) {
                            $readIds = DB::table('buyer_announcement_reads')
                                ->where('user_id', Auth::id())
                                ->whereIn('announcement_id', $activeIds)
                                ->pluck('announcement_id')
                                ->map(fn ($id) => (int) $id)
                                ->all();
                            $unreadAnnouncementsCount = count(array_diff($activeIds, $readIds));
                        } else {
                            $unreadAnnouncementsCount = count($activeIds);
                        }
                    }
                }
            } catch (\Throwable $e) {
            }
            $view->with('unreadAnnouncementsCount', $unreadAnnouncementsCount);
        });

        View::composer('components.admin.sidebar', function ($view) {
            $badges = [
                'apps_identity'  => 0,
                'apps_sellers'   => 0,
                'apps_logistics' => 0,
                'apps_total'     => 0,
                'appeals'        => 0,
                'compliance'     => 0,
                'complaints'     => 0,
                'commissions'    => 0,
            ];

            try {
                if (Schema::hasTable('identity_verifications')) {
                    $badges['apps_identity'] = IdentityVerification::where('status', 'pending')->count();
                }
                if (Schema::hasTable('seller_applications')) {
                    $badges['apps_sellers'] = SellerApplication::where('status', 'pending')->count();
                }
                if (Schema::hasTable('logistics_applications')) {
                    $badges['apps_logistics'] = LogisticsApplication::where('status', 'pending')->count();
                }
                $badges['apps_total'] = $badges['apps_identity'] + $badges['apps_sellers'] + $badges['apps_logistics'];

                if (Schema::hasTable('buyer_ban_appeals')) {
                    $badges['appeals'] = BuyerBanAppeal::where('status', 'pending')->count();
                }

                if (Schema::hasTable('products') && Schema::hasTable('product_approvals')) {
                    $pendingProducts = Product::whereHas('approval', fn ($q) => $q->where('status', 'Pending'))->count();
                    $pendingVariants = Schema::hasColumn('products', 'has_pending_variants')
                        ? Product::where('has_pending_variants', true)->count()
                        : 0;
                    $badges['compliance'] = $pendingProducts + $pendingVariants;
                }

                if (Schema::hasTable('seller_orders')) {
                    $unresolvedDisputes = SellerOrder::where(function ($q) {
                        $q->where('status', 'disputed')->orWhere('has_unresolved_dispute', true);
                    })->count();

                    $pendingRtsClaims = Schema::hasTable('seller_rts_claims')
                        ? SellerRtsClaim::where('reimbursement_status', 'pending_admin_review')->count()
                        : 0;

                    $badges['complaints'] = $unresolvedDisputes + $pendingRtsClaims;
                }

                $pendingWithdrawals = Schema::hasTable('withdrawal_requests')
                    ? WithdrawalRequest::where('status', 'pending')->count()
                    : 0;
                $pendingGcash = Schema::hasTable('order_payment_submissions')
                    ? OrderPaymentSubmission::where('status', 'submitted')->count()
                    : 0;
                $pendingArrears = Schema::hasTable('seller_commission_remittances')
                    ? DB::table('seller_commission_remittances')->where('status', 'pending')->count()
                    : 0;

                $badges['commissions'] = $pendingWithdrawals + $pendingGcash + $pendingArrears;
            } catch (\Throwable $e) {
            }

            $view->with('adminSidebarBadges', $badges);
        });

        View::composer('components.seller.sidebar', function ($view) {
            $badges = [
                'orders_new'        => 0,
                'orders_pending'    => 0,
                'orders_to_process' => 0,
                'orders_to_ship'    => 0,
                'orders_disputed'   => 0,
                'orders_total'      => 0,
                'low_stock'         => 0,
                'disapproved_prods' => 0,
                'products_total'    => 0,
                'unread_messages'   => 0,
                'unreplied_reviews' => 0,
            ];

            try {
                $user = Auth::guard('web')->user();
                $profile = $user?->sellerProfile;

                if ($user && $profile) {
                    if (Schema::hasTable('seller_orders')) {
                        $badges['orders_new'] = SellerOrder::where('seller_profile_id', $profile->id)
                            ->where('status', 'pending_seller_acceptance')
                            ->count();
                        $badges['orders_pending'] = SellerOrder::where('seller_profile_id', $profile->id)
                            ->where('status', 'pending_buyer_response')
                            ->count();
                        $badges['orders_to_process'] = SellerOrder::where('seller_profile_id', $profile->id)
                            ->whereIn('status', ['accepted', 'preparing'])
                            ->count();
                        $badges['orders_to_ship'] = SellerOrder::where('seller_profile_id', $profile->id)
                            ->where('status', 'ready_for_pickup')
                            ->count();
                        $badges['orders_disputed'] = SellerOrder::where('seller_profile_id', $profile->id)
                            ->where(function ($q) {
                                $q->where('status', 'disputed')->orWhere('has_unresolved_dispute', true);
                            })
                            ->count();

                        $badges['orders_total'] = $badges['orders_new']
                            + $badges['orders_pending']
                            + $badges['orders_to_process']
                            + $badges['orders_to_ship']
                            + $badges['orders_disputed'];
                    }

                    if (Schema::hasTable('products')) {
                        $badges['low_stock'] = Product::where('user_id', $user->id)
                            ->where('stock_quantity', '<=', 5)
                            ->count();
                        $badges['disapproved_prods'] = Product::where('user_id', $user->id)
                            ->whereHas('approval', fn ($q) => $q->where('status', 'Disapproved'))
                            ->count();
                        $badges['products_total'] = $badges['low_stock'] + $badges['disapproved_prods'];
                    }

                    if (Schema::hasTable('messages')) {
                        $badges['unread_messages'] = Message::where('receiver_id', $user->id)
                            ->where('is_read', false)
                            ->count();
                    }

                    if (Schema::hasTable('order_item_reviews')) {
                        $badges['unreplied_reviews'] = DB::table('order_item_reviews')
                            ->where('seller_profile_id', $profile->id)
                            ->whereNull('seller_reply')
                            ->count();
                    }
                }
            } catch (\Throwable $e) {
            }

            $view->with('sellerSidebarBadges', $badges);
        });

        View::composer('components.logistics.sidebar', function ($view) {
            $badges = [
                'shipments_to_pickup'      => 0,
                'shipments_in_transit_hub' => 0,
                'shipments_at_hub'         => 0,
                'shipments_failed'         => 0,
                'shipments_action_total'   => 0,
                'pending_remittances'      => 0,
                'short_remittances'        => 0,
            ];

            try {
                $user = Auth::guard('web')->user();
                $profile = $user?->logisticsProfile;

                if ($user && $profile) {
                    if (Schema::hasTable('shipments')) {
                        $badges['shipments_to_pickup'] = Shipment::where('logistics_profile_id', $profile->id)
                            ->whereIn('status', ['pickup_requested', 'courier_assigned', 'awaiting_seller_drop_off', 'awaiting_seller_dropoff'])
                            ->count();
                        $badges['shipments_in_transit_hub'] = Shipment::where('logistics_profile_id', $profile->id)
                            ->whereIn('status', ['picked_up_from_seller', 'in_transit_to_hub'])
                            ->count();
                        $badges['shipments_at_hub'] = Shipment::where('logistics_profile_id', $profile->id)
                            ->whereIn('status', ['received_at_hub', 'scheduled_redelivery'])
                            ->count();
                        $badges['shipments_failed'] = Shipment::where('logistics_profile_id', $profile->id)
                            ->where('status', 'delivery_failed')
                            ->count();

                        $badges['shipments_action_total'] = $badges['shipments_to_pickup']
                            + $badges['shipments_in_transit_hub']
                            + $badges['shipments_at_hub']
                            + $badges['shipments_failed'];
                    }

                    if (Schema::hasTable('cod_remittances')) {
                        $badges['pending_remittances'] = CodRemittance::where('logistics_profile_id', $profile->id)
                            ->where('status', 'submitted')
                            ->count();
                    }

                    if (Schema::hasTable('cod_collections')) {
                        $hasRemittedCol = Schema::hasColumn('cod_collections', 'remitted_amount');
                        $badges['short_remittances'] = CodCollection::where('logistics_profile_id', $profile->id)
                            ->where(function ($q) use ($hasRemittedCol) {
                                if ($hasRemittedCol) {
                                    $q->where(function ($sq) {
                                        $sq->whereNotNull('remitted_amount')
                                           ->whereColumn('remitted_amount', '<', 'expected_amount');
                                    })->orWhereColumn('collected_amount', '<', 'expected_amount');
                                } else {
                                    $q->whereColumn('collected_amount', '<', 'expected_amount');
                                }
                            })
                            ->count();
                    }
                }
            } catch (\Throwable $e) {
            }

            $view->with('logisticsSidebarBadges', $badges);
        });
    }

    protected function registerRateLimiters(): void
    {
        RateLimiter::for('api', function (Request $request) {
            $user = $request->user();
            $key = $user
                ? 'api:' . $user::class . ':' . $user->getAuthIdentifier()
                : 'api:ip:' . $request->ip();

            return Limit::perMinute((int) config('security.rate_limits.api_per_minute', 120))->by($key);
        });

        RateLimiter::for('api-auth', function (Request $request) {
            $email = Str::lower(trim((string) $request->input('email', '')));

            return [
                Limit::perMinute((int) config('security.rate_limits.api_auth_per_identity', 5))
                    ->by('api-auth:identity:' . $email . '|' . $request->ip()),
                Limit::perMinute((int) config('security.rate_limits.api_auth_per_ip', 20))
                    ->by('api-auth:ip:' . $request->ip()),
            ];
        });
    }

    protected function registerAdditiveStorefrontRoutes(): void
    {
        Route::middleware('web')->group(function () {
            if (! Route::has('bundles.index')) {
                Route::get('/bundles', [BundleDealController::class, 'index'])->name('bundles.index');
            }
            if (! Route::has('bundles.add-to-cart')) {
                Route::post('/bundles/{id}/add-to-cart', [BundleDealController::class, 'addBundleToCart'])
                    ->middleware('auth')
                    ->name('bundles.add-to-cart');
            }
            if (! Route::has('announcements.index')) {
                Route::get('/announcements', [AnnouncementController::class, 'index'])->name('announcements.index');
            }
            if (! Route::has('announcements.mark-read')) {
                Route::post('/announcements/{id}/read', [AnnouncementController::class, 'markAsRead'])
                    ->middleware('auth')
                    ->name('announcements.mark-read');
            }
            if (! Route::has('announcements.mark-all-read')) {
                Route::post('/announcements/read-all', [AnnouncementController::class, 'markAllAsRead'])
                    ->middleware('auth')
                    ->name('announcements.mark-all-read');
            }
        });
    }
}