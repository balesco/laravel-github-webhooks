<?php

namespace Laravel\GitHubWebhooks\Handlers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Laravel\GitHubWebhooks\Contracts\WebhookHandler;

class PushHandler implements WebhookHandler
{
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
        $repositoryPath = base_path("../{$repository}");

        if (!is_dir($repositoryPath)) {
            // Clone repository if it doesn't exist
            $cloneUrl = $payload['repository']['clone_url'] ?? null;
            if ($cloneUrl) {
                exec("git clone {$cloneUrl} {$repositoryPath} 2>&1", $output, $returnCode);
                if ($returnCode !== 0) {
                    Log::error("Failed to clone repository", ['repository' => $repository, 'output' => $output]);
                }
            }
        } else {
            // Pull latest changes
            exec("cd {$repositoryPath} && git fetch origin && git reset --hard origin/{$branch} 2>&1", $output, $returnCode);
            if ($returnCode !== 0) {
                Log::error("Failed to update repository", ['repository' => $repository, 'branch' => $branch, 'output' => $output]);
            } else {
                Log::info("Repository updated successfully", ['repository' => $repository, 'branch' => $branch]);
            }
        }


        return [
            'processed' => true,
            'repository' => $repository,
            'branch' => $branch,
            'commits' => $commits,
        ];
    }
}
