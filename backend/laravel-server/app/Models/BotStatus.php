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
        // Consider service offline if last ping was more than 5 minutes ago
        return $this->is_online && 
               $this->last_ping && 
               $this->last_ping->greaterThan(now()->subMinutes(5));
    }
}
