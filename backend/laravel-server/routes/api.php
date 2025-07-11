<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BotController;
use App\Http\Controllers\ClickUpAuthController;
use App\Http\Controllers\DiscordWebhookController;
use App\Http\Controllers\WebSocketController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Bot status and control routes
Route::get('/status', [BotController::class, 'status']);
Route::get('/check-new-messages', [BotController::class, 'checkNewMessages']);
Route::post('/bot/start', [BotController::class, 'startBot']);
Route::post('/bot/stop', [BotController::class, 'stopBot']);
Route::get('/messages', [BotController::class, 'getMessages']);
Route::get('/messages/stats', [BotController::class, 'getStats']);
Route::get('/messages/failed', [BotController::class, 'getFailedMessages']);
Route::post('/messages/retry', [BotController::class, 'retryFailedMessages']);

// WebSocket control routes (no authentication needed for basic status)
Route::get('/websocket/status', [WebSocketController::class, 'getStatus'])
    ->withoutMiddleware([\App\Http\Middleware\ApiKeyMiddleware::class]);
Route::post('/websocket/connect', [WebSocketController::class, 'connect'])
    ->withoutMiddleware([\App\Http\Middleware\ApiKeyMiddleware::class]);
Route::post('/websocket/disconnect', [WebSocketController::class, 'disconnect'])
    ->withoutMiddleware([\App\Http\Middleware\ApiKeyMiddleware::class]);

// ClickUp OAuth routes
Route::get('/auth/clickup', [ClickUpAuthController::class, 'redirectToClickUp']);
Route::get('/auth/clickup/callback', [ClickUpAuthController::class, 'handleCallback']);
Route::get('/auth/clickup/status', [ClickUpAuthController::class, 'getAuthStatus']);
Route::delete('/auth/clickup/revoke', [ClickUpAuthController::class, 'revokeToken']);

// Discord webhook routes (these should be excluded from API key middleware for webhooks)
Route::post('/discord/webhook', [DiscordWebhookController::class, 'handleWebhook'])
    ->withoutMiddleware([\App\Http\Middleware\ApiKeyMiddleware::class]);

// Testing/simulation routes
Route::post('/discord/simulate', [DiscordWebhookController::class, 'simulateMessage']);

// Health check
Route::get('/health', function () {
    return response()->json([
        'status' => 'healthy',
        'timestamp' => now()->toISOString(),
        'version' => '1.0.0'
    ]);
});

// Debug endpoint
Route::get('/debug', function () {
    return response()->json([
        'message' => 'Debug endpoint working',
        'timestamp' => now()->toISOString()
    ]);
});

// Simple status endpoint to test
Route::get('/status-simple', function () {
    return response()->json([
        'status' => 'Bot is offline (simple)',
        'clickupAuth' => 'Not tested'
    ]);
});

// Debug status endpoint with services
Route::get('/status-debug', function () {
    try {
        $discordService = app(\App\Services\DiscordBotService::class);
        $clickUpService = app(\App\Services\ClickUpService::class);
        
        $botStatus = $discordService->isOnline() 
            ? '🟢 Bot is online' 
            : '🔴 Bot is offline';
        
        $clickupAuth = $clickUpService->hasValidToken()
            ? '✅ Authenticated'
            : '❌ Not authenticated';

        return response()->json([
            'status' => $botStatus,
            'clickupAuth' => $clickupAuth,
            'debug' => 'Services loaded successfully'
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ], 500);
    }
});

// Test ClickUp service only
Route::get('/test-clickup', function () {
    try {
        $clickUpService = app(\App\Services\ClickUpService::class);
        $hasToken = $clickUpService->hasValidToken();
        
        return response()->json([
            'clickupAuth' => $hasToken ? '✅ Authenticated' : '❌ Not authenticated',
            'debug' => 'ClickUp service loaded successfully'
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ], 500);
    }
});

// Test Discord service only
Route::get('/test-discord', function () {
    try {
        $discordService = app(\App\Services\DiscordBotService::class);
        $isOnline = $discordService->isOnline();
        
        return response()->json([
            'status' => $isOnline ? '🟢 Bot is online' : '🔴 Bot is offline',
            'debug' => 'Discord service loaded successfully'
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ], 500);
    }
});

// Test BotStatus model directly
Route::get('/test-botstatus', function () {
    try {
        $status = \App\Models\BotStatus::getDiscordBotStatus();
        
        return response()->json([
            'status' => $status ? $status->toArray() : null,
            'debug' => 'BotStatus model loaded successfully'
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ], 500);
    }
});

// Test the exact Discord service logic
Route::get('/test-discord-logic', function () {
    try {
        $status = \App\Models\BotStatus::getDiscordBotStatus();
        $isOnline = $status ? $status->isOnline() : false;
        
        return response()->json([
            'status' => $isOnline ? '🟢 Bot is online' : '🔴 Bot is offline',
            'raw_status' => $status ? $status->toArray() : null,
            'debug' => 'Discord logic test successful'
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ], 500);
    }
});

// Test creating both services together
Route::get('/test-both-services', function () {
    try {
        $clickUpService = app(\App\Services\ClickUpService::class);
        $discordService = app(\App\Services\DiscordBotService::class);
        
        return response()->json([
            'clickup' => 'Service created successfully',
            'discord' => 'Service created successfully',
            'debug' => 'Both services created'
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ], 500);
    }
});

// Test discord service
Route::get('/test-discord-service', function () {
    try {
        $discordService = app(\App\Services\DiscordBotService::class);
        return response()->json(['success' => 'Discord service loaded successfully']);
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
});

// Test process discord message
Route::post('/test-process-message', function () {
    try {
        $discordService = app(\App\Services\DiscordBotService::class);
        
        $messageData = [
            'id' => '123456789',
            'channel_id' => '1087467843584532510',
            'guild_id' => '987654321',
            'author' => [
                'username' => 'TestUser',
                'discriminator' => '0001'
            ],
            'content' => 'Hello from Discord test!',
            'timestamp' => now()->timestamp * 1000,
            'attachments' => []
        ];
        
        $result = $discordService->processDiscordMessage($messageData);
        return response()->json(['success' => true, 'result' => $result]);
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()], 500);
    }
});
