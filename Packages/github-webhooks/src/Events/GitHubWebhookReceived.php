<?php

namespace Laravel\GitHubWebhooks\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GitHubWebhookReceived
{
    use Dispatchable, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public string $eventType,
        public array $payload,
        public ?string $deliveryId = null
    ) {}
}
