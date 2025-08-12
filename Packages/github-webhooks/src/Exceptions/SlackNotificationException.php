<?php

namespace Laravel\GitHubWebhooks\Exceptions;

use Exception;
use Throwable;

class SlackNotificationException extends Exception
{
    protected string $channel;
    protected string $operation;
    protected array $context;

    public function __construct(
        string $message = '',
        string $channel = '',
        string $operation = '',
        array $context = [],
        int $code = 0,
        ?Throwable $previous = null
    ) {
        $this->channel = $channel;
        $this->operation = $operation;
        $this->context = $context;

        if (empty($message)) {
            $message = "Slack notification operation '{$operation}' failed for channel {$channel}";
        }

        parent::__construct($message, $code, $previous);
    }

    /**
     * Créer une exception pour un token Slack invalide.
     */
    public static function invalidToken(): self
    {
        return new self(
            'Invalid Slack token provided',
            '',
            'authentication',
            [],
            401
        );
    }

    /**
     * Créer une exception pour un canal non trouvé.
     */
    public static function channelNotFound(string $channel): self
    {
        return new self(
            "Slack channel {$channel} not found",
            $channel,
            'channel_lookup',
            [],
            404
        );
    }

    /**
     * Créer une exception pour un échec d'envoi de message.
     */
    public static function sendFailed(string $channel, array $response = []): self
    {
        return new self(
            "Failed to send message to Slack channel {$channel}",
            $channel,
            'send_message',
            ['response' => $response],
            500
        );
    }

    /**
     * Créer une exception pour une limite de taux atteinte.
     */
    public static function rateLimitExceeded(string $channel, int $retryAfter = 0): self
    {
        return new self(
            "Rate limit exceeded for Slack channel {$channel}",
            $channel,
            'rate_limit',
            ['retry_after' => $retryAfter],
            429
        );
    }

    /**
     * Créer une exception pour des permissions insuffisantes.
     */
    public static function permissionDenied(string $channel): self
    {
        return new self(
            "Permission denied for Slack channel {$channel}",
            $channel,
            'permission',
            [],
            403
        );
    }

    public function getChannel(): string
    {
        return $this->channel;
    }

    public function getOperation(): string
    {
        return $this->operation;
    }

    public function getContext(): array
    {
        return $this->context;
    }
}
