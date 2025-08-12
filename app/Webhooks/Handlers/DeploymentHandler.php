<?php

namespace App\Webhooks\Handlers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Laravel\GitHubWebhooks\Contracts\WebhookHandler;
use Laravel\GitHubWebhooks\Exceptions\DeploymentFailedException;
use Laravel\GitHubWebhooks\Service\DeploymentService;

class DeploymentHandler implements WebhookHandler
{
    /**
     * The deployment service instance.
     */
    protected DeploymentService $deploymentService;

    public function __construct(DeploymentService $deploymentService)
    {
        $this->deploymentService = $deploymentService;
    }
    /**
     * Handle deployment-related GitHub events.
     */
    public function handle(string $event, array $payload, Request $request): mixed
    {
        if ($event === 'push') {
            return $this->handlePush($payload);
        }

        return null;
    }

    /**
     * Handle push events for automatic deployment.
     */
    private function handlePush(array $payload): array
    {
        $repository = $payload['repository']['full_name'] ?? 'unknown';
        $branch = str_replace('refs/heads/', '', $payload['ref'] ?? '');
        $commits = count($payload['commits'] ?? []);
        $pusher = $payload['pusher']['name'] ?? 'unknown';

        if (config('app.debug', false) === true) {
            Log::info("Push event received in local environment", [
                'repository' => $repository,
                'branch' => $branch,
                'commits' => $commits,
                'pusher' => $pusher,
            ]);
        }
        $response = [];

        if ($branch === config('github-webhooks.branch', 'main')) {
            try {
                $response = $this->deploymentService->deploy($payload);
                if (config('app.debug', false) === true)
                    Log::info("Deployment triggered for branch {$branch}", [
                        'repository' => $repository,
                        'commits' => $commits,
                    ]);
            } catch (DeploymentFailedException $e) {
                Log::error("Deployment failed", [
                    'repository' => $repository,
                    'branch' => $branch,
                    'error' => $e->getMessage(),
                ]);

                $response = [
                    'deployed' => false,
                    'reason' => 'Deployment service error',
                    'error' => $e->getMessage(),
                    'branch' => $branch,
                ];
            }
        }

        // Démarrer le processus de déploiement
        return $response ?: [
            'deployed' => false,
            'reason' => 'Branch not configured for deployment',
            'branch' => $branch,
        ];
    }
}
