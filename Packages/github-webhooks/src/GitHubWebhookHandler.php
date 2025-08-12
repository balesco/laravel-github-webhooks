<?php

namespace Laravel\GitHubWebhooks;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Laravel\GitHubWebhooks\Contracts\WebhookHandler;

class GitHubWebhookHandler
{
    protected array $handlers = [];

    public function __construct(protected array $config = [])
    {
        $this->loadHandlers();
    }

    /**
     * Handle a GitHub webhook event.
     */
    public function handle(string $event, array $payload, Request $request): mixed
    {
        $handlers = $this->getHandlersForEvent($event);

        $results = [];
        foreach ($handlers as $handler) {
            try {
                $result = $handler->handle($event, $payload, $request);
                $results[] = $result;
            } catch (\Exception $e) {
                Log::error("Handler failed for event {$event}", [
                    'handler' => get_class($handler),
                    'error' => $e->getMessage()
                ]);
                
                if (!($this->config['continue_on_handler_failure'] ?? true)) {
                    throw $e;
                }
            }
        }

        return $results;
    }

    /**
     * Register a handler for specific events.
     */
    public function on(string|array $events, WebhookHandler|callable $handler): self
    {
        $events = is_array($events) ? $events : [$events];

        foreach ($events as $event) {
            if (!isset($this->handlers[$event])) {
                $this->handlers[$event] = [];
            }

            $this->handlers[$event][] = $handler;
        }

        return $this;
    }

    /**
     * Get handlers for a specific event.
     */
    protected function getHandlersForEvent(string $event): array
    {
        $handlers = [];

        // Get specific event handlers
        if (isset($this->handlers[$event])) {
            $handlers = array_merge($handlers, $this->handlers[$event]);
        }

        // Get wildcard handlers
        if (isset($this->handlers['*'])) {
            $handlers = array_merge($handlers, $this->handlers['*']);
        }

        return $handlers;
    }

    /**
     * Load handlers from configuration.
     */
    protected function loadHandlers(): void
    {
        $configHandlers = $this->config['handlers'] ?? [];

        foreach ($configHandlers as $event => $handlerClasses) {
            if (is_string($handlerClasses)) {
                $handlerClasses = [$handlerClasses];
            }

            foreach ($handlerClasses as $handlerClass) {
                if (class_exists($handlerClass)) {
                    $this->on($event, app($handlerClass));
                }
            }
        }
    }
}
