<?php

namespace Laravel\GitHubWebhooks;

use Illuminate\Support\Facades\Facade;

/**
 * @method static self on(string|array $events, \Laravel\GitHubWebhooks\Contracts\WebhookHandler|callable $handler)
 * @method static mixed handle(string $event, array $payload, \Illuminate\Http\Request $request)
 */
class GitHubWebhooks extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return GitHubWebhookHandler::class;
    }
}
