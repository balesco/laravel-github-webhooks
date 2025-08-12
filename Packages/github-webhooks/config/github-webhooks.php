<?php

return [
    /*
    |--------------------------------------------------------------------------
    | GitHub Webhook Secret
    |--------------------------------------------------------------------------
    |
    | The secret key used to verify GitHub webhook signatures.
    | You should set this in your .env file as GITHUB_WEBHOOK_SECRET.
    |
    */
    'secret' => env('GITHUB_WEBHOOK_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | Route Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the route prefix and middleware for webhook endpoints.
    |
    */
    'route_prefix' => env('GITHUB_WEBHOOK_ROUTE_PREFIX', 'webhooks'),

    'middleware' => [
        // Add any middleware you want to apply to webhook routes
        // 'throttle:60,1',
    ],

    /*
    |--------------------------------------------------------------------------
    | Storage Configuration
    |--------------------------------------------------------------------------
    |
    | Whether to store webhook data in the database for audit purposes.
    |
    */
    'store_webhooks' => env('GITHUB_WEBHOOK_STORE', true),

    /*
    |--------------------------------------------------------------------------
    | Handler Configuration
    |--------------------------------------------------------------------------
    |
    | Configure handlers for different GitHub events.
    | You can specify handlers for specific events or use '*' for all events.
    |
    */
    'handlers' => [
        // Example handlers
        // 'push' => [
        //     App\Webhooks\Handlers\PushHandler::class,
        // ],
        // 'pull_request' => [
        //     App\Webhooks\Handlers\PullRequestHandler::class,
        // ],
        // '*' => [
        //     App\Webhooks\Handlers\LogAllEventsHandler::class,
        // ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Error Handling
    |--------------------------------------------------------------------------
    |
    | Whether to continue processing other handlers if one fails.
    |
    */
    'continue_on_handler_failure' => true,

    /*
    |--------------------------------------------------------------------------
    | Repository Auto-Update Configuration
    |--------------------------------------------------------------------------
    |
    | Configure automatic repository updates for push and pull request events.
    |
    */
    'auto_update_branches' => ['main', 'master', 'develop'],
    
    'repository_storage_path' => base_path(),
];
