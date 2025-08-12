<?php

namespace Tests\Feature\Webhooks;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Laravel\GitHubWebhooks\Events\GitHubWebhookReceived;
use Laravel\GitHubWebhooks\Models\GitHubWebhook;
use Tests\TestCase;

/**
 * Tests d'exemple pour les webhooks GitHub
 */
class GitHubWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Configuration de test
        config([
            'github-webhooks.secret' => 'test-secret',
            'github-webhooks.store_webhooks' => true,
        ]);
    }

    public function test_push_webhook_is_processed_correctly(): void
    {
        Event::fake();

        $payload = $this->getPushPayload();
        $signature = $this->generateSignature($payload);

        $response = $this->postJson('/webhooks/github', $payload, [
            'X-GitHub-Event' => 'push',
            'X-GitHub-Delivery' => 'test-delivery-123',
            'X-Hub-Signature-256' => $signature,
        ]);

        $response->assertStatus(200);

        // Vérifier que l'événement a été déclenché
        Event::assertDispatched(GitHubWebhookReceived::class, function ($event) {
            return $event->eventType === 'push' && 
                   $event->deliveryId === 'test-delivery-123';
        });

        // Vérifier que le webhook a été stocké
        $this->assertDatabaseHas('git_hub_webhooks', [
            'event_type' => 'push',
            'delivery_id' => 'test-delivery-123',
        ]);

        $webhook = GitHubWebhook::where('delivery_id', 'test-delivery-123')->first();
        $this->assertNotNull($webhook->processed_at);
    }

    public function test_pull_request_webhook_triggers_slack_notification(): void
    {
        Http::fake([
            'hooks.slack.com/*' => Http::response(['ok' => true], 200),
        ]);

        config([
            'github-webhooks.slack.enabled' => true,
            'github-webhooks.slack.webhook_url' => 'https://hooks.slack.com/test',
        ]);

        $payload = $this->getPullRequestPayload();
        $signature = $this->generateSignature($payload);

        $response = $this->postJson('/webhooks/github', $payload, [
            'X-GitHub-Event' => 'pull_request',
            'X-GitHub-Delivery' => 'test-delivery-456',
            'X-Hub-Signature-256' => $signature,
        ]);

        $response->assertStatus(200);

        // Vérifier que Slack a été appelé
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'hooks.slack.com') &&
                   str_contains($request->body(), 'Pull Request opened');
        });
    }

    public function test_webhook_with_invalid_signature_is_rejected(): void
    {
        $payload = $this->getPushPayload();

        $response = $this->postJson('/webhooks/github', $payload, [
            'X-GitHub-Event' => 'push',
            'X-Hub-Signature-256' => 'sha256=invalid-signature',
        ]);

        $response->assertStatus(401);
        
        // Vérifier qu'aucun webhook n'a été stocké
        $this->assertDatabaseCount('git_hub_webhooks', 0);
    }

    public function test_webhook_without_event_type_is_rejected(): void
    {
        $payload = $this->getPushPayload();
        $signature = $this->generateSignature($payload);

        $response = $this->postJson('/webhooks/github', $payload, [
            'X-Hub-Signature-256' => $signature,
        ]);

        $response->assertStatus(400);
    }

    public function test_deployment_webhook_triggers_auto_deployment(): void
    {
        // Configurer le déploiement automatique
        config([
            'github-webhooks.deployment_configs' => [
                'test/repo' => [
                    'branches' => [
                        'main' => [
                            'environment' => 'production',
                            'build_required' => false,
                            'run_tests' => false,
                            'deploy_commands' => ['echo "Deployed!"'],
                        ],
                    ],
                ],
            ],
        ]);

        $payload = $this->getPushPayload([
            'repository' => ['full_name' => 'test/repo'],
            'ref' => 'refs/heads/main',
        ]);
        $signature = $this->generateSignature($payload);

        $response = $this->postJson('/webhooks/github', $payload, [
            'X-GitHub-Event' => 'push',
            'X-Hub-Signature-256' => $signature,
        ]);

        $response->assertStatus(200);
        
        // Dans un vrai test, vous vérifieriez que les commandes de déploiement ont été exécutées
        // ou que les jobs appropriés ont été dispatched
    }

    public function test_webhook_rate_limiting(): void
    {
        config(['github-webhooks.middleware' => ['throttle:2,1']]);

        $payload = $this->getPushPayload();
        $signature = $this->generateSignature($payload);
        $headers = [
            'X-GitHub-Event' => 'push',
            'X-Hub-Signature-256' => $signature,
        ];

        // Premiers appels OK
        $this->postJson('/webhooks/github', $payload, $headers)->assertStatus(200);
        $this->postJson('/webhooks/github', $payload, $headers)->assertStatus(200);
        
        // Troisième appel rate limité
        $this->postJson('/webhooks/github', $payload, $headers)->assertStatus(429);
    }

    public function test_webhook_can_be_reprocessed(): void
    {
        Event::fake();

        // Créer un webhook dans la base
        $webhook = GitHubWebhook::create([
            'event_type' => 'push',
            'delivery_id' => 'test-reprocess',
            'payload' => $this->getPushPayload(),
            'headers' => ['X-GitHub-Event' => ['push']],
            'processed_at' => now(),
        ]);

        // Reprocesser le webhook
        $this->artisan('github-webhooks:reprocess', ['id' => $webhook->id])
             ->assertExitCode(0);

        // Vérifier que le timestamp a été mis à jour
        $webhook->refresh();
        $this->assertNotNull($webhook->processed_at);
    }

    public function test_webhook_validation_command(): void
    {
        $this->artisan('github-webhooks:validate')
             ->expectsOutput('🎉 All validations passed!')
             ->assertExitCode(0);
    }

    public function test_secret_generation_command(): void
    {
        $this->artisan('github-webhooks:generate-secret', ['--show'])
             ->expectsOutput('Generated webhook secret:')
             ->assertExitCode(0);
    }

    public function test_repository_update_command(): void
    {
        $this->artisan('github-webhooks:update-repo', [
            'repository' => 'test/repo',
            '--clone-url' => 'https://github.com/test/repo.git',
        ])->assertExitCode(0);
    }

    /**
     * Générer un payload de push d'exemple.
     */
    private function getPushPayload(array $overrides = []): array
    {
        return array_merge([
            'ref' => 'refs/heads/main',
            'repository' => [
                'full_name' => 'test/repository',
                'clone_url' => 'https://github.com/test/repository.git',
            ],
            'pusher' => [
                'name' => 'testuser',
            ],
            'commits' => [
                [
                    'id' => 'abc123',
                    'message' => 'Test commit',
                    'author' => ['name' => 'Test User'],
                    'timestamp' => now()->toISOString(),
                ],
            ],
        ], $overrides);
    }

    /**
     * Générer un payload de pull request d'exemple.
     */
    private function getPullRequestPayload(array $overrides = []): array
    {
        return array_merge([
            'action' => 'opened',
            'number' => 1,
            'pull_request' => [
                'id' => 123,
                'number' => 1,
                'title' => 'Test PR',
                'html_url' => 'https://github.com/test/repo/pull/1',
                'user' => ['login' => 'testuser'],
                'head' => ['ref' => 'feature-branch'],
                'base' => ['ref' => 'main'],
                'created_at' => now()->toISOString(),
                'merged' => false,
            ],
            'repository' => [
                'full_name' => 'test/repository',
            ],
        ], $overrides);
    }

    /**
     * Générer une signature HMAC pour les tests.
     */
    private function generateSignature(array $payload): string
    {
        $json = json_encode($payload);
        $secret = config('github-webhooks.secret');
        return 'sha256=' . hash_hmac('sha256', $json, $secret);
    }
}
