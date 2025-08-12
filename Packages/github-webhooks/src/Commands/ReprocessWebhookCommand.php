<?php

namespace Laravel\GitHubWebhooks\Commands;

use Illuminate\Console\Command;
use Laravel\GitHubWebhooks\Models\GitHubWebhook;

class ReprocessWebhookCommand extends Command
{
    protected $signature = 'github-webhooks:reprocess
                          {id : The webhook ID to reprocess}';

    protected $description = 'Reprocess a specific GitHub webhook';

    public function handle(): int
    {
        $webhookId = $this->argument('id');
        $webhook = GitHubWebhook::find($webhookId);

        if (!$webhook) {
            $this->error("Webhook with ID {$webhookId} not found.");
            return self::FAILURE;
        }

        $this->info("Reprocessing webhook {$webhook->id} (Event: {$webhook->event_type})");

        try {
            $handler = app(\Laravel\GitHubWebhooks\GitHubWebhookHandler::class);
            
            // Create a mock request with the stored data
            $request = request();
            $request->merge($webhook->payload);
            
            // Set headers
            foreach ($webhook->headers as $key => $value) {
                $request->headers->set($key, is_array($value) ? $value[0] : $value);
            }

            $result = $handler->handle($webhook->event_type, $webhook->payload, $request);

            // Update processed timestamp
            $webhook->update(['processed_at' => now()]);

            $this->info('Webhook reprocessed successfully.');
            $this->line('Result: ' . json_encode($result, JSON_PRETTY_PRINT));

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Failed to reprocess webhook: {$e->getMessage()}");
            return self::FAILURE;
        }
    }
}
