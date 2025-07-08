<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use App\Services\ClickUpService;
use App\Events\NewDiscordMessage;
use App\Models\DiscordMessage;
use App\Models\BotStatus;

class DiscordBotService
{
    private const BOT_STATUS_KEY = 'discord_bot_status';
    private const MESSAGES_CACHE_KEY = 'recent_discord_messages';

    public function __construct()
    {
        // Removed ClickUpService dependency injection to avoid circular dependency issues
        // Will use app() to resolve when needed
    }

    /**
     * Check if bot is online
     */
    public function isOnline(): bool
    {
        $status = BotStatus::getDiscordBotStatus();
        return $status ? $status->isOnline() : false;
    }

    /**
     * Start the Discord bot
     */
    public function start(): void
    {
        BotStatus::updateStatus('discord-bot', true, [
            'started_at' => now()->toISOString(),
            'version' => '1.0.0'
        ]);
        Log::info('🤖 Discord bot started');
        
        // In a real implementation, you would start the actual Discord bot process here
        // This could be done via a command, queue job, or external process management
    }

    /**
     * Stop the Discord bot
     */
    public function stop(): void
    {
        BotStatus::updateStatus('discord-bot', false);
        Log::info('❌ Discord bot stopped');
    }

    /**
     * Get recent messages
     */
    public function getRecentMessages(): array
    {
        return DiscordMessage::getRecent(50)
            ->map(function ($message) {
                return [
                    'id' => $message->discord_message_id,
                    'username' => $message->username,
                    'content' => $message->content,
                    'timestamp' => $message->discord_timestamp->toISOString(),
                    'sent_to_clickup' => $message->sent_to_clickup,
                ];
            })
            ->toArray();
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

        // Store message in database
        $discordMessage = DiscordMessage::create([
            'discord_message_id' => $messageData['id'],
            'channel_id' => $messageData['channel_id'],
            'guild_id' => $messageData['guild_id'],
            'username' => $messageData['author']['username'],
            'content' => $messageData['content'],
            'attachments' => $messageData['attachments'] ?? [],
            'discord_timestamp' => date('Y-m-d H:i:s', $messageData['timestamp'] / 1000),
        ]);

        // Send to ClickUp using the mapped channel
        $clickUpService = app(ClickUpService::class);
        $result = $clickUpService->sendMessage($content, $clickUpChannelId);

        if ($result['success']) {
            Log::info('✅ Message sent to ClickUp channel');
            
            // Mark as sent to ClickUp
            $discordMessage->markAsSentToClickUp($result['response']['id'] ?? null);

            // Broadcast to frontend (using Laravel Broadcasting)
            broadcast(new NewDiscordMessage([
                'username' => $messageData['author']['username'],
                'content' => $messageData['content'],
                'timestamp' => $timestamp,
            ]));

            return ['processed' => true, 'clickup_result' => $result];
        } else {
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
    public function getFailedMessages(): \Illuminate\Database\Eloquent\Collection
    {
        return DiscordMessage::notSentToClickUp()
            ->watchedChannels()
            ->orderBy('discord_timestamp', 'asc')
            ->get();
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
                'id' => $message->discord_message_id,
                'channel_id' => $message->channel_id,
                'guild_id' => $message->guild_id,
                'author' => ['username' => $message->username, 'discriminator' => '0000'],
                'content' => $message->content,
                'timestamp' => $message->discord_timestamp->timestamp * 1000,
                'attachments' => $message->attachments ?? [],
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
