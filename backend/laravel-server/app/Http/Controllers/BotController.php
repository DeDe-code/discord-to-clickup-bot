<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Services\DiscordBotService;
use App\Services\ClickUpService;
use App\Services\FileMessageService;

class BotController extends Controller
{
    /**
     * Get bot status
     */
    public function status(): JsonResponse
    {
        $discordService = app(DiscordBotService::class);
        $clickUpService = app(ClickUpService::class);
        
        $botStatus = $discordService->isOnline() 
            ? '🟢 Bot is online' 
            : '🔴 Bot is offline';
        
        $clickupAuth = $clickUpService->hasValidToken()
            ? '✅ Authenticated'
            : '❌ Not authenticated';

        return response()->json([
            'status' => $botStatus,
            'clickupAuth' => $clickupAuth,
            'hasNewMessages' => cache()->has('new_message_available'),
            'timestamp' => now()->toISOString()
        ]);
    }

    /**
     * Check if there are new messages (lightweight endpoint)
     */
    public function checkNewMessages(): JsonResponse
    {
        $hasNewMessages = cache()->has('new_message_available');
        
        if ($hasNewMessages) {
            // Clear the flag when frontend checks
            cache()->forget('new_message_available');
        }
        
        return response()->json([
            'hasNewMessages' => $hasNewMessages,
            'timestamp' => now()->toISOString()
        ]);
    }

    /**
     * Start Discord bot
     */
    public function startBot(): JsonResponse
    {
        try {
            $discordService = app(DiscordBotService::class);
            $discordService->start();
            return response()->json(['message' => 'Bot started successfully']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to start bot: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Stop Discord bot
     */
    public function stopBot(): JsonResponse
    {
        try {
            $discordService = app(DiscordBotService::class);
            $discordService->stop();
            return response()->json(['message' => 'Bot stopped successfully']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to stop bot: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Get recent messages
     */
    public function getMessages(Request $request): JsonResponse
    {
        $fileMessageService = app(FileMessageService::class);
        $limit = $request->get('limit', 50);
        
        $messages = $fileMessageService->getRecentMessages($limit);
        
        return response()->json($messages);
    }

    /**
     * Get failed messages
     */
    public function getFailedMessages(): JsonResponse
    {
        $discordService = app(DiscordBotService::class);
        $messages = $discordService->getFailedMessages();
        return response()->json($messages);
    }

    /**
     * Retry failed messages
     */
    public function retryFailedMessages(): JsonResponse
    {
        $discordService = app(DiscordBotService::class);
        $results = $discordService->retryFailedMessages();
        return response()->json([
            'message' => 'Retry completed',
            'results' => $results
        ]);
    }

    /**
     * Get storage statistics
     */
    public function getStats(): JsonResponse
    {
        $fileMessageService = app(FileMessageService::class);
        $stats = $fileMessageService->getStats();
        
        return response()->json($stats);
    }
}
