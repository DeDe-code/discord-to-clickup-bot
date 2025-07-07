<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class DiscordMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'discord_message_id',
        'channel_id',
        'guild_id',
        'username',
        'content',
        'attachments',
        'discord_timestamp',
        'sent_to_clickup',
        'clickup_message_id',
        'clickup_sent',
        'clickup_response',
        'error_message',
    ];

    protected $casts = [
        'attachments' => 'array',
        'discord_timestamp' => 'datetime',
        'sent_to_clickup' => 'boolean',
        'clickup_sent' => 'boolean',
        'clickup_response' => 'array',
    ];

    /**
     * Scope for messages from watched channels
     */
    public function scopeWatchedChannels($query)
    {
        $watchedChannels = explode(',', config('services.discord.watched_channel_ids', ''));
        return $query->whereIn('channel_id', $watchedChannels);
    }

    /**
     * Scope for messages not sent to ClickUp
     */
    public function scopeNotSentToClickUp($query)
    {
        return $query->where('sent_to_clickup', false);
    }

    /**
     * Mark message as sent to ClickUp
     */
    public function markAsSentToClickUp(?string $clickupMessageId = null): void
    {
        $this->update([
            'sent_to_clickup' => true,
            'clickup_message_id' => $clickupMessageId,
        ]);
    }

    /**
     * Get recent messages
     */
    public static function getRecent(int $limit = 50): \Illuminate\Database\Eloquent\Collection
    {
        return static::watchedChannels()
            ->orderBy('discord_timestamp', 'desc')
            ->limit($limit)
            ->get();
    }
}
