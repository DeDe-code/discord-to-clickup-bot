<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class ClickUpService
{
    private const TOKEN_FILE = 'clickup_token.json';
    private const TOKEN_CACHE_KEY = 'clickup_access_token';

    /**
     * Exchange authorization code for access token
     */
    public function exchangeCodeForToken(string $code): ?string
    {
        $clientId = config('services.clickup.client_id');
        $clientSecret = config('services.clickup.client_secret');
        $redirectUri = config('app.url') . '/auth/clickup/callback';

        try {
            $response = Http::post('https://api.clickup.com/api/v2/oauth/token', [
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'code' => $code,
                'redirect_uri' => $redirectUri,
            ]);

            if ($response->successful()) {
                $token = $response->json('access_token');
                $this->saveToken($token);
                Log::info('✅ ClickUp access token obtained and saved.');
                return $token;
            }

            Log::error('❌ Error getting ClickUp token:', $response->json());
            return null;
        } catch (\Exception $e) {
            Log::error('❌ Exception getting ClickUp token: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Save token to storage and cache
     */
    private function saveToken(string $token): void
    {
        try {
            // Save to file
            Storage::put(self::TOKEN_FILE, json_encode(['access_token' => $token]));
            
            // Cache for quick access
            Cache::put(self::TOKEN_CACHE_KEY, $token, now()->addDays(30));
            
            Log::info('💾 ClickUp token saved to file and cache.');
        } catch (\Exception $e) {
            Log::error('❌ Failed to save token: ' . $e->getMessage());
        }
    }

    /**
     * Load token from storage or cache
     */
    public function loadToken(): ?string
    {
        // Try cache first
        $token = Cache::get(self::TOKEN_CACHE_KEY);
        if ($token) {
            return $token;
        }

        // Try file storage
        if (Storage::exists(self::TOKEN_FILE)) {
            try {
                $data = json_decode(Storage::get(self::TOKEN_FILE), true);
                $token = $data['access_token'] ?? null;
                
                if ($token) {
                    // Update cache
                    Cache::put(self::TOKEN_CACHE_KEY, $token, now()->addDays(30));
                    Log::info('🔄 ClickUp token loaded from file.');
                    return $token;
                }
            } catch (\Exception $e) {
                Log::warning('⚠️ Failed to parse ClickUp token file: ' . $e->getMessage());
            }
        }

        return null;
    }

    /**
     * Check if we have a valid token
     */
    public function hasValidToken(): bool
    {
        $token = $this->loadToken();
        return !empty($token);
    }

    /**
     * Check if token file exists
     */
    public function tokenExists(): bool
    {
        return Storage::exists(self::TOKEN_FILE) || Cache::has(self::TOKEN_CACHE_KEY);
    }

    /**
     * Revoke and remove token
     */
    public function revokeToken(): void
    {
        Cache::forget(self::TOKEN_CACHE_KEY);
        Storage::delete(self::TOKEN_FILE);
        Log::info('🗑️ ClickUp token revoked and removed.');
    }

    /**
     * Send message to ClickUp channel
     */
    public function sendMessage(string $content, string $channelId): array
    {
        $token = $this->loadToken();
        
        if (!$token) {
            Log::warning('⚠️ No ClickUp token. Please authenticate.');
            return ['success' => false, 'reason' => 'unauthenticated'];
        }

        $workspaceId = config('services.clickup.workspace_id');
        $url = "https://api.clickup.com/api/v3/workspaces/{$workspaceId}/chat/channels/{$channelId}/messages";

        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$token}",
                'Content-Type' => 'application/json',
            ])->post($url, [
                'content' => $content
            ]);

            if ($response->successful()) {
                Log::info('✅ Message sent to ClickUp chat channel successfully');
                return ['success' => true, 'response' => $response->json()];
            }

            $status = $response->status();
            $message = $response->json();

            if ($status === 401) {
                Log::error('🔒 ClickUp token is invalid or expired. Removing saved token.');
                $this->revokeToken();
            } else {
                Log::error('❌ ClickUp API error:', ['status' => $status, 'message' => $message]);
            }

            return [
                'success' => false,
                'reason' => 'request_failed',
                'status' => $status,
                'message' => $message
            ];
        } catch (\Exception $e) {
            Log::error('❌ Exception sending to ClickUp: ' . $e->getMessage());
            return [
                'success' => false,
                'reason' => 'exception',
                'message' => $e->getMessage()
            ];
        }
    }
}
