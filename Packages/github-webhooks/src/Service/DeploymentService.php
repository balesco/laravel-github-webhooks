<?php

namespace Laravel\GitHubWebhooks\Service;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Artisan;
use Laravel\GitHubWebhooks\Exceptions\DeploymentFailedException;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class DeploymentService
{
    protected string $projectPath;
    protected array $steps;

    public function __construct()
    {
        $this->projectPath = base_path();
        $this->steps = config('github-webhooks.steps', []);
    }

    /**
     * Exécuter le déploiement complet
     */
    public function deploy(array $webhookPayload = []): array
    {
        $startTime = microtime(true);
        $results = [];

        Log::info('Deployment started', ['payload' => $webhookPayload]);

        try {
            // Étapes de déploiement
            $results['git_pull'] = $this->gitPull();
            if (config('github-webhooks.deployment.run_composer', true)) {
                $results['composer_install'] = $this->composerInstall();
            }
            if (config('github-webhooks.deployment.run_npm', true)) {
                $results['npm_install'] = $this->npmInstall();
            }
            if (config('github-webhooks.deployment.run_migrations', true)) {
                $results['migrate'] = $this->runMigrations();
            }
            if (config('github-webhooks.deployment.cache_clear', true)) {
                $results['cache_clear'] = $this->clearCache();
            }
            if (config('github-webhooks.deployment.optimize', false)) {
                $results['optimize'] = $this->optimize();
            }
            if (config('github-webhooks.deployment.create_storage_link', false)) {
                $results['storage_link'] = $this->storageLink();
            }
            if (config('github-webhooks.deployment.restart_queue', false)) {
                $results['queue_restart'] = $this->restartQueue();
            }

            // Étapes personnalisées si configurées
            if (!empty($this->steps)) {
                $results['custom_steps'] = $this->runCustomSteps();
            }

            $duration = round(microtime(true) - $startTime, 2);

            Log::info('Deployment completed successfully', [
                'duration' => $duration . 's',
                'results' => $results
            ]);

            return [
                'status' => 'success',
                'duration' => $duration,
                'steps' => $results
            ];
        } catch (DeploymentFailedException $e) {
            $duration = round(microtime(true) - $startTime, 2);
            throw $e;
        }
    }

    /**
     * Effectuer un git pull
     */
    protected function gitPull(): array
    {
        Log::info('Executing git pull');

        $process = new Process(['git', 'pull', 'origin', config('github-webhooks.branch', 'main')], $this->projectPath);
        $process->setTimeout(120);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        return [
            'success' => true,
            'output' => trim($process->getOutput()),
            'error' => trim($process->getErrorOutput())
        ];
    }

    /**
     * Installer les dépendances Composer
     */
    protected function composerInstall(): array
    {
        Log::info('Installing Composer dependencies');

        $process = new Process([
            'composer',
            'install',
            '--no-interaction',
            '--prefer-dist',
            '--optimize-autoloader',
            '--no-dev'
        ], $this->projectPath);

        $process->setTimeout(300);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        return [
            'success' => true,
            'output' => trim($process->getOutput()),
            'error' => trim($process->getErrorOutput())
        ];
    }

    /**
     * Installer les dépendances NPM
     */
    protected function npmInstall(): array
    {
        Log::info('Installing NPM dependencies');

        $process = new Process(['npm', 'ci'], $this->projectPath);
        $process->setTimeout(300);
        $process->run();

        if (!$process->isSuccessful()) {
            // NPM peut ne pas être critique, on log juste l'erreur
            Log::warning('NPM install failed', [
                'output' => $process->getOutput(),
                'error' => $process->getErrorOutput()
            ]);

            return [
                'success' => false,
                'output' => trim($process->getOutput()),
                'error' => trim($process->getErrorOutput())
            ];
        }

        // Construire les assets
        $buildProcess = new Process(['npm', 'run', 'production'], $this->projectPath);
        $buildProcess->setTimeout(300);
        $buildProcess->run();

        return [
            'success' => true,
            'output' => trim($process->getOutput()),
            'build_output' => trim($buildProcess->getOutput()),
            'error' => trim($process->getErrorOutput())
        ];
    }

    /**
     * Exécuter les migrations
     */
    protected function runMigrations(): array
    {
        Log::info('Running database migrations');

        try {
            Artisan::call('migrate', ['--force' => true]);

            return [
                'success' => true,
                'output' => Artisan::output()
            ];
        } catch (\Exception $e) {
            throw new \Exception('Migration failed: ' . $e->getMessage());
        }
    }

    /**
     * Vider le cache
     */
    protected function clearCache(): array
    {
        Log::info('Clearing application cache');

        $commands = [
            'cache:clear',
            'config:clear',
            'route:clear',
            'view:clear'
        ];

        $outputs = [];

        foreach ($commands as $command) {
            try {
                Artisan::call($command);
                $outputs[$command] = Artisan::output();
            } catch (\Exception $e) {
                Log::warning("Failed to run {$command}", ['error' => $e->getMessage()]);
                $outputs[$command] = 'Failed: ' . $e->getMessage();
            }
        }

        return [
            'success' => true,
            'outputs' => $outputs
        ];
    }

    /**
     * Optimiser l'application
     */
    protected function optimize(): array
    {
        Log::info('Optimizing application');

        $commands = [
            'config:cache',
            'route:cache',
            'view:cache'
        ];

        $outputs = [];

        foreach ($commands as $command) {
            try {
                Artisan::call($command);
                $outputs[$command] = Artisan::output();
            } catch (\Exception $e) {
                Log::warning("Failed to run {$command}", ['error' => $e->getMessage()]);
                $outputs[$command] = 'Failed: ' . $e->getMessage();
            }
        }

        return [
            'success' => true,
            'outputs' => $outputs
        ];
    }

    /**
     * Créer le lien symbolique pour le storage
     */
    protected function storageLink(): array
    {
        Log::info('Creating storage symlink');

        try {
            Artisan::call('storage:link');

            return [
                'success' => true,
                'output' => Artisan::output()
            ];
        } catch (\Exception $e) {
            // Le lien peut déjà exister
            Log::info('Storage link creation skipped', ['reason' => $e->getMessage()]);

            return [
                'success' => true,
                'output' => 'Skipped: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Redémarrer les queues
     */
    protected function restartQueue(): array
    {
        Log::info('Restarting queue workers');

        try {
            Artisan::call('queue:restart');

            return [
                'success' => true,
                'output' => Artisan::output()
            ];
        } catch (\Exception $e) {
            Log::warning('Queue restart failed', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Exécuter des étapes personnalisées
     */
    protected function runCustomSteps(): array
    {
        Log::info('Running custom deployment steps');

        $results = [];

        foreach ($this->steps as $name => $step) {
            try {
                if (isset($step['command'])) {
                    $process = new Process(explode(' ', $step['command']), $this->projectPath);
                    $process->setTimeout($step['timeout'] ?? 120);
                    $process->run();

                    $results[$name] = [
                        'success' => $process->isSuccessful(),
                        'output' => trim($process->getOutput()),
                        'error' => trim($process->getErrorOutput())
                    ];
                } elseif (isset($step['artisan'])) {
                    Artisan::call($step['artisan'], $step['parameters'] ?? []);
                    $results[$name] = [
                        'success' => true,
                        'output' => Artisan::output()
                    ];
                }
            } catch (\Exception $e) {
                $results[$name] = [
                    'success' => false,
                    'error' => $e->getMessage()
                ];

                if ($step['required'] ?? false) {
                    throw new \Exception("Required custom step '{$name}' failed: " . $e->getMessage());
                }
            }
        }

        return $results;
    }
}
