<?php

namespace Laravel\GitHubWebhooks\Handlers;

use App\Services\DeploymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Laravel\GitHubWebhooks\Contracts\WebhookHandler;

class PullRequestHandler implements WebhookHandler
{
    protected DeploymentService $deploymentService;

    public function __construct(DeploymentService $deploymentService)
    {
        $this->deploymentService = $deploymentService;
    }
    /**
     * Handle a pull request event from GitHub.
     */
    public function handle(string $event, array $payload, Request $request): mixed
    {
        if ($event !== 'pull_request') {
            return null;
        }

        $branch = str_replace('refs/heads/', '', $payload['ref'] ?? '');
        $action = $payload['action'] ?? 'unknown';
        $repository = $payload['repository']['full_name'] ?? 'unknown';
        $prNumber = $payload['pull_request']['number'] ?? 'unknown';
        $prTitle = $payload['pull_request']['title'] ?? 'unknown';
        $author = $payload['pull_request']['user']['login'] ?? 'unknown';

        Log::info("Pull request event received", [
            'action' => $action,
            'repository' => $repository,
            'pr_number' => $prNumber,
            'pr_title' => $prTitle,
            'author' => $author,
        ]);

        // Add your custom logic here based on action
        switch ($action) {
            case 'opened':
                // Handle new PR
                break;
            case 'closed':
                // Handle closed PR
                if ($payload['pull_request']['merged'] ?? false) {
                    // PR was merged                    
                    Log::info("Pull request #{$prNumber} was merged");
                    if ($branch === config('github-webhooks.branch', 'main')) {
                        $this->deploymentService->deploy($payload);
                    }
                }
                break;
            case 'synchronize':
                // Handle PR updates (new commits)
                break;
            case 'review_requested':
                // Handle review requests
                break;
        }

        return [
            'processed' => true,
            'action' => $action,
            'repository' => $repository,
            'pr_number' => $prNumber,
        ];
    }
}
