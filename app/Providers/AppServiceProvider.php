<?php

namespace App\Providers;

use App\Models\Admin;
use App\Models\BuyerAccountSanction;
use App\Models\Courier;
use App\Models\Rider;
use App\Models\User;
use App\Observers\AccountSecurityObserver;
use App\Services\StorkSchemaBootstrapService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
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

        Blade::anonymousComponentPath(resource_path('views/layouts'), 'layouts');

        $this->registerRateLimiters();
        $this->registerObservers();

        if (! $this->app->runningInConsole()) {
            StorkSchemaBootstrapService::ensureTablesExist();
        }
    }

    /**
     * Limits are read from config/security.php so they can be tuned per
     * environment without touching code.
     */
    protected function registerRateLimiters(): void
    {
        // General API limiter, used by $middleware->throttleApi('api').
        // The key includes the model class so a Rider with id 5 and a Buyer
        // with id 5 never share one bucket.
        RateLimiter::for('api', function (Request $request) {
            $user = $request->user();

            $key = $user
                ? 'api:'.$user::class.':'.$user->getAuthIdentifier()
                : 'api:ip:'.$request->ip();

            return Limit::perMinute((int) config('security.rate_limits.api_per_minute', 120))->by($key);
        });

        // Login / OTP / register endpoints of the API. Two buckets: attempts
        // against one email from one IP, and total attempts from one IP.
        RateLimiter::for('api-auth', function (Request $request) {
            $email = Str::lower(trim((string) $request->input('email', '')));

            return [
                Limit::perMinute((int) config('security.rate_limits.api_auth_per_identity', 5))
                    ->by('api-auth:identity:'.$email.'|'.$request->ip()),
                Limit::perMinute((int) config('security.rate_limits.api_auth_per_ip', 20))
                    ->by('api-auth:ip:'.$request->ip()),
            ];
        });
    }

    /**
     * Revokes sessions / tokens whenever a password changes or an account is
     * banned or suspended, from any code path.
     */
    protected function registerObservers(): void
    {
        User::observe(AccountSecurityObserver::class);
        Admin::observe(AccountSecurityObserver::class);
        Rider::observe(AccountSecurityObserver::class);
        Courier::observe(AccountSecurityObserver::class);
        BuyerAccountSanction::observe(AccountSecurityObserver::class);
    }
}