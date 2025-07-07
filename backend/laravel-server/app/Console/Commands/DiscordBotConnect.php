<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Ratchet\Client\WebSocket;
use Ratchet\Client\Connector;
use React\EventLoop\Loop;
use Ratchet\Client\Exception\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Services\DiscordBotService;
use App\Models\BotStatus;

class DiscordBotConnect extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'discord:connect {--stop : Stop the Discord bot}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Connect to Discord as a bot and monitor channels';

    private $token;
    private $watchedChannelId;
    private $gatewayUrl;
    private $sessionId;
    private $sequenceNumber = null;
    private $heartbeatInterval;
    private $loop;
    private $connector;

    public function __construct()
    {
        parent::__construct();
        $this->token = config('services.discord.bot_token');
        $this->watchedChannelId = config('services.discord.watched_channel_ids');
        $this->loop = Loop::get();
        $this->connector = new Connector();
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if ($this->option('stop')) {
            return $this->stopBot();
        }

        if (empty($this->token)) {
            $this->error('Discord bot token not configured in .env file');
            return 1;
        }

        $this->info('🤖 Starting Discord bot connection...');
        
        // Get Discord Gateway URL
        $gatewayResponse = Http::get('https://discord.com/api/gateway');
        if (!$gatewayResponse->successful()) {
            $this->error('Failed to get Discord gateway URL');
            return 1;
        }

        $this->gatewayUrl = $gatewayResponse->json('url') . '/?v=10&encoding=json';
        $this->info("🔗 Gateway URL: {$this->gatewayUrl}");

        // Update bot status in database
        BotStatus::updateStatus('discord-bot', true, [
            'started_at' => now()->toISOString(),
            'version' => '1.0.0',
            'gateway_url' => $this->gatewayUrl
        ]);

        // Connect to Discord Gateway
        $this->connectToDiscord();

        return 0;
    }

    private function connectToDiscord()
    {
        $this->info('🔌 Connecting to Discord Gateway...');

        ($this->connector)($this->gatewayUrl)
            ->then(function (WebSocket $conn) {
                $this->info('✅ Connected to Discord Gateway!');
                
                // Set up message handler
                $conn->on('message', function ($msg) use ($conn) {
                    $this->handleDiscordMessage($msg, $conn);
                });

                $conn->on('close', function ($code = null, $reason = null) {
                    $this->warn("❌ Connection closed ({$code} - {$reason})");
                    BotStatus::updateStatus('discord-bot', false, [
                        'disconnected_at' => now()->toISOString(),
                        'disconnect_code' => $code,
                        'disconnect_reason' => $reason
                    ]);
                });

                $conn->on('error', function (\Exception $e) {
                    $this->error("❌ WebSocket error: " . $e->getMessage());
                });

            }, function (\Exception $e) {
                $this->error("❌ Could not connect to Discord: " . $e->getMessage());
                return 1;
            });

        // Start the event loop
        $this->info('🔄 Starting event loop...');
        $this->loop->run();
    }

    private function handleDiscordMessage($message, $conn)
    {
        $data = json_decode($message, true);
        
        if (!$data) {
            $this->warn('⚠️ Invalid JSON received from Discord');
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
                $this->line('💓 Heartbeat acknowledged');
                break;
            
            case 0: // Dispatch
                $this->handleDispatch($data);
                break;
            
            case 1: // Heartbeat
                $this->sendHeartbeat($conn);
                break;
            
            case 7: // Reconnect
                $this->warn('🔄 Discord requested reconnection');
                break;
            
            case 9: // Invalid Session
                $this->warn('❌ Invalid session, reconnecting...');
                break;
            
            default:
                $this->line("📋 Received opcode {$opcode}");
                break;
        }
    }

    private function handleHello($data, $conn)
    {
        $this->heartbeatInterval = $data['d']['heartbeat_interval'];
        $this->info("💓 Heartbeat interval: {$this->heartbeatInterval}ms");
        
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
        $this->line('💓 Heartbeat sent');
    }

    private function sendIdentify($conn)
    {
        $identify = json_encode([
            'op' => 2,
            'd' => [
                'token' => $this->token,
                'intents' => 513, // GUILDS + GUILD_MESSAGES
                'properties' => [
                    'os' => 'linux',
                    'browser' => 'laravel-discord-bot',
                    'device' => 'laravel-discord-bot'
                ]
            ]
        ]);

        $conn->send($identify);
        $this->info('🔐 Sent identify payload');
    }

    private function handleDispatch($data)
    {
        $eventType = $data['t'] ?? null;
        $eventData = $data['d'] ?? [];

        switch ($eventType) {
            case 'READY':
                $this->handleReady($eventData);
                break;
            
            case 'MESSAGE_CREATE':
                $this->handleMessageCreate($eventData);
                break;
            
            case 'GUILD_CREATE':
                $this->handleGuildCreate($eventData);
                break;
            
            default:
                // Uncomment for debugging
                // $this->line("📨 Event: {$eventType}");
                break;
        }
    }

    private function handleReady($data)
    {
        $this->sessionId = $data['session_id'];
        $username = $data['user']['username'];
        $this->info("🎉 Bot is ready! Logged in as: {$username}");
        $this->info("🆔 Session ID: {$this->sessionId}");
        
        // Update bot status
        BotStatus::updateStatus('discord-bot', true, [
            'ready_at' => now()->toISOString(),
            'session_id' => $this->sessionId,
            'username' => $username
        ]);
    }

    private function handleGuildCreate($data)
    {
        $guildName = $data['name'];
        $guildId = $data['id'];
        $this->info("🏰 Connected to guild: {$guildName} (ID: {$guildId})");
    }

    private function handleMessageCreate($data)
    {
        // Skip bot messages
        if (isset($data['author']['bot']) && $data['author']['bot']) {
            return;
        }

        // Check if message is from watched channel
        if ($data['channel_id'] !== $this->watchedChannelId) {
            return;
        }

        $username = $data['author']['username'];
        $content = $data['content'];
        $channelId = $data['channel_id'];
        
        $this->info("📨 New message in watched channel from {$username}: {$content}");

        // Process the message through our existing service
        try {
            $discordService = app(DiscordBotService::class);
            
            // Convert Discord timestamp to milliseconds
            $timestamp = strtotime($data['timestamp']) * 1000;
            
            $messageData = [
                'id' => $data['id'],
                'channel_id' => $data['channel_id'],
                'guild_id' => $data['guild_id'] ?? null,
                'author' => $data['author'],
                'content' => $data['content'],
                'timestamp' => $timestamp,
                'attachments' => $data['attachments'] ?? []
            ];

            $result = $discordService->processDiscordMessage($messageData);
            
            if ($result['processed']) {
                $this->info("✅ Message forwarded to ClickUp successfully");
            } else {
                $this->warn("❌ Failed to forward message to ClickUp");
            }
            
        } catch (\Exception $e) {
            $this->error("❌ Error processing message: " . $e->getMessage());
            Log::error('Discord message processing error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    private function stopBot()
    {
        $this->info('🛑 Stopping Discord bot...');
        
        // Update bot status in database
        BotStatus::updateStatus('discord-bot', false, [
            'stopped_at' => now()->toISOString(),
            'stop_reason' => 'manual'
        ]);
        
        $this->info('✅ Discord bot stopped');
        return 0;
    }
}
