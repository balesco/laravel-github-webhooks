<?php

namespace Laravel\GitHubWebhooks\Handlers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Artisan;
use Laravel\GitHubWebhooks\Contracts\WebhookHandler;

class RepositoryUpdateHandler implements WebhookHandler
{
    /**
     * Handle repository update events from GitHub.
     */
    public function handle(string $event, array $payload, Request $request): mixed
    {
        if ($event === 'push') {
            return $this->handlePush($payload);
        }

        if ($event === 'pull_request') {
            return $this->handlePullRequest($payload);
        }

        return null;
    }

    /**
     * Handle push events.
     */
    private function handlePush(array $payload): array
    {
        $repository = $payload['repository']['full_name'] ?? 'unknown';
        $branch = str_replace('refs/heads/', '', $payload['ref'] ?? '');
        $cloneUrl = $payload['repository']['clone_url'] ?? null;

        // Only update for configured branches
        $allowedBranches = config('github-webhooks.auto_update_branches', ['main', 'master', 'develop']);
        
        if (!in_array($branch, $allowedBranches)) {
            Log::info("Push event ignored - branch not configured for auto-update", [
                'repository' => $repository,
                'branch' => $branch,
                'allowed_branches' => $allowedBranches
            ]);

            return [
                'updated' => false,
                'reason' => 'Branch not configured for auto-update',
                'branch' => $branch
            ];
        }

        return $this->updateRepository($repository, $branch, $cloneUrl, 'push');
    }

    /**
     * Handle pull request events.
     */
    private function handlePullRequest(array $payload): array
    {
        $action = $payload['action'] ?? 'unknown';
        
        // Only handle merged pull requests
        if ($action !== 'closed' || !($payload['pull_request']['merged'] ?? false)) {
            return [
                'updated' => false,
                'reason' => 'Pull request not merged',
                'action' => $action
            ];
        }

        $repository = $payload['repository']['full_name'] ?? 'unknown';
        $branch = $payload['pull_request']['base']['ref'] ?? 'main';
        $cloneUrl = $payload['repository']['clone_url'] ?? null;

        Log::info("Pull request merged, updating repository", [
            'repository' => $repository,
            'branch' => $branch,
            'pr_number' => $payload['pull_request']['number'] ?? 'unknown'
        ]);

        return $this->updateRepository($repository, $branch, $cloneUrl, 'pull_request_merged');
    }

    /**
     * Update the local repository.
     */
    private function updateRepository(string $repository, string $branch, ?string $cloneUrl, string $trigger): array
    {
        try {
            Log::info("Triggering repository update", [
                'repository' => $repository,
                'branch' => $branch,
                'trigger' => $trigger
            ]);

            $exitCode = Artisan::call('github-webhooks:update-repo', [
                'repository' => $repository,
                '--branch' => $branch,
                '--clone-url' => $cloneUrl,
                '--force' => true
            ]);

            if ($exitCode === 0) {
                $output = Artisan::output();
                
                Log::info("Repository updated successfully via webhook", [
                    'repository' => $repository,
                    'branch' => $branch,
                    'trigger' => $trigger
                ]);

                return [
                    'updated' => true,
                    'repository' => $repository,
                    'branch' => $branch,
                    'trigger' => $trigger,
                    'output' => trim($output)
                ];
            } else {
                $output = Artisan::output();
                
                Log::error("Repository update failed via webhook", [
                    'repository' => $repository,
                    'branch' => $branch,
                    'trigger' => $trigger,
                    'exit_code' => $exitCode,
                    'output' => $output
                ]);

                return [
                    'updated' => false,
                    'error' => 'Update command failed',
                    'exit_code' => $exitCode,
                    'output' => trim($output)
                ];
            }

        } catch (\Exception $e) {
            Log::error("Exception during repository update", [
                'repository' => $repository,
                'branch' => $branch,
                'trigger' => $trigger,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'updated' => false,
                'error' => $e->getMessage(),
                'exception' => get_class($e)
            ];
        }
    }
}
