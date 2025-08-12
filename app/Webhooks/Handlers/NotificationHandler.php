<?php

namespace App\Webhooks\Handlers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Laravel\GitHubWebhooks\Contracts\WebhookHandler;
use Laravel\GitHubWebhooks\Service\DeploymentService;

class NotificationHandler implements WebhookHandler
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
     * Handle GitHub events for notifications.
     */
    public function handle(string $event, array $payload, Request $request): mixed
    {
        $repository = $payload['repository']['full_name'] ?? 'unknown';

        switch ($event) {
            case 'pull_request':
                return $this->handlePullRequest($payload);

            case 'issues':
                return $this->handleIssue($payload);

            case 'push':
                return $this->handlePush($payload);

            default:
                Log::info("Event reçu : {$event}", ['repository' => $repository]);
                return ['event' => $event, 'notified' => false];
        }
    }

    /**
     * Handle pull request notifications.
     */
    private function handlePullRequest(array $payload): array
    {
        $action = $payload['action'] ?? 'unknown';
        $pr = $payload['pull_request'] ?? [];
        $repository = $payload['repository']['full_name'] ?? 'unknown';

        $message = match ($action) {
            'opened' => "🔄 Nouvelle Pull Request ouverte dans {$repository}",
            'closed' => $pr['merged']
                ? "✅ Pull Request mergée dans {$repository}"
                : "❌ Pull Request fermée dans {$repository}",
            'review_requested' => "👀 Review demandée sur une Pull Request dans {$repository}",
            'ready_for_review' => "📝 Pull Request prête pour review dans {$repository}",
            default => "🔄 Pull Request {$action} dans {$repository}",
        };

        $this->sendNotification($message, [
            'repository' => $repository,
            'pr_number' => $pr['number'] ?? 'unknown',
            'pr_title' => $pr['title'] ?? 'Sans titre',
            'author' => $pr['user']['login'] ?? 'unknown',
            'action' => $action,
        ]);

        return [
            'notified' => true,
            'action' => $action,
            'pr_number' => $pr['number'] ?? 'unknown',
        ];
    }

    /**
     * Handle issue notifications.
     */
    private function handleIssue(array $payload): array
    {
        $action = $payload['action'] ?? 'unknown';
        $issue = $payload['issue'] ?? [];
        $repository = $payload['repository']['full_name'] ?? 'unknown';

        $message = match ($action) {
            'opened' => "🐛 Nouveau ticket ouvert dans {$repository}",
            'closed' => "✅ Ticket fermé dans {$repository}",
            'reopened' => "🔄 Ticket rouvert dans {$repository}",
            'labeled' => "🏷️ Étiquette ajoutée au ticket dans {$repository}",
            default => "🎫 Ticket {$action} dans {$repository}",
        };

        $this->sendNotification($message, [
            'repository' => $repository,
            'issue_number' => $issue['number'] ?? 'unknown',
            'issue_title' => $issue['title'] ?? 'Sans titre',
            'author' => $issue['user']['login'] ?? 'unknown',
            'action' => $action,
        ]);

        return [
            'notified' => true,
            'action' => $action,
            'issue_number' => $issue['number'] ?? 'unknown',
        ];
    }

    /**
     * Handle push notifications.
     */
    private function handlePush(array $payload): array
    {
        $repository = $payload['repository']['full_name'] ?? 'unknown';
        $branch = str_replace('refs/heads/', '', $payload['ref'] ?? '');
        $commits = $payload['commits'] ?? [];
        $pusher = $payload['pusher']['name'] ?? 'unknown';

        // Notifier seulement pour les branches principales
        if (!in_array($branch, ['main', 'master', 'develop'])) {
            return ['notified' => false, 'reason' => 'Branch not important'];
        }

        $commitCount = count($commits);
        $message = "📨 {$commitCount} commit(s) poussé(s) sur {$branch} dans {$repository}";

        $this->sendNotification($message, [
            'repository' => $repository,
            'branch' => $branch,
            'commits' => $commitCount,
            'pusher' => $pusher,
            'last_commit' => $commits[count($commits) - 1]['message'] ?? 'unknown',
        ]);

        return [
            'notified' => true,
            'branch' => $branch,
            'commits' => $commitCount,
        ];
    }

    /**
     * Send notification (customize this method for your needs).
     */
    private function sendNotification(string $message, array $data = []): void
    {
        Log::info("GitHub Notification: {$message}", $data);

        // Ici vous pourriez intégrer avec :
        // - Slack API
        // - Discord webhook
        // - Microsoft Teams
        // - Email notifications
        // - SMS notifications
        // - Push notifications

        // Exemple pour Slack :
        // Http::post('https://hooks.slack.com/services/YOUR/SLACK/WEBHOOK', [
        //     'text' => $message,
        //     'attachments' => [
        //         [
        //             'color' => 'good',
        //             'fields' => array_map(fn($key, $value) => [
        //                 'title' => $key,
        //                 'value' => $value,
        //                 'short' => true
        //             ], array_keys($data), $data)
        //         ]
        //     ]
        // ]);
    }
}
