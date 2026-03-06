<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Auto-Debug System Configuration
    |--------------------------------------------------------------------------
    |
    | Enable/disable the entire auto-debug system. When disabled, the scheduler
    | will skip all analysis and no AI calls will be made.
    |
    */

    'enabled' => env('AUTODEBUG_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | AI Provider Configuration
    |--------------------------------------------------------------------------
    |
    | Supported providers: 'openai', 'anthropic'
    | The system will use the configured provider for exception analysis
    | and fix generation.
    |
    */

    'ai' => [
        'provider' => env('AUTODEBUG_AI_PROVIDER', 'openai'),

        'openai' => [
            'api_key' => env('AUTODEBUG_OPENAI_API_KEY', env('OPENAI_API_KEY')),
            'model'   => env('AUTODEBUG_OPENAI_MODEL', 'gpt-4o'),
            'max_tokens' => 4096,
        ],

        'anthropic' => [
            'api_key' => env('AUTODEBUG_ANTHROPIC_API_KEY', env('ANTHROPIC_API_KEY')),
            'model'   => env('AUTODEBUG_ANTHROPIC_MODEL', 'claude-sonnet-4-20250514'),
            'max_tokens' => 4096,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | GitHub Integration
    |--------------------------------------------------------------------------
    |
    | Configure GitHub for automatic PR creation. The token needs repo access.
    | The base_branch is the branch PRs will be created against.
    |
    */

    'github' => [
        'enabled'     => env('AUTODEBUG_GITHUB_ENABLED', true),
        'token'       => env('AUTODEBUG_GITHUB_TOKEN'),
        'owner'       => env('AUTODEBUG_GITHUB_OWNER'),
        'repo'        => env('AUTODEBUG_GITHUB_REPO'),
        'base_branch' => env('AUTODEBUG_GITHUB_BASE_BRANCH', 'main'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Notification Channels
    |--------------------------------------------------------------------------
    |
    | Configure how the team gets notified about auto-debug events.
    | Supported channels: 'slack', 'mail', 'database'
    |
    */

    'notifications' => [
        'channels' => explode(',', env('AUTODEBUG_NOTIFICATION_CHANNELS', 'database')),

        'slack' => [
            'webhook_url' => env('AUTODEBUG_SLACK_WEBHOOK_URL'),
            'channel'     => env('AUTODEBUG_SLACK_CHANNEL', '#exceptions'),
        ],

        'mail' => [
            'to' => explode(',', env('AUTODEBUG_MAIL_TO', '')),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Analysis Settings
    |--------------------------------------------------------------------------
    |
    | Control how aggressively the system analyzes and fixes exceptions.
    |
    */

    'analysis' => [
        // Minimum confidence score (0-100) to auto-create a PR
        'min_confidence_for_pr' => env('AUTODEBUG_MIN_CONFIDENCE', 75),

        // Maximum AI API calls per hour (cost control)
        'max_calls_per_hour' => env('AUTODEBUG_MAX_CALLS_PER_HOUR', 10),

        // How often to poll for new exceptions (in minutes)
        'poll_interval' => env('AUTODEBUG_POLL_INTERVAL', 5),

        // Maximum number of exceptions to process per run
        'batch_size' => env('AUTODEBUG_BATCH_SIZE', 5),

        // How many lines of context to include around the error line
        'context_lines' => 20,

        // Dry run mode — analyze but don't create PRs
        'dry_run' => env('AUTODEBUG_DRY_RUN', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Safety Guards
    |--------------------------------------------------------------------------
    |
    | Files and directories that should NEVER be modified by auto-fix.
    |
    */

    'safety' => [
        'protected_paths' => [
            'database/migrations',
            'config/',
            '.env',
            'composer.json',
            'composer.lock',
            'package.json',
            'storage/',
            'bootstrap/',
            'public/index.php',
        ],

        // Exception classes to ignore (e.g. validation, auth)
        'ignored_exceptions' => [
            \Illuminate\Validation\ValidationException::class,
            \Illuminate\Auth\AuthenticationException::class,
            \Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class,
            \Illuminate\Database\Eloquent\ModelNotFoundException::class,
        ],
    ],

];
