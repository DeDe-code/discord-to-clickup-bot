<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BotStatus extends Model
{
    use HasFactory;

    protected $table = 'bot_status';

    protected $fillable = [
        'service_name',
        'is_online',
        'last_ping',
        'metadata',
    ];

    protected $casts = [
        'is_online' => 'boolean',
        'last_ping' => 'datetime',
        'metadata' => 'array',
    ];

    /**
     * Update or create bot status
     */
    public static function updateStatus(string $serviceName, bool $isOnline, ?array $metadata = null): self
    {
        return static::updateOrCreate(
            ['service_name' => $serviceName],
            [
                'is_online' => $isOnline,
                'last_ping' => now(),
                'metadata' => $metadata,
            ]
        );
    }

    /**
     * Get Discord bot status
     */
    public static function getDiscordBotStatus(): ?self
    {
        return static::where('service_name', 'discord-bot')->first();
    }

    /**
     * Check if service is online
     */
    public function isOnline(): bool
    {
        // First check if the database status is online
        if (!$this->is_online) {
            return false;
        }

        // For Discord bot, check if the process is actually running
        if ($this->service_name === 'discord-bot') {
            return $this->isDiscordBotProcessRunning();
        }

        // For other services, use the ping-based approach
        return $this->last_ping && 
               $this->last_ping->greaterThan(now()->subMinutes(5));
    }

    /**
     * Check if Discord bot process is running
     */
    private function isDiscordBotProcessRunning(): bool
    {
        try {
            // Check if the discord:start process is running
            $output = shell_exec('pgrep -f "discord:start" 2>/dev/null');
            return !empty(trim($output));
        } catch (\Exception $e) {
            // If we can't check the process, fall back to ping-based check
            return $this->last_ping && 
                   $this->last_ping->greaterThan(now()->subMinutes(10)); // More lenient for fallback
        }
    }
}
