<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('bot-control');
});

Route::get('/api-docs', function () {
    return response()->json([
        'message' => 'Discord to ClickUp Bot - Laravel Server',
        'version' => '1.0.0',
        'documentation' => [
            'WebSocket Control' => [
                'GET /api/websocket/status' => 'Get WebSocket connection status',
                'POST /api/websocket/connect' => 'Connect to Discord WebSocket',
                'POST /api/websocket/disconnect' => 'Disconnect from Discord WebSocket',
            ],
            'Bot Control' => [
                'GET /api/status' => 'Get bot and ClickUp authentication status',
                'POST /api/bot/start' => 'Start the Discord bot',
                'POST /api/bot/stop' => 'Stop the Discord bot',
            ],
            'Messages' => [
                'GET /api/messages' => 'Get recent Discord messages',
                'GET /api/messages/failed' => 'Get messages that failed to send to ClickUp',
                'POST /api/messages/retry' => 'Retry sending failed messages to ClickUp',
            ],
            'ClickUp Authentication' => [
                'GET /api/auth/clickup' => 'Redirect to ClickUp OAuth',
                'GET /api/auth/clickup/callback' => 'Handle ClickUp OAuth callback',
                'GET /api/auth/clickup/status' => 'Get ClickUp authentication status',
                'DELETE /api/auth/clickup/revoke' => 'Revoke ClickUp token',
            ],
            'Discord Integration' => [
                'POST /api/discord/webhook' => 'Handle Discord webhooks (no API key required)',
                'POST /api/discord/simulate' => 'Simulate Discord message for testing',
            ],
            'Utility' => [
                'GET /api/health' => 'Health check endpoint',
            ]
        ],
        'authentication' => [
            'method' => 'API Key',
            'header' => 'X-API-Key: your_api_secret',
            'query' => '?api_key=your_api_secret',
            'body' => 'api_key: your_api_secret'
        ],
        'note' => 'Most endpoints require API key authentication except Discord webhooks'
    ]);
});
