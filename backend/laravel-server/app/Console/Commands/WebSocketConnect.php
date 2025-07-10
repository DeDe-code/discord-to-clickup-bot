<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Ratchet\Client\WebSocket;
use Ratchet\Client\Connector;
use React\EventLoop\Loop;
use App\Services\DiscordBotService;

class WebSocketConnect extends Command
{
    protected $signature = 'websocket:connect-background';
    protected $description = 'Connect to Discord WebSocket in background';

    private $token;
    private $watchedChannelIds;
    private $loop;
    private $connector;
    private $conn;
    private $sessionId;
    private $sequenceNumber = null;
    private $heartbeatInterval;
    private $cacheFile;

    public function __construct()
    {
        parent::__construct();
        $this->token = config('services.discord.bot_token');
        // Get watched channel IDs from channel mappings
        $channelMappings = config('services.discord.channel_mappings', []);
        $this->watchedChannelIds = array_map('strval', array_keys($channelMappings));
        
        $this->loop = Loop::get();
        $this->connector = new Connector();
        $this->cacheFile = storage_path('framework/cache/discord_status.json');
    }

    private function setCacheStatus($status)
    {
        // Ensure cache directory exists
        $dir = dirname($this->cacheFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        
        file_put_contents($this->cacheFile, json_encode($status));
    }

    private function getCacheStatus()
    {
        if (file_exists($this->cacheFile)) {
            $cached = json_decode(file_get_contents($this->cacheFile), true);
            return $cached ?: [];
        }
        return [];
    }

    public function handle()
    {
        $this->info('Starting WebSocket connection...');
        
        if (empty($this->token)) {
            $this->error('Discord bot token not configured');
            return 1;
        }

        $this->info('Discord token found');

        // Create PID file for process management
        $pidFile = storage_path('framework/cache/websocket.pid');
        file_put_contents($pidFile, getmypid());
        $this->info('PID file created: ' . $pidFile);

        // Set up signal handlers for graceful shutdown
        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGTERM, [$this, 'handleShutdown']);
            pcntl_signal(SIGINT, [$this, 'handleShutdown']);
            pcntl_async_signals(true); // Enable async signals
            $this->info('Signal handlers set up');
        }

        // Get Discord Gateway URL
        $this->info('Getting Discord Gateway URL...');
        $gatewayResponse = Http::get('https://discord.com/api/gateway');
        if (!$gatewayResponse->successful()) {
            $this->error('Failed to get Discord gateway URL');
            return 1;
        }

        $gatewayUrl = $gatewayResponse->json('url') . '/?v=10&encoding=json';
        $this->info("Connecting to Discord Gateway: {$gatewayUrl}");

