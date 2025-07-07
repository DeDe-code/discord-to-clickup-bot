<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // Discord Configuration
    'discord' => [
        'bot_token' => env('DISCORD_BOT_TOKEN'),
        'watched_channel_ids' => env('WATCHED_CHANNEL_IDS'),
        'webhook_secret' => env('DISCORD_WEBHOOK_SECRET'),
    ],

    // ClickUp Configuration
    'clickup' => [
        'api_token' => env('CLICKUP_API_TOKEN'),
        'client_id' => env('CLICKUP_CLIENT_ID'),
        'client_secret' => env('CLICKUP_CLIENT_SECRET'),
        'workspace_id' => env('CLICKUP_WORKSPACE_ID'),
        'channel_id' => env('CLICKUP_CHANNEL_ID'),
        'task_id' => env('CLICKUP_TASK_ID'),
    ],

    // Pusher Configuration for Broadcasting
    'pusher' => [
        'app_id' => env('PUSHER_APP_ID'),
        'key' => env('PUSHER_APP_KEY'),
        'secret' => env('PUSHER_APP_SECRET'),
        'cluster' => env('PUSHER_APP_CLUSTER'),
        'host' => env('PUSHER_HOST'),
        'port' => env('PUSHER_PORT', 443),
        'scheme' => env('PUSHER_SCHEME', 'https'),
    ],

];
