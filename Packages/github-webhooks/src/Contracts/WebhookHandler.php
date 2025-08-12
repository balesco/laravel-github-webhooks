<?php

namespace Laravel\GitHubWebhooks\Contracts;

use Illuminate\Http\Request;

interface WebhookHandler
{
    /**
     * Handle a GitHub webhook event.
     */
    public function handle(string $event, array $payload, Request $request): mixed;
}
