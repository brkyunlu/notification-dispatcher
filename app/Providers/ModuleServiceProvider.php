<?php

namespace App\Providers;

use App\Modules\Delivery\Contracts\ProviderInterface;
use App\Modules\Delivery\Providers\WebhookProvider;
use Illuminate\Support\ServiceProvider;

class ModuleServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register provider interface binding
        $this->app->bind(ProviderInterface::class, WebhookProvider::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Load module routes if needed
        // $this->loadRoutesFrom(app_path('Modules/Notification/routes.php'));
    }
}
