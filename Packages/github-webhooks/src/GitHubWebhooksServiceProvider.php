<?php

namespace Laravel\GitHubWebhooks;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;

class GitHubWebhooksServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/github-webhooks.php',
            'github-webhooks'
        );

        $this->app->singleton(GitHubWebhookHandler::class, function ($app) {
            return new GitHubWebhookHandler(
                $app['config']['github-webhooks']
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/github-webhooks.php' => config_path('github-webhooks.php'),
            ], 'github-webhooks-config');

            $this->publishes([
                __DIR__ . '/../database/migrations' => database_path('migrations'),
            ], 'github-webhooks-migrations');

            $this->commands([
                Commands\ListWebhooksCommand::class,
                Commands\ReprocessWebhookCommand::class,
                Commands\UpdateRepositoryCommand::class,
                Commands\ListRepositoriesCommand::class,
                Commands\GenerateWebhookSecretCommand::class,
                Commands\ValidateWebhookConfigCommand::class,
            ]);
        }

        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        $this->registerRoutes();
    }

    /**
     * Register the package routes.
     */
    protected function registerRoutes(): void
    {
        Route::group([
            'prefix' => config('github-webhooks.route_prefix', 'webhooks'),
            'middleware' => config('github-webhooks.middleware', []),
        ], function () {
            Route::post('/github', [GitHubWebhookController::class, 'handle'])
                ->name('github-webhooks.handle');
        });
    }
}
