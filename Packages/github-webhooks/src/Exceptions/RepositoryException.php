<?php

namespace Laravel\GitHubWebhooks\Exceptions;

use Exception;
use Throwable;

class RepositoryException extends Exception
{
    protected string $repository;
    protected string $operation;
    protected array $context;

    public function __construct(
        string $message = '',
        string $repository = '',
        string $operation = '',
        array $context = [],
        int $code = 0,
        ?Throwable $previous = null
    ) {
        $this->repository = $repository;
        $this->operation = $operation;
        $this->context = $context;

        if (empty($message)) {
            $message = "Repository operation '{$operation}' failed for {$repository}";
        }

        parent::__construct($message, $code, $previous);
    }

    /**
     * Créer une exception pour un repository non trouvé.
     */
    public static function notFound(string $repository): self
    {
        return new self(
            "Repository {$repository} not found",
            $repository,
            'find',
            [],
            404
        );
    }

    /**
     * Créer une exception pour un échec de clone.
     */
    public static function cloneFailed(string $repository, string $cloneUrl, array $output = []): self
    {
        return new self(
            "Failed to clone repository {$repository} from {$cloneUrl}",
            $repository,
            'clone',
            ['clone_url' => $cloneUrl, 'output' => $output],
            500
        );
    }

    /**
     * Créer une exception pour un échec de mise à jour.
     */
    public static function updateFailed(string $repository, string $branch, array $output = []): self
    {
        return new self(
            "Failed to update repository {$repository} on branch {$branch}",
            $repository,
            'update',
            ['branch' => $branch, 'output' => $output],
            500
        );
    }

    /**
     * Créer une exception pour des permissions insuffisantes.
     */
    public static function permissionDenied(string $repository, string $path): self
    {
        return new self(
            "Permission denied for repository {$repository} at path {$path}",
            $repository,
            'permission',
            ['path' => $path],
            403
        );
    }

    /**
     * Créer une exception pour une branche non trouvée.
     */
    public static function branchNotFound(string $repository, string $branch): self
    {
        return new self(
            "Branch {$branch} not found in repository {$repository}",
            $repository,
            'branch',
            ['branch' => $branch],
            404
        );
    }

    public function getRepository(): string
    {
        return $this->repository;
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
