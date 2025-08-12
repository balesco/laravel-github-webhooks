<?php

namespace Laravel\GitHubWebhooks\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Laravel\GitHubWebhooks\Models\GitHubWebhook;

class ValidateWebhookConfigCommand extends Command
{
    protected $signature = 'github-webhooks:validate
                          {--url= : Test with a specific webhook URL}';

    protected $description = 'Validate GitHub webhook configuration and connectivity';

    public function handle(): int
    {
        $this->info('🔍 Validating GitHub webhook configuration...');
        $this->newLine();

        $allValid = true;

        // Check configuration
        $allValid &= $this->validateConfiguration();
        $this->newLine();

        // Check database
        $allValid &= $this->validateDatabase();
        $this->newLine();

        // Check routes
        $allValid &= $this->validateRoutes();
        $this->newLine();

        // Check webhook URL if provided
        if ($url = $this->option('url')) {
            $allValid &= $this->validateWebhookUrl($url);
            $this->newLine();
        }

        // Show summary
        $this->showSummary($allValid);

        return $allValid ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Validate configuration settings.
     */
    private function validateConfiguration(): bool
    {
        $this->info('📋 Configuration Validation:');
        
        $isValid = true;
        $config = config('github-webhooks');

        // Check if config is loaded
        if (empty($config)) {
            $this->error('  ❌ Configuration not found. Run: php artisan vendor:publish --tag=github-webhooks-config');
            return false;
        } else {
            $this->line('  ✅ Configuration file loaded');
        }

        // Check secret
        $secret = config('github-webhooks.secret');
        if (empty($secret)) {
            $this->warn('  ⚠️  No webhook secret configured (GITHUB_WEBHOOK_SECRET)');
            $this->line('     Run: php artisan github-webhooks:generate-secret');
            $isValid = false;
        } else {
            $secretLength = strlen($secret);
            if ($secretLength < 16) {
                $this->warn("  ⚠️  Webhook secret is too short ({$secretLength} chars, recommended: 32+)");
                $isValid = false;
            } else {
                $this->line("  ✅ Webhook secret configured ({$secretLength} characters)");
            }
        }

        // Check route prefix
        $routePrefix = config('github-webhooks.route_prefix', 'webhooks');
        $this->line("  ✅ Route prefix: /{$routePrefix}");

        // Check storage setting
        $storeWebhooks = config('github-webhooks.store_webhooks', true);
        $this->line('  ✅ Store webhooks: ' . ($storeWebhooks ? 'enabled' : 'disabled'));

        // Check handlers
        $handlers = config('github-webhooks.handlers', []);
        $handlerCount = collect($handlers)->flatten()->count();
        $this->line("  ✅ Configured handlers: {$handlerCount}");

        return $isValid;
    }

    /**
     * Validate database setup.
     */
    private function validateDatabase(): bool
    {
        $this->info('🗄️  Database Validation:');
        
        try {
            // Check if table exists
            $tableExists = Schema::hasTable('git_hub_webhooks');
            if (!$tableExists) {
                $this->error('  ❌ git_hub_webhooks table not found. Run: php artisan migrate');
                return false;
            } else {
                $this->line('  ✅ git_hub_webhooks table exists');
            }

            // Check webhook count
            $webhookCount = GitHubWebhook::count();
            $this->line("  ✅ Total webhooks in database: {$webhookCount}");

            // Check recent activity
            $recentCount = GitHubWebhook::where('created_at', '>', now()->subDays(7))->count();
            $this->line("  ✅ Recent webhooks (7 days): {$recentCount}");

            return true;

        } catch (\Exception $e) {
            $this->error('  ❌ Database connection failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Validate route registration.
     */
    private function validateRoutes(): bool
    {
        $this->info('🛣️  Route Validation:');
        
        $routePrefix = config('github-webhooks.route_prefix', 'webhooks');
        $expectedRoute = "{$routePrefix}/github";

        // Get all registered routes
        $routes = collect(Route::getRoutes())->map(function ($route) {
            return [
                'uri' => $route->uri(),
                'methods' => $route->methods(),
                'name' => $route->getName(),
            ];
        });

        // Check if webhook route exists
        $webhookRoute = $routes->first(function ($route) use ($expectedRoute) {
            return $route['uri'] === $expectedRoute && in_array('POST', $route['methods']);
        });

        if ($webhookRoute) {
            $this->line("  ✅ Webhook route registered: POST /{$expectedRoute}");
            if ($webhookRoute['name']) {
                $this->line("  ✅ Route name: {$webhookRoute['name']}");
            }
            return true;
        } else {
            $this->error("  ❌ Webhook route not found: POST /{$expectedRoute}");
            $this->line('     Check if GitHubWebhooksServiceProvider is registered');
            return false;
        }
    }

    /**
     * Validate webhook URL connectivity.
     */
    private function validateWebhookUrl(string $url): bool
    {
        $this->info('🌐 URL Connectivity Validation:');
        
        try {
            // Parse URL
            $parsedUrl = parse_url($url);
            if (!$parsedUrl || !isset($parsedUrl['scheme'], $parsedUrl['host'])) {
                $this->error("  ❌ Invalid URL format: {$url}");
                return false;
            }

            $this->line("  ✅ URL format valid: {$url}");

            // Check if URL is reachable (basic connectivity test)
            $context = stream_context_create([
                'http' => [
                    'method' => 'HEAD',
                    'timeout' => 10,
                ],
            ]);

            $headers = @get_headers($url, false, $context);
            if ($headers === false) {
                $this->warn("  ⚠️  Could not reach URL (this might be normal if behind authentication)");
                return true; // Don't fail validation for this
            }

            $statusCode = (int) substr($headers[0], 9, 3);
            if ($statusCode >= 200 && $statusCode < 500) {
                $this->line("  ✅ URL reachable (HTTP {$statusCode})");
                return true;
            } else {
                $this->warn("  ⚠️  Unexpected HTTP status: {$statusCode}");
                return true; // Don't fail validation for this
            }

        } catch (\Exception $e) {
            $this->warn("  ⚠️  URL test failed: " . $e->getMessage());
            return true; // Don't fail validation for connectivity issues
        }
    }

    /**
     * Show validation summary.
     */
    private function showSummary(bool $allValid): void
    {
        $this->newLine();
        
        if ($allValid) {
            $this->info('🎉 All validations passed! Your GitHub webhook setup looks good.');
            $this->newLine();
            $this->info('Next steps:');
            $this->line('1. Configure your GitHub webhook with this URL:');
            $this->line('   ' . url(config('github-webhooks.route_prefix', 'webhooks') . '/github'));
            $this->line('2. Set the Content-Type to: application/json');
            $this->line('3. Add your webhook secret if configured');
            $this->line('4. Select the events you want to receive');
        } else {
            $this->error('❌ Some validations failed. Please fix the issues above.');
            $this->newLine();
            $this->info('Common solutions:');
            $this->line('• Run: php artisan vendor:publish --tag=github-webhooks-config');
            $this->line('• Run: php artisan migrate');
            $this->line('• Run: php artisan github-webhooks:generate-secret');
            $this->line('• Check that GitHubWebhooksServiceProvider is registered');
        }
    }
}
