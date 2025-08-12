<?php

namespace Laravel\GitHubWebhooks\Exceptions;

use Exception;
use Throwable;

class WebhookHandlerException extends Exception
{
    protected string $handlerClass;
    protected string $eventType;
    protected array $payload;

    public function __construct(
        string $message = '',
        string $handlerClass = '',
        string $eventType = '',
        array $payload = [],
        int $code = 0,
        ?Throwable $previous = null
    ) {
        $this->handlerClass = $handlerClass;
        $this->eventType = $eventType;
        $this->payload = $payload;

        if (empty($message)) {
            $message = "Handler {$handlerClass} failed to process {$eventType} event";
        }

        parent::__construct($message, $code, $previous);
    }

    /**
     * Créer une exception pour un handler qui n'existe pas.
     */
    public static function handlerNotFound(string $handlerClass): self
    {
        return new self(
            "Handler class {$handlerClass} not found",
            $handlerClass,
            '',
            [],
            404
        );
    }

    /**
     * Créer une exception pour un handler invalide.
     */
    public static function invalidHandler(string $handlerClass): self
    {
        return new self(
            "Handler {$handlerClass} does not implement WebhookHandler interface",
            $handlerClass,
            '',
            [],
            400
        );
    }

    /**
     * Créer une exception pour un timeout de handler.
     */
    public static function handlerTimeout(string $handlerClass, string $eventType, int $timeout): self
    {
        return new self(
            "Handler {$handlerClass} timed out after {$timeout} seconds while processing {$eventType}",
            $handlerClass,
            $eventType,
            [],
            408
        );
    }

    public function getHandlerClass(): string
    {
        return $this->handlerClass;
    }

    public function getEventType(): string
    {
        return $this->eventType;
    }

    public function getPayload(): array
    {
        return $this->payload;
    }
}
