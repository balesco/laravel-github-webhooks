<?php

namespace Laravel\GitHubWebhooks\Exceptions;

use Exception;
use Throwable;

class DeploymentFailedException extends Exception
{
    protected string $repository;
    protected string $branch;
    protected string $environment;
    protected string $deploymentId;
    protected array $context;

    public function __construct(
        string $message = '',
        string $repository = '',
        string $branch = '',
        string $environment = '',
        string $deploymentId = '',
        array $context = [],
        int $code = 0,
        ?Throwable $previous = null
    ) {
        $this->repository = $repository;
        $this->branch = $branch;
        $this->environment = $environment;
        $this->deploymentId = $deploymentId;
        $this->context = $context;

        // Construire un message informatif si pas fourni
        if (empty($message)) {
            $message = $this->buildDefaultMessage();
        }

        parent::__construct($message, $code, $previous);
    }

    /**
     * Créer une exception pour une erreur de build.
     */
    public static function buildFailed(
        string $repository,
        string $branch,
        string $environment,
        string $deploymentId,
        array $buildOutput = [],
        ?Throwable $previous = null
    ): self {
        return new self(
            "Build failed for {$repository} ({$branch}) in {$environment}",
            $repository,
            $branch,
            $environment,
            $deploymentId,
            ['build_output' => $buildOutput, 'step' => 'build'],
            500,
            $previous
        );
    }

    /**
     * Créer une exception pour une erreur de tests.
     */
    public static function testsFailed(
        string $repository,
        string $branch,
        string $environment,
        string $deploymentId,
        array $testOutput = [],
        ?Throwable $previous = null
    ): self {
        return new self(
            "Tests failed for {$repository} ({$branch}) in {$environment}",
            $repository,
            $branch,
            $environment,
            $deploymentId,
            ['test_output' => $testOutput, 'step' => 'tests'],
            500,
            $previous
        );
    }

    /**
     * Créer une exception pour une erreur de déploiement.
     */
    public static function deploymentFailed(
        string $repository,
        string $branch,
        string $environment,
        string $deploymentId,
        array $deployOutput = [],
        ?Throwable $previous = null
    ): self {
        return new self(
            "Deployment failed for {$repository} ({$branch}) to {$environment}",
            $repository,
            $branch,
            $environment,
            $deploymentId,
            ['deploy_output' => $deployOutput, 'step' => 'deployment'],
            500,
            $previous
        );
    }

    /**
     * Créer une exception pour une erreur de rollback.
     */
    public static function rollbackFailed(
        string $repository,
        string $branch,
        string $environment,
        string $deploymentId,
        array $rollbackOutput = [],
        ?Throwable $previous = null
    ): self {
        return new self(
            "Rollback failed for {$repository} ({$branch}) in {$environment}",
            $repository,
            $branch,
            $environment,
            $deploymentId,
            ['rollback_output' => $rollbackOutput, 'step' => 'rollback'],
            500,
            $previous
        );
    }

    /**
     * Créer une exception pour une erreur de health check.
     */
    public static function healthCheckFailed(
        string $repository,
        string $branch,
        string $environment,
        string $deploymentId,
        string $healthCheckUrl = '',
        int $httpCode = 0,
        ?Throwable $previous = null
    ): self {
        return new self(
            "Health check failed for {$repository} ({$branch}) in {$environment}",
            $repository,
            $branch,
            $environment,
            $deploymentId,
            [
                'health_check_url' => $healthCheckUrl,
                'http_code' => $httpCode,
                'step' => 'health_check'
            ],
            500,
            $previous
        );
    }

    /**
     * Construire un message par défaut basé sur les propriétés.
     */
    protected function buildDefaultMessage(): string
    {
        $parts = ['Deployment failed'];

        if ($this->repository) {
            $parts[] = "for repository {$this->repository}";
        }

        if ($this->branch) {
            $parts[] = "on branch {$this->branch}";
        }

        if ($this->environment) {
            $parts[] = "to environment {$this->environment}";
        }

        if ($this->deploymentId) {
            $parts[] = "(ID: {$this->deploymentId})";
        }

        return implode(' ', $parts);
    }

    /**
     * Obtenir le repository concerné.
     */
    public function getRepository(): string
    {
        return $this->repository;
    }

    /**
     * Obtenir la branche concernée.
     */
    public function getBranch(): string
    {
        return $this->branch;
    }

    /**
     * Obtenir l'environnement concerné.
     */
    public function getEnvironment(): string
    {
        return $this->environment;
    }

    /**
     * Obtenir l'ID de déploiement.
     */
    public function getDeploymentId(): string
    {
        return $this->deploymentId;
    }

    /**
     * Obtenir le contexte additionnel.
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Obtenir l'étape qui a échoué.
     */
    public function getFailedStep(): string
    {
        return $this->context['step'] ?? 'unknown';
    }

    /**
     * Vérifier si c'est une erreur de build.
     */
    public function isBuildFailure(): bool
    {
        return $this->getFailedStep() === 'build';
    }

    /**
     * Vérifier si c'est une erreur de tests.
     */
    public function isTestFailure(): bool
    {
        return $this->getFailedStep() === 'tests';
    }

    /**
     * Vérifier si c'est une erreur de déploiement.
     */
    public function isDeploymentFailure(): bool
    {
        return $this->getFailedStep() === 'deployment';
    }

    /**
     * Vérifier si c'est une erreur de rollback.
     */
    public function isRollbackFailure(): bool
    {
        return $this->getFailedStep() === 'rollback';
    }

    /**
     * Vérifier si c'est une erreur de health check.
     */
    public function isHealthCheckFailure(): bool
    {
        return $this->getFailedStep() === 'health_check';
    }

    /**
     * Convertir en tableau pour logging.
     */
    public function toArray(): array
    {
        return [
            'message' => $this->getMessage(),
            'repository' => $this->repository,
            'branch' => $this->branch,
            'environment' => $this->environment,
            'deployment_id' => $this->deploymentId,
            'failed_step' => $this->getFailedStep(),
            'context' => $this->context,
            'code' => $this->getCode(),
            'file' => $this->getFile(),
            'line' => $this->getLine(),
            'trace' => $this->getTraceAsString(),
        ];
    }

    /**
     * Convertir en format JSON.
     */
    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_PRETTY_PRINT);
    }
}
