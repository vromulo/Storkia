<?php

namespace App\Providers;

use App\Services\Chat\ChatBadgeService;
use App\Services\Chat\ChatEligibilityService;
use App\Services\Chat\ChatPresenter;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

/**
 * Wiring for the chat system.
 *
 * Besides registering the chat services as request-scoped bindings, it shares one
 * variable with every layout component that shows a chat link:
 *
 *   $chatBadges = ['messages' => int, 'support' => int, 'total' => int]
 *
 * It is deliberately a separate variable (and a separate provider) so the
 * existing sidebar / navbar composers in AppServiceProvider stay untouched and
 * the two never depend on the order they run in.
 */
class ChatServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(ChatBadgeService::class);
        $this->app->scoped(ChatEligibilityService::class);
        $this->app->scoped(ChatPresenter::class);
    }

    public function boot(): void
    {
        // Storefront navbar: the buyer's "My Messages" / "Help Center" links and,
        // for a seller browsing the storefront, their own chat links.
        View::composer('components.storefront.navbar', function ($view) {
            $view->with('chatBadges', app(ChatBadgeService::class)->forUser(Auth::guard('web')->user()));
        });

        View::composer(['components.seller.sidebar', 'components.logistics.sidebar'], function ($view) {
            $view->with('chatBadges', app(ChatBadgeService::class)->forUser(Auth::guard('web')->user()));
        });

        View::composer('components.admin.sidebar', function ($view) {
            $view->with('chatBadges', app(ChatBadgeService::class)->forAdmin(Auth::guard('admin')->user()));
        });
    }
}