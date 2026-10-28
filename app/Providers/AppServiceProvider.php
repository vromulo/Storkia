<?php

namespace App\Providers;

use App\Services\StorkSchemaBootstrapService;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

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

        if (! $this->app->runningInConsole()) {
            StorkSchemaBootstrapService::ensureTablesExist();
        }
    }
}