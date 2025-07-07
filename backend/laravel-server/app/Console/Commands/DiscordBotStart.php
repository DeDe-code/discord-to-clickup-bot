<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Discord\Discord;
use Discord\WebSockets\Intents;
use Discord\Parts\Channel\Message;
use App\Services\ClickUpService;
use App\Models\DiscordMessage;
use App\Models\BotStatus;
use Illuminate\Support\Facades\Log;

class DiscordBotStart extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'discord:start';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Start the Discord bot to monitor channels and forward messages to ClickUp';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🚀 Starting Discord bot...');
        
        // Update bot status to starting
        BotStatus::updateOrCreate(
            ['service_name' => 'discord-bot'],
            [
                'is_online' => true,
                'last_ping' => now(),
                'metadata' => json_encode(['started_at' => now()->toISOString(), 'status' => 'starting'])
            ]
        );

        $token = config('services.discord.bot_token');
        $watchedChannelIds = explode(',', config('services.discord.watched_channel_ids', ''));
        
        if (empty($token)) {
            $this->error('❌ Discord bot token not configured');
            return 1;
        }

        if (empty($watchedChannelIds)) {
            $this->error('❌ No watched channel IDs configured');
            return 1;
        }

        $this->info('🔑 Bot token configured');
        $this->info('👀 Watching channels: ' . implode(', ', $watchedChannelIds));

        // Create Discord instance
        $discord = new Discord([
            'token' => $token,
            'intents' => Intents::getDefaultIntents() | Intents::MESSAGE_CONTENT,
            'loadAllMembers' => false,
        ]);

        // Bot ready event
        $discord->on('ready', function ($discord) {
            $this->info('✅ Discord bot is ready!');
            $this->info('🤖 Logged in as: ' . $discord->user->username . '#' . $discord->user->discriminator);
            
            // Update bot status to online
            BotStatus::updateOrCreate(
                ['service_name' => 'discord-bot'],
                [
                    'is_online' => true,
                    'last_ping' => now(),
                    'metadata' => json_encode([
                        'started_at' => now()->toISOString(),
                        'status' => 'online',
                        'bot_user' => $discord->user->username . '#' . $discord->user->discriminator,
                        'connection_status' => 'connected'
                    ])
                ]
            );
            
            Log::info('🤖 Discord bot is online and ready!');
        });

        // Connection closed event
        $discord->on('close', function ($code, $reason) {
            $this->error("❌ Discord connection closed: Code {$code}, Reason: {$reason}");
            
            // Update bot status to offline
            BotStatus::updateOrCreate(
                ['service_name' => 'discord-bot'],
                [
                    'is_online' => false,
                    'last_ping' => now(),
                    'metadata' => json_encode([
                        'status' => 'offline',
                        'connection_status' => 'disconnected',
                        'disconnect_reason' => $reason,
                        'disconnect_code' => $code,
                        'disconnected_at' => now()->toISOString()
                    ])
                ]
            );
            
            Log::error("Discord connection closed: Code {$code}, Reason: {$reason}");
        });

        // Reconnect event
        $discord->on('reconnect', function () {
            $this->info('🔄 Discord bot reconnecting...');
            
            // Update bot status to reconnecting
            BotStatus::updateOrCreate(
                ['service_name' => 'discord-bot'],
                [
                    'is_online' => true,
                    'last_ping' => now(),
                    'metadata' => json_encode([
                        'status' => 'reconnecting',
                        'connection_status' => 'reconnecting',
                        'reconnect_at' => now()->toISOString()
                    ])
                ]
            );
            
            Log::info('Discord bot reconnecting...');
        });

        // Message received event
        $discord->on('message', function (Message $message, Discord $discord) use ($watchedChannelIds) {
            // Skip bot messages
            if ($message->author->bot) {
                return;
            }

            // Check if message is from watched channel
            if (!in_array($message->channel_id, $watchedChannelIds)) {
                return;
            }

            $this->info("📨 New message in watched channel: {$message->content}");
            
            try {
                // Process the message
                $this->processDiscordMessage($message);
            } catch (\Exception $e) {
                $this->error("❌ Error processing message: " . $e->getMessage());
                Log::error('Error processing Discord message: ' . $e->getMessage());
            }
        });

        // Error handling
        $discord->on('error', function ($error) {
            $this->error('❌ Discord error: ' . $error->getMessage());
            Log::error('Discord error: ' . $error->getMessage());
        });

        // Start the bot
        $this->info('🔌 Connecting to Discord...');
        $discord->run();
    }

    /**
     * Process a Discord message and forward it to ClickUp
     */
    private function processDiscordMessage(Message $message)
    {
        // Check if message already exists to avoid duplicates
        $existingMessage = DiscordMessage::where('discord_message_id', $message->id)->first();
        
        if ($existingMessage) {
            $this->info("⚠️ Message already processed: {$message->id}");
            // Still mark as new message available for frontend
            cache()->put('new_message_available', true, 300);
            return;
        }

        // Save message to database
        try {
            $discordMessage = DiscordMessage::create([
                'discord_message_id' => $message->id,
                'channel_id' => $message->channel_id,
                'guild_id' => $message->guild_id,
                'username' => $message->author->username,
                'content' => $message->content,
                'attachments' => json_encode($message->attachments->toArray()),
                'discord_timestamp' => $message->timestamp,
            ]);

            Log::info("💾 Saved Discord message to database: {$message->id}");
            
            // Mark that there's a new message for frontend polling (always, regardless of ClickUp)
            cache()->put('new_message_available', true, 300); // 5 minutes cache
        } catch (\Illuminate\Database\QueryException $e) {
            if (str_contains($e->getMessage(), 'UNIQUE constraint failed')) {
                $this->warn("⚠️ Duplicate message detected, skipping: {$message->id}");
                return;
            }
            throw $e;
        }

        // Forward to ClickUp
        $clickUpService = app(ClickUpService::class);
        
        if (!$clickUpService->hasValidToken()) {
            $this->warn('⚠️ ClickUp not authenticated. Message saved but not forwarded.');
            Log::warning('ClickUp not authenticated. Message saved but not forwarded.');
            return;
        }

        // Format message for ClickUp
        $formattedMessage = $this->formatMessageForClickUp($message);
        
        // Send to ClickUp
        $result = $clickUpService->sendMessage($formattedMessage);
        
        if ($result['success']) {
            $this->info('✅ Message forwarded to ClickUp successfully!');
            Log::info('✅ Message forwarded to ClickUp successfully!');
            
            // Update Discord message record
            $discordMessage->update([
                'clickup_sent' => true,
                'clickup_response' => json_encode($result['response'] ?? [])
            ]);
        } else {
            $this->error('❌ Failed to forward message to ClickUp: ' . ($result['message'] ?? 'Unknown error'));
            Log::error('Failed to forward message to ClickUp', $result);
            
            // Update Discord message record
            $discordMessage->update([
                'clickup_sent' => false,
                'error_message' => $result['message'] ?? 'Unknown error'
            ]);
        }
    }

    /**
     * Format Discord message for ClickUp
     */
    private function formatMessageForClickUp(Message $message): string
    {
        $timestamp = $message->timestamp->format('Y-m-d H:i:s');
        $messageUrl = "https://discord.com/channels/{$message->guild_id}/{$message->channel_id}/{$message->id}";
        
        $formatted = "📨 **New Discord Message**\n\n";
        $formatted .= "**User**: {$message->author->username}#{$message->author->discriminator}\n";
        $formatted .= "**Channel**: <#{$message->channel_id}>\n";
        $formatted .= "**Time**: {$timestamp}\n";
        $formatted .= "**Message**: {$message->content}\n";
        
        if ($message->attachments->count() > 0) {
            $formatted .= "**Attachments**:\n";
            foreach ($message->attachments as $attachment) {
                $formatted .= "- [{$attachment->filename}]({$attachment->url})\n";
            }
        }
        
        $formatted .= "\n🔗 [View message in Discord]({$messageUrl})";
        
        return $formatted;
    }
}
