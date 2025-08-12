<?php

namespace Laravel\GitHubWebhooks\Handlers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Laravel\GitHubWebhooks\Contracts\WebhookHandler;

class IssueHandler implements WebhookHandler
{
    /**
     * Handle an issue event from GitHub.
     */
    public function handle(string $event, array $payload, Request $request): mixed
    {
        if ($event !== 'issues') {
            return null;
        }

        $action = $payload['action'] ?? 'unknown';
        $repository = $payload['repository']['full_name'] ?? 'unknown';
        $issueNumber = $payload['issue']['number'] ?? 'unknown';
        $issueTitle = $payload['issue']['title'] ?? 'unknown';
        $author = $payload['issue']['user']['login'] ?? 'unknown';

        Log::info("Issue event received", [
            'action' => $action,
            'repository' => $repository,
            'issue_number' => $issueNumber,
            'issue_title' => $issueTitle,
            'author' => $author,
        ]);

        // Add your custom logic here based on action
        switch ($action) {
            case 'opened':
                // Handle new issue
                break;
            case 'closed':
                // Handle closed issue
                break;
            case 'reopened':
                // Handle reopened issue
                break;
            case 'assigned':
                // Handle issue assignment
                break;
            case 'labeled':
                // Handle label added
                break;
        }

        return [
            'processed' => true,
            'action' => $action,
            'repository' => $repository,
            'issue_number' => $issueNumber,
        ];
    }
}
