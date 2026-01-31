<?php

namespace App\Providers;

use App\Modules\Auth\Commands\GenerateApiKeyCommand;
use App\Modules\Auth\Middleware\ApiKeyMiddleware;
use App\Modules\Delivery\Contracts\ProviderInterface;
use App\Modules\Delivery\Providers\WebhookProvider;
use Illuminate\Routing\Router;
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
        // Register middleware alias
        $router = $this->app->make(Router::class);
        $router->aliasMiddleware('api.key', ApiKeyMiddleware::class);

        // Register commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                GenerateApiKeyCommand::class,
            ]);
        }
    }
}
