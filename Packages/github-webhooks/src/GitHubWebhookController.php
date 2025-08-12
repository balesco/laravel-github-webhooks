<?php

namespace Laravel\GitHubWebhooks;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Laravel\GitHubWebhooks\Events\GitHubWebhookReceived;
use Laravel\GitHubWebhooks\Exceptions\InvalidSignatureException;
use Laravel\GitHubWebhooks\Models\GitHubWebhook;

class GitHubWebhookController
{
    public function __construct(
        protected GitHubWebhookHandler $handler
    ) {}

    /**
     * Handle the incoming GitHub webhook.
     */
    public function handle(Request $request): Response
    {
        try {
            // Verify the webhook signature if secret is configured
            if (config('github-webhooks.secret')) {
                $this->verifySignature($request);
            }

            // Get the event type from the header
            $event = $request->header('X-GitHub-Event');
            $delivery = $request->header('X-GitHub-Delivery');
            
            if (!$event) {
                Log::warning('GitHub webhook received without event type');
                return response('Missing event type', 400);
            }

            // Store the webhook in database if enabled
            $webhook = null;
            if (config('github-webhooks.store_webhooks', true)) {
                $webhook = GitHubWebhook::create([
                    'event_type' => $event,
                    'delivery_id' => $delivery,
                    'payload' => $request->all(),
                    'headers' => $request->headers->all(),
                    'processed_at' => null,
                ]);
            }

            // Process the webhook
            $result = $this->handler->handle($event, $request->all(), $request);

            // Mark as processed if stored
            if ($webhook) {
                $webhook->update(['processed_at' => now()]);
            }

            // Fire event
            event(new GitHubWebhookReceived($event, $request->all(), $delivery));

            Log::info("GitHub webhook processed successfully", [
                'event' => $event,
                'delivery' => $delivery,
                'result' => $result
            ]);

            return response('OK', 200);

        } catch (InvalidSignatureException $e) {
            Log::error('GitHub webhook signature verification failed', [
                'error' => $e->getMessage(),
                'delivery' => $request->header('X-GitHub-Delivery')
            ]);
            return response('Unauthorized', 401);

        } catch (\Exception $e) {
            Log::error('GitHub webhook processing failed', [
                'error' => $e->getMessage(),
                'delivery' => $request->header('X-GitHub-Delivery'),
                'trace' => $e->getTraceAsString()
            ]);
            return response('Internal Server Error', 500);
        }
    }

    /**
     * Verify the webhook signature.
     */
    protected function verifySignature(Request $request): void
    {
        $signature = $request->header('X-Hub-Signature-256');
        $secret = config('github-webhooks.secret');

        if (!$signature || !$secret) {
            throw new InvalidSignatureException('Missing signature or secret');
        }

        $expectedSignature = 'sha256=' . hash_hmac('sha256', $request->getContent(), $secret);

        if (!hash_equals($expectedSignature, $signature)) {
            throw new InvalidSignatureException('Invalid signature');
        }
    }
}
