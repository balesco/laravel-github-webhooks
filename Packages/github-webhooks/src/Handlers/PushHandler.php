<?php

namespace Laravel\GitHubWebhooks\Handlers;

use App\Services\DeploymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Laravel\GitHubWebhooks\Contracts\WebhookHandler;

class PushHandler implements WebhookHandler
{
    protected DeploymentService $deploymentService;

    public function __construct(DeploymentService $deploymentService)
    {
        $this->deploymentService = $deploymentService;
    }

    /**
     * Handle a push event from GitHub.
     */
    public function handle(string $event, array $payload, Request $request): mixed
    {
        if ($event !== 'push') {
            return null;
        }

        $repository = $payload['repository']['full_name'] ?? 'unknown';
        $branch = str_replace('refs/heads/', '', $payload['ref'] ?? '');
        $commits = count($payload['commits'] ?? []);
        $pusher = $payload['pusher']['name'] ?? 'unknown';

        Log::info("Push event received", [
            'repository' => $repository,
            'branch' => $branch,
            'commits' => $commits,
            'pusher' => $pusher,
        ]);

        // Add your custom logic here
        // For example:
        // - Trigger CI/CD pipeline
        // - Update deployment status
        // - Send notifications
        // - Update local repository
        // Update local repository

        if ($branch === config('github-webhooks.branch', 'main')) {
            $this->deploymentService->deploy($payload);
        }

        return [
            'processed' => true,
            'repository' => $repository,
            'branch' => $branch,
            'commits' => $commits,
        ];
    }
}
