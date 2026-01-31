<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register middleware aliases
        $router = $this->app['router'];
        $router->aliasMiddleware('api.rate.limit', \App\Modules\Observability\Middleware\ApiRateLimitMiddleware::class);
    }
}
