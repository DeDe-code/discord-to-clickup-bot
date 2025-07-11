<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use App\Services\ClickUpService;
use App\Services\FileMessageService;
use App\Events\NewDiscordMessage;

class DiscordBotService
{
    private const BOT_STATUS_KEY = 'discord_bot_status';
    private const MESSAGES_CACHE_KEY = 'recent_discord_messages';

    private FileMessageService $fileMessageService;

    public function __construct(FileMessageService $fileMessageService)
    {
        $this->fileMessageService = $fileMessageService;
    }

    /**
     * Check if bot is online
     */
    public function isOnline(): bool
    {
        $status = $this->fileMessageService->getBotStatus();
        return ($status['status'] ?? 'disconnected') === 'connected';
    }

    /**
     * Start the Discord bot
     */
    public function start(): void
    {
        $this->fileMessageService->updateBotStatus([
            'status' => 'connected',
            'started_at' => now()->toISOString(),
            'version' => '1.0.0'
        ]);
        Log::info('🤖 Discord bot started');
    }

    /**
     * Stop the Discord bot
     */
    public function stop(): void
    {
        $this->fileMessageService->updateBotStatus([
            'status' => 'disconnected',
            'stopped_at' => now()->toISOString()
        ]);
        Log::info('❌ Discord bot stopped');
    }

    /**
     * Get recent messages
     */
    public function getRecentMessages(): array
    {
        return $this->fileMessageService->getRecentMessages();
    }

    /**
     * Process a Discord message (webhook endpoint)
     * This method would be called by a Discord webhook or external process
     */
    public function processDiscordMessage(array $messageData): array
    {
        $channelMappings = config('services.discord.channel_mappings', []);
        
        // Check if this Discord channel is in our mapping
        if (!isset($channelMappings[$messageData['channel_id']])) {
            return ['processed' => false, 'reason' => 'channel_not_watched'];
        }
        
        $clickUpChannelId = $channelMappings[$messageData['channel_id']];

        $timestamp = date('Y-m-d H:i:s', $messageData['timestamp'] / 1000);
        $author = $messageData['author']['username'] . '#' . $messageData['author']['discriminator'];
        $discordLink = "https://discord.com/channels/{$messageData['guild_id']}/{$messageData['channel_id']}/{$messageData['id']}";

        $attachmentLinks = '';
        if (!empty($messageData['attachments'])) {
            $attachmentLinks = "\n📎 Attachments:\n";
            foreach ($messageData['attachments'] as $attachment) {
                $attachmentLinks .= "- [{$attachment['filename']}]({$attachment['url']})\n";
            }
        }

        $content = "📨 **New Discord Message**\n\n" .
                  "**User**: {$author}\n" .
                  "**Time**: {$timestamp}\n" .
                  "**Message**: {$messageData['content']}\n" .
                  "🔗 [View message in Discord]({$discordLink})\n" .
                  $attachmentLinks;

        // Store message in file storage
        $messageId = $messageData['id'] ?? uniqid();
        $this->fileMessageService->storeMessage([
            'discord_message_id' => $messageId,
            'channel_id' => $messageData['channel_id'],
            'username' => $messageData['author']['username'] ?? 'Unknown',
            'content' => $messageData['content'] ?? '',
            'discord_timestamp' => date('Y-m-d H:i:s', ($messageData['timestamp'] ?? time() * 1000) / 1000),
            'clickup_sent' => false,
        ]);

        // Send to ClickUp using the mapped channel
        $clickUpService = app(ClickUpService::class);
        $result = $clickUpService->sendMessage($content, $clickUpChannelId);

        if ($result['success']) {
            Log::info('✅ Message sent to ClickUp channel');
            
            // Mark as sent to ClickUp
            $this->fileMessageService->markAsSentToClickUp($messageId, $clickUpChannelId, $result['response'] ?? null);

            // Broadcast to frontend (using Laravel Broadcasting)
            broadcast(new NewDiscordMessage([
                'username' => $messageData['author']['username'] ?? 'Unknown',
                'content' => $messageData['content'] ?? '',
                'timestamp' => $timestamp,
            ]));

            return ['processed' => true, 'clickup_result' => $result];
        } else {
            // Mark as failed
            $this->fileMessageService->markAsFailed($messageId, $result['message'] ?? 'Unknown error');
            
            if ($result['status'] === 401) {
                Log::warning('🔁 Re-authentication required. Visit /auth/clickup to log in again.');
            } else {
                Log::error('❌ Failed to send message to ClickUp:', $result);
            }
            
            return ['processed' => false, 'error' => $result];
        }
    }

    /**
     * Validate Discord webhook signature (if using webhooks)
     */
    public function validateWebhookSignature(string $signature, string $body): bool
    {
        $expectedSignature = hash_hmac('sha256', $body, config('services.discord.webhook_secret'));
        return hash_equals($signature, $expectedSignature);
    }

    /**
     * Get failed messages that need to be retried
     */
    public function getFailedMessages(): array
    {
        return $this->fileMessageService->getFailedMessages();
    }

    /**
     * Retry failed messages
     */
    public function retryFailedMessages(): array
    {
        $failedMessages = $this->getFailedMessages();
        $results = [];

        foreach ($failedMessages as $message) {
            $messageData = [
                'id' => $message['discord_message_id'],
                'channel_id' => $message['channel_id'],
                'author' => ['username' => $message['username']],
                'content' => $message['content'],
                'timestamp' => strtotime($message['discord_timestamp']) * 1000,
            ];

            $result = $this->processDiscordMessage($messageData);
            $results[] = [
                'message_id' => $message->discord_message_id,
                'result' => $result,
            ];
        }

        return $results;
    }
}
