<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class WebSocketController extends Controller
{
    private $cacheFile;

    public function __construct()
    {
        $this->cacheFile = storage_path('framework/cache/discord_status.json');
    }

    public function getStatus()
    {
        $status = $this->getCacheStatus();
        return response()->json($status);
    }

    private function getCacheStatus()
    {
        if (file_exists($this->cacheFile)) {
            $cached = json_decode(file_get_contents($this->cacheFile), true);
            return $cached ?: $this->getDefaultStatus();
        }
        return $this->getDefaultStatus();
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

    private function getDefaultStatus()
    {
        return [
            'connected' => false,
            'last_heartbeat' => null,
            'session_id' => null,
            'username' => null
        ];
    }

    public function connect()
    {
        try {
            $token = config('services.discord.bot_token');
            
            if (empty($token)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Discord bot token not configured'
                ], 400);
            }

            // Check if already connected
            $currentStatus = $this->getCacheStatus();
            if (isset($currentStatus['connected']) && $currentStatus['connected']) {
                return response()->json([
                    'success' => false,
                    'message' => 'WebSocket is already connected'
                ], 400);
            }

            // Start the background WebSocket connection using shell_exec
            $command = sprintf(
                'cd %s && nohup php artisan websocket:connect-background > /dev/null 2>&1 & echo $!',
                base_path()
            );
            
            $pid = shell_exec($command);
            
            if ($pid) {
                Log::info("Started WebSocket process with PID: " . trim($pid));
            }

            // Mark as connecting
            $this->setCacheStatus([
                'connected' => false,
                'connecting' => true,
                'connecting_at' => date('c')
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Discord WebSocket connection started in background'
            ]);

        } catch (\Exception $e) {
            Log::error('Discord WebSocket connection error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to connect: ' . $e->getMessage()
            ], 500);
        }
    }

    public function disconnect()
    {
        try {
            $pidFile = storage_path('framework/cache/websocket.pid');
            $processKilled = false;
            
            // Check if PID file exists and process is running
            if (file_exists($pidFile)) {
                $pid = trim(file_get_contents($pidFile));
                
                if ($pid && is_numeric($pid)) {
                    // Check if process is still running
                    if (function_exists('posix_kill')) {
                        if (posix_kill((int)$pid, 0)) {
                            // Process is running, send SIGTERM signal for graceful shutdown
                            $result = posix_kill((int)$pid, 15); // SIGTERM
                            
                            if ($result) {
                                Log::info("Sent shutdown signal to process {$pid}");
                                $processKilled = true;
                                
                                // Wait briefly for graceful shutdown
                                usleep(500000); // 0.5 seconds
                                
                                // Check if process is still running, force kill if necessary
                                if (posix_kill((int)$pid, 0)) {
                                    posix_kill((int)$pid, 9); // SIGKILL
                                    Log::info("Force killed process {$pid}");
                                }
                            }
                        }
                    } else {
                        // Fallback for systems without posix functions
                        exec("kill -TERM {$pid} 2>/dev/null", $output, $return_code);
                        if ($return_code === 0) {
                            $processKilled = true;
                            usleep(500000); // 0.5 seconds
                            exec("kill -KILL {$pid} 2>/dev/null");
                        }
                    }
                }
                
                // Remove PID file if it still exists
                if (file_exists($pidFile)) {
                    unlink($pidFile);
                }
            }

            // Fallback: kill any remaining websocket processes
            exec("pkill -f 'websocket:connect-background' 2>/dev/null", $output, $return_code);
            if ($return_code === 0) {
                $processKilled = true;
            }

            // Update cache to reflect disconnection
            $this->setCacheStatus([
                'connected' => false,
                'last_heartbeat' => null,
                'session_id' => null,
                'username' => null,
                'disconnected_at' => date('c'),
                'disconnect_reason' => 'manual'
            ]);

            $message = $processKilled ? 'Discord WebSocket disconnected gracefully' : 'No active WebSocket connection found';
            
            return response()->json([
                'success' => true,
                'message' => $message
            ]);

        } catch (\Exception $e) {
            Log::error('Discord WebSocket disconnection error', [
                'message' => $e->getMessage()
            ]);

            // Still try to update cache even if there was an error
            $this->setCacheStatus([
                'connected' => false,
                'last_heartbeat' => null,
                'session_id' => null,
                'username' => null,
                'disconnected_at' => date('c'),
                'disconnect_reason' => 'error_during_disconnect'
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to disconnect: ' . $e->getMessage()
            ], 500);
        }
    }
}
