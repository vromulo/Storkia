<?php

use App\Http\Middleware\ApplySessionLifetime;
use App\Http\Middleware\EnforceIdleTimeout;
use App\Http\Middleware\EnsureAccountActive;
use App\Http\Middleware\EnsureApiActor;
use App\Http\Middleware\EnsureLogisticsIsApproved;
use App\Http\Middleware\EnsureSellerIsApproved;
use App\Http\Middleware\EnsureUserRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
        then: function () {
            Route::middleware('web')
                ->group(base_path('routes/admin.php'));

            Route::middleware('web')
                ->group(base_path('routes/seller.php'));

            Route::middleware('web')
                ->group(base_path('routes/logistics.php'));

            Route::middleware('web')
                ->group(base_path('routes/courier.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role'               => EnsureUserRole::class,
            'seller.approved'    => EnsureSellerIsApproved::class,
            'logistics.approved' => EnsureLogisticsIsApproved::class,
            'account.active'     => EnsureAccountActive::class,
            'api.actor'          => EnsureApiActor::class,
        ]);

        // Global, and prepended so it runs BEFORE StartSession: gives rider and
        // courier URLs a longer session lifetime (config/security.php).
        $middleware->prepend(ApplySessionLifetime::class);

        // Runs on every web request (including Livewire updates) after the
        // session has started: idle timeout for admin, and live enforcement of
        // bans / suspensions for buyer, seller, logistics, rider and courier.
        $middleware->web(append: [
            EnforceIdleTimeout::class,
            EnsureAccountActive::class,
        ]);

        // General API rate limit. The "api" limiter itself is defined in
        // AppServiceProvider (config/security.php -> rate_limits).
        $middleware->throttleApi('api');

        // Where an unauthenticated visitor lands. Without this, Laravel sends
        // everyone to the buyer "login" route, even from /admin or /seller.
        $middleware->redirectGuestsTo(function (Request $request) {
            return match (true) {
                $request->is('admin', 'admin/*')     => route('admin.login'),
                $request->is('seller', 'seller/*')   => route('seller.login'),
                // Riders and couriers also sign in through the logistics portal.
                $request->is(
                    'logistics', 'logistics/*',
                    'rider', 'rider/*',
                    'courier', 'courier/*'
                )                                    => route('logistics.login'),
                default                              => route('login'),
            };
        });

        // Where an already signed-in user is sent when they open a guest-only
        // page (for example their own login form). Without this, Laravel sends
        // everyone to the buyer storefront.
        $middleware->redirectUsersTo(function (Request $request) {
            if ($request->is('admin', 'admin/*')) {
                return route('admin.dashboard');
            }

            return match (Auth::guard('web')->user()?->role) {
                'Seller'    => route('seller.seller-dashboard'),
                'Logistics' => route('logistics.logistics-dashboard'),
                default     => route('home'),
            };
        });

        // Trust every proxy by default (current behaviour). In production set
        // TRUSTED_PROXIES to your load balancer IP(s), comma separated, so
        // clients cannot spoof X-Forwarded-For and dodge login throttling.
        $trustedProxies = (string) env('TRUSTED_PROXIES', '*');

        $middleware->trustProxies(
            at: in_array($trustedProxies, ['*', '**'], true)
                ? $trustedProxies
                : array_map('trim', explode(',', $trustedProxies))
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();