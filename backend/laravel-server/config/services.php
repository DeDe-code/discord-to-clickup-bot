<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Discord and ClickUp. This file provides the de facto location for
    | this type of information, allowing packages to have a conventional file
    | to locate the various service credentials.
    |
    */

    // Discord Configuration
    'discord' => [
        'bot_token' => env('DISCORD_BOT_TOKEN'),
        'webhook_secret' => env('DISCORD_WEBHOOK_SECRET'),
        'channel_mappings' => [
            // Discord Channel ID => ClickUp Channel ID
            '1087467843584532510' => '6-901209555432-8',        // INCIDENTS -> INCIDENTS_CLICKUP
            '1087466485498265722' => '6-901209555434-8',        // WEBCAR_INCIDENTS -> WEBCAR_INCIDENTS_CLICKUP (different channel)
        ],
    ],

    // ClickUp Configuration
    'clickup' => [
        'api_token' => env('CLICKUP_API_TOKEN'),
        'client_id' => env('CLICKUP_CLIENT_ID'),
        'client_secret' => env('CLICKUP_CLIENT_SECRET'),
        'workspace_id' => env('CLICKUP_WORKSPACE_ID'),
    ],

];