        $this->startConnection($gatewayUrl);
        return 0;
    }

    public function handleShutdown($signal)
    {
        $this->info("Received shutdown signal: {$signal}");
        
        // Close WebSocket connection gracefully
        if ($this->conn) {
            $this->conn->close();
        }

        // Update cache to reflect disconnection
        $this->setCacheStatus([
            'connected' => false,
            'disconnected_at' => date('c'),
            'disconnect_reason' => 'shutdown_signal'
        ]);

        // Remove PID file
        $pidFile = storage_path('framework/cache/websocket.pid');
        if (file_exists($pidFile)) {
            unlink($pidFile);
        }

        $this->info('WebSocket connection closed gracefully');
        exit(0);
    }

    private function startConnection($gatewayUrl)
    {
        ($this->connector)($gatewayUrl)
            ->then(function (WebSocket $conn) {
                $this->conn = $conn;
                $this->info('Connected to Discord Gateway');
                
                // Update cache
                $this->setCacheStatus([
                    'connected' => true,
                    'connected_at' => date('c'),
                    'session_id' => null,
                    'username' => null
                ]);

                // Set up message handler
                $conn->on('message', function ($msg) use ($conn) {
                    $this->handleDiscordMessage($msg, $conn);
                });

                $conn->on('close', function ($code = null, $reason = null) {
                    $this->warn("Connection closed ({$code} - {$reason})");
                    $this->setCacheStatus([
                        'connected' => false,
                        'disconnected_at' => date('c'),
                        'disconnect_code' => $code,
                        'disconnect_reason' => $reason
                    ]);
                    
                    // Remove PID file on connection close
                    $pidFile = storage_path('framework/cache/websocket.pid');
                    if (file_exists($pidFile)) {
                        unlink($pidFile);
                    }
                    
                    // Exit the process when connection is closed
                    exit(0);
                });

                $conn->on('error', function (\Exception $e) {
                    $this->error('WebSocket error: ' . $e->getMessage());
                    $this->setCacheStatus([
                        'connected' => false,
                        'error' => $e->getMessage(),
                        'error_at' => date('c')
                    ]);
                });

            }, function (\Exception $e) {
                $this->error('Could not connect to Discord: ' . $e->getMessage());
                $this->setCacheStatus([
                    'connected' => false,
                    'error' => $e->getMessage(),
                    'error_at' => date('c')
                ]);
            });

        // Start the event loop
        $this->info('Starting event loop...');
        
        // Add periodic signal checking if pcntl is available
        if (function_exists('pcntl_signal_dispatch')) {
            $this->loop->addPeriodicTimer(1, function () {
                pcntl_signal_dispatch();
            });
        }
        
        $this->loop->run();
    }

    private function handleDiscordMessage($message, $conn)
    {
        $data = json_decode($message, true);
        
        if (!$data) {
            $this->warn('Invalid JSON received from Discord');
            return;
        }

        $opcode = $data['op'] ?? null;
        $sequenceNumber = $data['s'] ?? null;

        if ($sequenceNumber !== null) {
            $this->sequenceNumber = $sequenceNumber;
        }

        switch ($opcode) {
            case 10: // Hello
                $this->handleHello($data, $conn);
                break;
            
            case 11: // Heartbeat ACK
                $this->line('Heartbeat acknowledged');
                $this->updateHeartbeat();
                break;
            
            case 0: // Dispatch
                $this->handleDispatch($data);
                break;
            
            case 1: // Heartbeat
                $this->sendHeartbeat($conn);
                break;
        }
    }

    private function handleHello($data, $conn)
    {
        $this->heartbeatInterval = $data['d']['heartbeat_interval'];
        $this->info("Heartbeat interval: {$this->heartbeatInterval}ms");
        
        // Start heartbeat timer
        $this->loop->addPeriodicTimer($this->heartbeatInterval / 1000, function () use ($conn) {
            $this->sendHeartbeat($conn);
        });

        // Send identify payload
        $this->sendIdentify($conn);
    }

    private function sendHeartbeat($conn)
    {
        $heartbeat = json_encode([
            'op' => 1,
            'd' => $this->sequenceNumber
        ]);
        
        $conn->send($heartbeat);
        $this->line('Heartbeat sent');
        $this->updateHeartbeat();
    }

    private function updateHeartbeat()
    {
        $status = $this->getCacheStatus();
        $status['last_heartbeat'] = date('c');
        $this->setCacheStatus($status);
    }

    private function sendIdentify($conn)
    {
        $intents = 513 + 32768; // GUILDS (1) + GUILD_MESSAGES (512) + MESSAGE_CONTENT (32768)
        
        $identify = json_encode([
            'op' => 2,
            'd' => [
                'token' => $this->token,
                'intents' => $intents,
                'properties' => [
                    'os' => 'linux',
                    'browser' => 'laravel-discord-bot',
                    'device' => 'laravel-discord-bot'
                ]
            ]
        ]);

        $conn->send($identify);
        $this->info("Sent identify payload with intents: {$intents} (GUILDS + GUILD_MESSAGES + MESSAGE_CONTENT)");
    }

    private function handleDispatch($data)
    {
        $eventType = $data['t'] ?? null;
        $eventData = $data['d'] ?? [];
        
        $this->info("📡 Discord Event: {$eventType}");

        switch ($eventType) {
            case 'READY':
                $this->handleReady($eventData);
                break;
            
            case 'MESSAGE_CREATE':
                $this->handleMessageCreate($eventData);
                break;
                
            default:
                $this->line("📃 Other event: {$eventType}");
                break;
        }
    }

    private function handleReady($data)
    {
        $this->sessionId = $data['session_id'];
        $username = $data['user']['username'];
        $this->info("Bot is ready! Logged in as: {$username}");
        
        // Update cache with ready status
        $status = $this->getCacheStatus();
        $status['session_id'] = $this->sessionId;
        $status['username'] = $username;
        $status['ready_at'] = date('c');
        $this->setCacheStatus($status);
    }

    private function handleMessageCreate($data)
    {
        // Log all incoming messages for debugging
        $this->info("📨 Received message from Discord:");
        $this->info("  Channel ID: " . ($data['channel_id'] ?? 'N/A'));
        $this->info("  Author: " . ($data['author']['username'] ?? 'N/A'));
        $this->info("  Content: " . ($data['content'] ?? 'N/A'));
        $this->info("  Is Bot: " . (($data['author']['bot'] ?? false) ? 'Yes' : 'No'));
        
        // Skip if no content and no attachments
        if (empty($data['content']) && empty($data['attachments'])) {
            $this->info("⏭️ Skipping message - no content or attachments");
            return;
        }

        // Check if message is from watched channels
        $channelIds = is_array($this->watchedChannelIds) ? $this->watchedChannelIds : [$this->watchedChannelIds];
        // Ensure all channel IDs are strings for comparison
        $channelIds = array_map('strval', $channelIds);
        $currentChannelId = strval($data['channel_id'] ?? '');
        
        $this->info("🔍 Watched channels: " . json_encode($channelIds));
        $this->info("📍 Current channel: {$currentChannelId}");
        
        if (empty($channelIds) || empty($channelIds[0])) {
            $this->info("⚠️ No watched channels configured - forwarding from all channels");
        } else {
            if (!in_array($currentChannelId, $channelIds)) {
                $this->info("⏭️ Skipping message - channel {$currentChannelId} is not in watched list");
                return;
            }
            $this->info("✅ Channel {$currentChannelId} is in watched list - processing message");
        }

        // Forward messages from watched channels (or all if no specific channels configured)
        $username = $data['author']['username'];
        $content = $data['content'];
        $isBot = $data['author']['bot'] ?? false;
        $botInfo = $isBot ? ' (BOT)' : '';
        
        $this->info("🔄 Processing message from {$username}{$botInfo}: {$content}");

        // Forward the message to ClickUp
        try {
            $result = $this->forwardMessageToClickUp($data);
            
            if ($result['success']) {
                $this->info("✅ Message forwarded to ClickUp successfully");
            } else {
                $this->warn("❌ Failed to forward message to ClickUp: " . ($result['error'] ?? 'Unknown error'));
            }
            
        } catch (\Exception $e) {
            $this->error("Error processing message: " . $e->getMessage());
            Log::error('Discord message processing error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    private function forwardMessageToClickUp($messageData)
    {
        try {
            // Get ClickUp service
            $clickUpService = app(\App\Services\ClickUpService::class);
            
            // Debug log the message data
            $this->info("📝 Message data: " . json_encode($messageData, JSON_PRETTY_PRINT));
            
            // Format message for ClickUp - handle timestamp conversion safely
            $timestamp = isset($messageData['timestamp']) 
                ? date('Y-m-d H:i:s', intval($messageData['timestamp']) / 1000)
                : date('Y-m-d H:i:s');
            
            $author = $messageData['author']['username'] ?? 'Unknown';
            $isBot = $messageData['author']['bot'] ?? false;
            $botInfo = $isBot ? ' 🤖' : '';
            
            $guildId = $messageData['guild_id'] ?? 'unknown';
            $channelId = $messageData['channel_id'] ?? 'unknown';
            $messageId = $messageData['id'] ?? 'unknown';
            
            $discordLink = "https://discord.com/channels/{$guildId}/{$channelId}/{$messageId}";

            $attachmentLinks = '';
            if (!empty($messageData['attachments'])) {
                $attachmentLinks = "\n📎 Attachments:\n";
                foreach ($messageData['attachments'] as $attachment) {
                    $filename = $attachment['filename'] ?? 'Unknown file';
                    $url = $attachment['url'] ?? '#';
                    $attachmentLinks .= "- [{$filename}]({$url})\n";
                }
            }

            $content = "📨 **Discord Message**{$botInfo}\n\n" .
                      "**User**: {$author}\n" .
                      "**Time**: {$timestamp}\n" .
                      "**Channel**: <#{$channelId}>\n" .
                      "**Message**: {$messageData['content']}\n" .
                      "🔗 [View in Discord]({$discordLink})\n" .
                      $attachmentLinks;

            // Get channel mappings from config
            $channelMappings = config('services.discord.channel_mappings', []);
            $this->info("📍 Channel mappings: " . json_encode($channelMappings));
            
            // Try to find mapped ClickUp channel, otherwise use default
            $clickUpChannelId = $channelMappings[$messageData['channel_id']] ?? null;
            
            if (!$clickUpChannelId) {
                // Use first available channel mapping or create a default
                $clickUpChannelId = !empty($channelMappings) ? array_values($channelMappings)[0] : 'general';
                $this->warn("No mapping found for Discord channel {$messageData['channel_id']}, using default: {$clickUpChannelId}");
            }

            $this->info("🎯 Sending to ClickUp channel: {$clickUpChannelId}");

            // Send to ClickUp
            $result = $clickUpService->sendMessage($content, $clickUpChannelId);

            if ($result['success']) {
                Log::info('✅ Message sent to ClickUp chat channel successfully');
                return ['success' => true, 'clickup_result' => $result];
            } else {
                Log::error('❌ Failed to send message to ClickUp', $result);
                return ['success' => false, 'error' => $result['error'] ?? 'Unknown error'];
            }
            
        } catch (\Exception $e) {
            $this->error("🚨 Exception in forwardMessageToClickUp: " . $e->getMessage());
            $this->error("📍 Line: " . $e->getLine() . " in " . $e->getFile());
            Log::error('Error in forwardMessageToClickUp', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'messageData' => $messageData
            ]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
