# Laravel GitHub Webhooks Package

A comprehensive Laravel package for handling GitHub webhooks with database storage, signature validation, and event management.

## Installation

1. Add the package to your local `composer.json`:

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "./Packages/github-webhooks"
        }
    ],
    "require": {
        "laravel/github-webhooks": "*"
    }
}
```

2. Install the package:

```bash
composer require laravel/github-webhooks
```

3. Publish configuration and migrations:

```bash
php artisan vendor:publish --tag=github-webhooks-config
php artisan vendor:publish --tag=github-webhooks-migrations
```

4. Run migrations:

```bash
php artisan migrate
```

## Configuration

### Environment Variables

Add these variables to your `.env` file:

```env
# Secret for validating GitHub webhooks (optional but recommended)
GITHUB_WEBHOOK_SECRET=your-webhook-secret

# Route prefix (default: webhooks)
GITHUB_WEBHOOK_ROUTE_PREFIX=webhooks

# Store webhooks in database (default: true)
GITHUB_WEBHOOK_STORE=true
```

### GitHub Configuration

1. Go to your GitHub repository settings
2. Click "Webhooks" in the left menu
3. Click "Add webhook"
4. Configure:
   - **Payload URL**: `https://your-domain.com/webhooks/github`
   - **Content type**: `application/json`
   - **Secret**: Your secret (same as `GITHUB_WEBHOOK_SECRET`)
   - **Events**: Select the events you want to receive

## Usage

### Custom Handlers

Create handlers to process GitHub events:

```php
<?php

namespace App\Webhooks\Handlers;

use Illuminate\Http\Request;
use Laravel\GitHubWebhooks\Contracts\WebhookHandler;

class DeploymentHandler implements WebhookHandler
{
    public function handle(string $event, array $payload, Request $request): mixed
    {
        if ($event === 'push') {
            $branch = str_replace('refs/heads/', '', $payload['ref'] ?? '');
            
            if ($branch === 'main') {
                // Trigger deployment
                $this->triggerDeployment($payload);
            }
        }

        return ['deployed' => true];
    }

    private function triggerDeployment(array $payload): void
    {
        // Your deployment logic
    }
}
```

### Registering Handlers

In `config/github-webhooks.php`:

```php
'handlers' => [
    'push' => [
        App\Webhooks\Handlers\DeploymentHandler::class,
    ],
    'pull_request' => [
        App\Webhooks\Handlers\PullRequestHandler::class,
    ],
    // Handler for all events
    '*' => [
        App\Webhooks\Handlers\LogAllEventsHandler::class,
    ],
],
```

### Programmatic Usage

```php
use Laravel\GitHubWebhooks\GitHubWebhooks;

// Register a handler on the fly
GitHubWebhooks::on('push', function ($event, $payload, $request) {
    // Process push event
    return ['status' => 'processed'];
});

// Register handler for multiple events
GitHubWebhooks::on(['push', 'pull_request'], new MyCustomHandler());
```

### Event Listeners

```php
use Laravel\GitHubWebhooks\Events\GitHubWebhookReceived;

// In your EventServiceProvider
protected $listen = [
    GitHubWebhookReceived::class => [
        App\Listeners\HandleGitHubWebhook::class,
    ],
];
```

```php
<?php

namespace App\Listeners;

use Laravel\GitHubWebhooks\Events\GitHubWebhookReceived;

class HandleGitHubWebhook
{
    public function handle(GitHubWebhookReceived $event): void
    {
        $eventType = $event->eventType;
        $payload = $event->payload;
        $deliveryId = $event->deliveryId;

        // Your custom logic
    }
}
```

## Artisan Commands

The package provides several Artisan commands for managing GitHub webhooks:

### Secret Management

```bash
# Generate a new secure webhook secret
php artisan github-webhooks:generate-secret

# Show the secret without writing to .env
php artisan github-webhooks:generate-secret --show

# Force overwrite existing secret
php artisan github-webhooks:generate-secret --force

# Generate secret with custom length
php artisan github-webhooks:generate-secret --length=64
```

### Configuration Validation

```bash
# Validate complete configuration
php artisan github-webhooks:validate

# Test with specific URL
php artisan github-webhooks:validate --url=https://your-domain.com/webhooks/github
```

### Webhook Management

```bash
# List all webhooks
php artisan github-webhooks:list

# Filter by event type
php artisan github-webhooks:list --event=push

# Show only unprocessed webhooks
php artisan github-webhooks:list --unprocessed

# Show only processed webhooks
php artisan github-webhooks:list --processed

# Limit number of results
php artisan github-webhooks:list --limit=5

# Reprocess specific webhook
php artisan github-webhooks:reprocess 123
```

### Repository Management

```bash
# List all local repositories
php artisan github-webhooks:list-repos

# Update repository from GitHub
php artisan github-webhooks:update-repo owner/repo --branch=main --clone-url=https://github.com/owner/repo.git

# Force update (ignore local changes)
php artisan github-webhooks:update-repo owner/repo --force

# Use custom path
php artisan github-webhooks:list-repos --path=/custom/path
```

## Data Model

The package stores webhooks in the `git_hub_webhooks` table with these fields:

- `id`: Auto-incremented ID
- `event_type`: GitHub event type (push, pull_request, etc.)
- `delivery_id`: GitHub delivery ID
- `payload`: JSON webhook data
- `headers`: HTTP headers
- `processed_at`: Processing timestamp
- `created_at` / `updated_at`: Laravel timestamps

### Model Usage

```php
use Laravel\GitHubWebhooks\Models\GitHubWebhook;

// Get all unprocessed push webhooks
$webhooks = GitHubWebhook::eventType('push')
    ->unprocessed()
    ->get();

// Mark as processed
$webhook->update(['processed_at' => now()]);
```

## Security

### Signature Validation

The package automatically validates GitHub signatures if you configure a secret. This validation:

- Uses HMAC-SHA256
- Compares securely with `hash_equals()`
- Returns 401 error if signature is invalid

### Recommended Middleware

Add middleware in configuration:

```php
'middleware' => [
    'throttle:60,1', // Rate limiting
    'api',           // API middleware
],
```

## Supported GitHub Events

The package can handle all GitHub events, including:

- `push`: Commits pushed
- `pull_request`: Pull requests (opened, closed, synchronized, etc.)
- `issues`: Issues (opened, closed, labeled, etc.)
- `release`: Releases
- `deployment`: Deployments
- `workflow_run`: GitHub Actions runs
- And many more...

## Troubleshooting

### Check Logs

Webhooks are automatically logged. Check your Laravel logs:

```bash
tail -f storage/logs/laravel.log
```

### Test Webhooks

Use ngrok for local testing:

```bash
ngrok http 8000
```

Then configure the GitHub webhook URL with the ngrok URL.

### Webhook Not Received

1. Check that the URL is accessible from GitHub
2. Verify secret configuration
3. Check the "Recent Deliveries" tab in GitHub
4. Review error logs

## Contributing

This package is designed to be extensible. You can:

- Create custom handlers
- Extend the GitHubWebhook model
- Add custom middleware
- Contribute new features

## License

MIT