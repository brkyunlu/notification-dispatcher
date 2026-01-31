<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class ModuleServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register module service bindings here
        // Example:
        // $this->app->bind(ProviderInterface::class, WebhookProvider::class);
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
