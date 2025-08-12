<?php

namespace Laravel\GitHubWebhooks\Commands;

use Illuminate\Console\Command;
use Laravel\GitHubWebhooks\Models\GitHubWebhook;

class ListWebhooksCommand extends Command
{
    protected $signature = 'github-webhooks:list
                          {--event= : Filter by event type}
                          {--processed : Show only processed webhooks}
                          {--unprocessed : Show only unprocessed webhooks}
                          {--limit=10 : Number of webhooks to show}';

    protected $description = 'List GitHub webhooks stored in the database';

    public function handle(): int
    {
        $query = GitHubWebhook::query()->orderByDesc('created_at');

        // Apply filters
        if ($this->option('event')) {
            $query->eventType($this->option('event'));
        }

        if ($this->option('processed')) {
            $query->processed();
        } elseif ($this->option('unprocessed')) {
            $query->unprocessed();
        }

        $webhooks = $query->limit($this->option('limit'))->get();

        if ($webhooks->isEmpty()) {
            $this->info('No webhooks found.');
            return self::SUCCESS;
        }

        $headers = ['ID', 'Event Type', 'Delivery ID', 'Processed', 'Created At'];
        $rows = [];

        foreach ($webhooks as $webhook) {
            $rows[] = [
                $webhook->id,
                $webhook->event_type,
                $webhook->delivery_id ?: 'N/A',
                $webhook->processed_at ? '✓' : '✗',
                $webhook->created_at->format('Y-m-d H:i:s'),
            ];
        }

        $this->table($headers, $rows);

        return self::SUCCESS;
    }
}
