<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Configuration complète d'exemple pour GitHub Webhooks
    |--------------------------------------------------------------------------
    |
    | Ce fichier montre toutes les options disponibles avec des exemples
    | concrets d'utilisation.
    |
    */

    'secret' => env('GITHUB_WEBHOOK_SECRET'),
    'route_prefix' => env('GITHUB_WEBHOOK_ROUTE_PREFIX', 'webhooks'),
    'store_webhooks' => env('GITHUB_WEBHOOK_STORE', true),

    'middleware' => [
        'throttle:60,1',  // Limiter à 60 requêtes par minute
        // 'auth:api',    // Authentification si nécessaire
    ],

    /*
    |--------------------------------------------------------------------------
    | Handlers Configuration
    |--------------------------------------------------------------------------
    */
    'handlers' => [
        // Déploiement automatique
        'push' => [
            App\Webhooks\Handlers\AutoDeploymentHandler::class,
            App\Webhooks\Handlers\SlackNotificationHandler::class,
            Laravel\GitHubWebhooks\Handlers\RepositoryUpdateHandler::class,
        ],

        // Pull Requests
        'pull_request' => [
            App\Webhooks\Handlers\SlackNotificationHandler::class,
            App\Webhooks\Handlers\AutoDeploymentHandler::class, // Pour les environnements de preview
            Laravel\GitHubWebhooks\Handlers\RepositoryUpdateHandler::class,
        ],

        // Issues et bugs
        'issues' => [
            App\Webhooks\Handlers\SlackNotificationHandler::class,
            // App\Webhooks\Handlers\JiraIntegrationHandler::class,
        ],

        // Releases
        'release' => [
            App\Webhooks\Handlers\SlackNotificationHandler::class,
            App\Webhooks\Handlers\AutoDeploymentHandler::class,
            // App\Webhooks\Handlers\ChangelogHandler::class,
        ],

        // Déploiements
        'deployment_status' => [
            App\Webhooks\Handlers\SlackNotificationHandler::class,
        ],

        // GitHub Actions
        'workflow_run' => [
            App\Webhooks\Handlers\SlackNotificationHandler::class,
        ],

        // Gestion de la sécurité
        'security_advisory' => [
            // App\Webhooks\Handlers\SecurityHandler::class,
        ],

        // Handler universel pour logging
        '*' => [
            // App\Webhooks\Handlers\AuditLogHandler::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Slack Integration
    |--------------------------------------------------------------------------
    */
    'slack' => [
        'enabled' => env('SLACK_NOTIFICATIONS_ENABLED', false),
        'webhook_url' => env('SLACK_WEBHOOK_URL'),
        
        // Repositories à notifier (vide = tous)
        'repositories' => [
            // 'owner/repo1',
            // 'owner/repo2',
        ],

        // Branches importantes pour les notifications push
        'important_branches' => ['main', 'master', 'develop', 'staging'],

        // Canaux Slack par type d'événement
        'channels' => [
            'development' => env('SLACK_CHANNEL_DEV', '#dev'),
            'pull_requests' => env('SLACK_CHANNEL_PRS', '#pull-requests'),
            'issues' => env('SLACK_CHANNEL_ISSUES', '#issues'),
            'releases' => env('SLACK_CHANNEL_RELEASES', '#releases'),
            'deployments' => env('SLACK_CHANNEL_DEPLOYMENTS', '#deployments'),
            'workflows' => env('SLACK_CHANNEL_CI_CD', '#ci-cd'),
            'security' => env('SLACK_CHANNEL_SECURITY', '#security'),
            'general' => env('SLACK_CHANNEL_GENERAL', '#general'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Deployment Configuration
    |--------------------------------------------------------------------------
    */
    'deployment_configs' => [
        'mon-organisation/mon-app' => [
            'branches' => [
                'main' => [
                    'environment' => 'production',
                    'clone_url' => 'git@github.com:mon-organisation/mon-app.git',
                    'build_required' => true,
                    'run_tests' => true,
                    'auto_rollback' => true,
                    
                    'build_commands' => [
                        'composer install --no-dev --optimize-autoloader',
                        'npm ci',
                        'npm run build',
                        'php artisan config:cache',
                        'php artisan route:cache',
                        'php artisan view:cache',
                    ],
                    
                    'test_commands' => [
                        'php artisan test --parallel',
                    ],
                    
                    'deploy_commands' => [
                        'rsync -avz --delete {work_dir}/ user@server:/var/www/html/',
                        'ssh user@server "cd /var/www/html && php artisan migrate --force"',
                        'ssh user@server "cd /var/www/html && php artisan queue:restart"',
                        'ssh user@server "sudo systemctl reload php8.2-fpm"',
                    ],
                    
                    'post_deploy_commands' => [
                        'curl -f https://mon-app.com/health-check',
                    ],
                    
                    'rollback_commands' => [
                        'ssh user@server "cd /var/www/html && git reset --hard HEAD~1"',
                        'ssh user@server "cd /var/www/html && php artisan migrate:rollback --force"',
                    ],
                    
                    'notifications' => [
                        'success' => true,
                        'failure' => true,
                    ],
                ],
                
                'develop' => [
                    'environment' => 'staging',
                    'clone_url' => 'git@github.com:mon-organisation/mon-app.git',
                    'build_required' => true,
                    'run_tests' => true,
                    'auto_rollback' => false,
                    
                    'build_commands' => [
                        'composer install',
                        'npm ci',
                        'npm run dev',
                    ],
                    
                    'test_commands' => [
                        'php artisan test',
                    ],
                    
                    'deploy_commands' => [
                        'rsync -avz --delete {work_dir}/ user@staging-server:/var/www/staging/',
                        'ssh user@staging-server "cd /var/www/staging && php artisan migrate --force"',
                    ],
                    
                    'notifications' => [
                        'success' => true,
                        'failure' => true,
                    ],
                ],
            ],
        ],
        
        'mon-organisation/mon-api' => [
            'branches' => [
                'main' => [
                    'environment' => 'production',
                    'clone_url' => 'git@github.com:mon-organisation/mon-api.git',
                    'build_required' => true,
                    'run_tests' => true,
                    'auto_rollback' => true,
                    
                    'build_commands' => [
                        'composer install --no-dev --optimize-autoloader',
                        'php artisan config:cache',
                        'php artisan route:cache',
                    ],
                    
                    'test_commands' => [
                        'php artisan test --coverage',
                    ],
                    
                    'deploy_commands' => [
                        // Déploiement Docker
                        'docker build -t mon-api:latest {work_dir}',
                        'docker tag mon-api:latest registry.example.com/mon-api:latest',
                        'docker push registry.example.com/mon-api:latest',
                        'kubectl set image deployment/mon-api mon-api=registry.example.com/mon-api:latest',
                        'kubectl rollout status deployment/mon-api',
                    ],
                    
                    'notifications' => [
                        'success' => true,
                        'failure' => true,
                    ],
                ],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Repository Update Configuration
    |--------------------------------------------------------------------------
    */
    'auto_update_branches' => ['main', 'master', 'develop'],
    'repository_storage_path' => storage_path('repositories'),

    /*
    |--------------------------------------------------------------------------
    | Security Configuration
    |--------------------------------------------------------------------------
    */
    'security' => [
        'alert_channels' => ['#security', '#dev-team'],
        'vulnerability_threshold' => 'medium', // low, medium, high, critical
        'auto_create_issues' => true,
        'auto_assign_security_team' => ['@security-team'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Code Quality Integration
    |--------------------------------------------------------------------------
    */
    'code_quality' => [
        'enabled' => env('CODE_QUALITY_ENABLED', false),
        'tools' => [
            'phpstan' => [
                'enabled' => true,
                'level' => 8,
                'command' => 'vendor/bin/phpstan analyse --level=8 app/',
            ],
            'phpcs' => [
                'enabled' => true,
                'standard' => 'PSR12',
                'command' => 'vendor/bin/phpcs --standard=PSR12 app/',
            ],
            'pest' => [
                'enabled' => true,
                'coverage_threshold' => 80,
                'command' => 'vendor/bin/pest --coverage --min=80',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Monitoring & Observability
    |--------------------------------------------------------------------------
    */
    'monitoring' => [
        'sentry' => [
            'enabled' => env('SENTRY_ENABLED', false),
            'dsn' => env('SENTRY_DSN'),
            'release_tracking' => true,
        ],
        
        'newrelic' => [
            'enabled' => env('NEWRELIC_ENABLED', false),
            'api_key' => env('NEWRELIC_API_KEY'),
            'app_id' => env('NEWRELIC_APP_ID'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Error Handling
    |--------------------------------------------------------------------------
    */
    'continue_on_handler_failure' => true,
    
    'retry' => [
        'enabled' => true,
        'max_attempts' => 3,
        'delay' => 60, // seconds
    ],
];
