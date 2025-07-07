<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Services\DiscordBotService;

class DiscordWebhookController extends Controller
{
    /**
     * Handle Discord webhook
     */
    public function handleWebhook(Request $request): JsonResponse
    {
        $discordService = app(DiscordBotService::class);
        
        // Validate webhook signature if configured
        $signature = $request->header('X-Signature-Ed25519');
        if ($signature && !$discordService->validateWebhookSignature($signature, $request->getContent())) {
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        // Process the Discord message
        $messageData = $request->all();
        
        // Validate required fields
        $requiredFields = ['id', 'channel_id', 'guild_id', 'author', 'content', 'timestamp'];
        foreach ($requiredFields as $field) {
            if (!isset($messageData[$field])) {
                return response()->json(['error' => "Missing required field: {$field}"], 400);
            }
        }

        try {
            $result = $discordService->processDiscordMessage($messageData);
            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to process message: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Simulate Discord message (for testing)
     */
    public function simulateMessage(Request $request): JsonResponse
    {
        $request->validate([
            'content' => 'required|string',
            'username' => 'required|string',
            'channel_id' => 'required|string',
        ]);

        $messageData = [
            'id' => (string) (time() * 1000 + rand(100, 999)), // Generate unique ID
            'channel_id' => $request->input('channel_id'),
            'guild_id' => '987654321',
            'author' => [
                'username' => $request->input('username'),
                'discriminator' => '0001'
            ],
            'content' => $request->input('content'),
            'timestamp' => now()->timestamp * 1000,
            'attachments' => []
        ];

        try {
            $discordService = app(DiscordBotService::class);
            $result = $discordService->processDiscordMessage($messageData);
            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to simulate message: ' . $e->getMessage()
            ], 500);
        }
    }
}
