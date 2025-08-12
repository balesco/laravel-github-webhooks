<?php

namespace Laravel\GitHubWebhooks\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Event;
use Laravel\GitHubWebhooks\Events\GitHubWebhookReceived;
use Laravel\GitHubWebhooks\Models\GitHubWebhook;
use Laravel\GitHubWebhooks\Tests\TestCase;

class WebhookControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_webhook_can_be_received(): void
    {
        Event::fake();

        $payload = [
            'action' => 'opened',
            'number' => 1,
            'pull_request' => [
                'title' => 'Test PR',
                'user' => ['login' => 'testuser']
            ],
            'repository' => [
                'full_name' => 'test/repo'
            ]
        ];

        $response = $this->postJson('/webhooks/github', $payload, [
            'X-GitHub-Event' => 'pull_request',
            'X-GitHub-Delivery' => 'test-delivery-123'
        ]);

        $response->assertStatus(200);

        // Vérifier que l'événement a été déclenché
        Event::assertDispatched(GitHubWebhookReceived::class);

        // Vérifier que le webhook a été stocké
        $this->assertDatabaseHas('git_hub_webhooks', [
            'event_type' => 'pull_request',
            'delivery_id' => 'test-delivery-123'
        ]);
    }

    public function test_webhook_with_invalid_signature_is_rejected(): void
    {
        config(['github-webhooks.secret' => 'test-secret']);

        $payload = ['test' => 'data'];

        $response = $this->postJson('/webhooks/github', $payload, [
            'X-GitHub-Event' => 'push',
            'X-Hub-Signature-256' => 'sha256=invalid'
        ]);

        $response->assertStatus(401);
    }

    public function test_webhook_with_valid_signature_is_accepted(): void
    {
        config(['github-webhooks.secret' => 'test-secret']);

        $payload = ['test' => 'data'];
        $payloadJson = json_encode($payload);
        $signature = 'sha256=' . hash_hmac('sha256', $payloadJson, 'test-secret');

        $response = $this->call('POST', '/webhooks/github', $payload, [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_GITHUB_EVENT' => 'push',
            'HTTP_X_HUB_SIGNATURE_256' => $signature
        ], $payloadJson);

        $response->assertStatus(200);
    }

    public function test_webhook_without_event_type_is_rejected(): void
    {
        $response = $this->postJson('/webhooks/github', ['test' => 'data']);

        $response->assertStatus(400);
    }
}
