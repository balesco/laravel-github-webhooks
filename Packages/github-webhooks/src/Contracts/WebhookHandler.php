<?php

namespace Laravel\GitHubWebhooks\Contracts;

use Illuminate\Http\Request;
use Laravel\GitHubWebhooks\Service\DeploymentService;

interface WebhookHandler
{
    public function __construct(DeploymentService $deploymentService);
    
    /**
     * Handle a GitHub webhook event.
     */
    public function handle(string $event, array $payload, Request $request): mixed;
}
