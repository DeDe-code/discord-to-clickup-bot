<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class FileMessageService
{
    private $messagesFile = 'discord_messages.json';
    private $statusFile = 'bot_status.json';
    private $maxMessages = 10; // LIMIT: Keep only last 10 messages

    /**
     * Store a Discord message
     */
    public function storeMessage(array $messageData): array
    {
        $messages = $this->getMessages();
        
        $message = [
            'id' => $messageData['discord_message_id'] ?? uniqid(),
            'discord_message_id' => $messageData['discord_message_id'] ?? null,
            'channel_id' => $messageData['channel_id'] ?? null,
            'username' => $messageData['username'] ?? 'Unknown',
            'content' => $messageData['content'] ?? '',
            'discord_timestamp' => $messageData['discord_timestamp'] ?? now()->toISOString(),
            'clickup_sent' => $messageData['clickup_sent'] ?? false,
            'clickup_channel_id' => $messageData['clickup_channel_id'] ?? null,
            'clickup_response' => $messageData['clickup_response'] ?? null,
            'error_message' => $messageData['error_message'] ?? null,
            'created_at' => now()->toISOString(),
            'updated_at' => now()->toISOString(),
        ];

        // Add to beginning of array (most recent first)
        array_unshift($messages, $message);

        // LIMIT: Keep only the last 10 messages
        if (count($messages) > $this->maxMessages) {
            $messages = array_slice($messages, 0, $this->maxMessages);
        }

        $this->saveMessages($messages);
        
        // Return the stored message
        return $message;
    }

    /**
     * Get recent messages
     */
    public function getRecentMessages(int $limit = 50): array
    {
        $messages = $this->getMessages();
        return array_slice($messages, 0, $limit);
    }

    /**
     * Get failed messages (not sent to ClickUp)
     */
    public function getFailedMessages(): array
    {
        $messages = $this->getMessages();
        return array_filter($messages, function ($message) {
            return !($message['clickup_sent'] ?? false);
        });
    }

    /**
     * Update message status
     */
    public function updateMessage(string $messageId, array $updateData): void
    {
        $messages = $this->getMessages();
        
        foreach ($messages as $index => $message) {
            if ($message['id'] === $messageId || $message['discord_message_id'] === $messageId) {
                $messages[$index] = array_merge($message, $updateData);
                $messages[$index]['updated_at'] = now()->toISOString();
                break;
            }
        }

        $this->saveMessages($messages);
    }

    /**
     * Mark message as sent to ClickUp
     */
    public function markAsSentToClickUp(string $messageId, string $clickupChannelId, array $response = null): void
    {
        $this->updateMessage($messageId, [
            'clickup_sent' => true,
            'clickup_channel_id' => $clickupChannelId,
            'clickup_response' => $response,
            'error_message' => null,
        ]);
    }

    /**
     * Mark message as failed
     */
    public function markAsFailed(string $messageId, string $errorMessage): void
    {
        $this->updateMessage($messageId, [
            'clickup_sent' => false,
            'error_message' => $errorMessage,
        ]);
    }

    /**
     * Get all messages from storage
     */
    private function getMessages(): array
    {
        if (!Storage::exists($this->messagesFile)) {
            return [];
        }

        $content = Storage::get($this->messagesFile);
        $messages = json_decode($content, true);
        
        return is_array($messages) ? $messages : [];
    }

    /**
     * Save messages to storage
     */
    private function saveMessages(array $messages): void
    {
        Storage::put($this->messagesFile, json_encode($messages, JSON_PRETTY_PRINT));
    }

    /**
     * Get bot status
     */
    public function getBotStatus(): array
    {
        if (!Storage::exists($this->statusFile)) {
            return [
                'status' => 'disconnected',
                'last_seen' => null,
                'message_count' => 0,
            ];
        }

        $content = Storage::get($this->statusFile);
        $status = json_decode($content, true);
        
        return is_array($status) ? $status : [
            'status' => 'disconnected',
            'last_seen' => null,
            'message_count' => 0,
        ];
    }

    /**
     * Update bot status
     */
    public function updateBotStatus(array $statusData): void
    {
        $currentStatus = $this->getBotStatus();
        $newStatus = array_merge($currentStatus, $statusData);
        $newStatus['updated_at'] = now()->toISOString();

        Storage::put($this->statusFile, json_encode($newStatus, JSON_PRETTY_PRINT));
    }

    /**
     * Get message statistics
     */
    public function getMessageStats(): array
    {
        $messages = $this->getMessages();
        
        $total = count($messages);
        $sent = count(array_filter($messages, fn($m) => $m['clickup_sent'] ?? false));
        $failed = $total - $sent;

        return [
            'total_messages' => $total,
            'sent_to_clickup' => $sent,
            'failed_messages' => $failed,
            'success_rate' => $total > 0 ? round(($sent / $total) * 100, 2) : 0,
        ];
    }

    /**
     * Get messages by channel
     */
    public function getMessagesByChannel(string $channelId, int $limit = 50): array
    {
        $messages = $this->getMessages();
        
        $filtered = array_filter($messages, function ($message) use ($channelId) {
            return ($message['channel_id'] ?? '') === $channelId;
        });

        return array_slice($filtered, 0, $limit);
    }

    /**
     * Clear old messages (keep only last N days)
     */
    public function clearOldMessages(int $days = 30): int
    {
        $messages = $this->getMessages();
        $cutoffDate = Carbon::now()->subDays($days);
        
        $filtered = array_filter($messages, function ($message) use ($cutoffDate) {
            $messageDate = Carbon::parse($message['created_at'] ?? $message['discord_timestamp'] ?? now());
            return $messageDate->isAfter($cutoffDate);
        });

        $removedCount = count($messages) - count($filtered);
        $this->saveMessages(array_values($filtered));

        return $removedCount;
    }

    /**
     * Get storage statistics
     */
    public function getStats(): array
    {
        $messages = $this->getMessages();
        $successful = array_filter($messages, fn($msg) => $msg['clickup_sent'] ?? false);
        $failed = array_filter($messages, fn($msg) => !($msg['clickup_sent'] ?? false));
        
        $filePath = storage_path('app/' . $this->messagesFile);
        $fileSize = file_exists($filePath) ? filesize($filePath) : 0;
        
        return [
            'total_messages' => count($messages),
            'successful_forwards' => count($successful),
            'failed_forwards' => count($failed),
            'max_storage_limit' => $this->maxMessages,
            'file_size_kb' => round($fileSize / 1024, 2),
            'file_size_bytes' => $fileSize,
        ];
    }
}
