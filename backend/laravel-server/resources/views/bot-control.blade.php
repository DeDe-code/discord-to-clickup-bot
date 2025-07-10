<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Discord to ClickUp Bot</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="container mx-auto px-4 py-8" x-data="botController()">
        <div class="max-w-2xl mx-auto">
            <h1 class="text-3xl font-bold text-gray-800 mb-8 text-center">Discord to ClickUp Bot</h1>
            
            <!-- Status Card -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <h2 class="text-xl font-semibold text-gray-700 mb-4">WebSocket Status</h2>
                
                <div class="flex items-center justify-between mb-4">
                    <span class="text-gray-600">Connection Status:</span>
                    <span class="px-3 py-1 rounded-full text-sm font-medium" 
                          :class="status.connected ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'">
                        <span x-text="status.connected ? 'Connected' : 'Disconnected'"></span>
                    </span>
                </div>
                
                <div class="space-y-2 text-sm text-gray-600" x-show="status.connected">
                    <div x-show="status.username">
                        <strong>Bot Username:</strong> <span x-text="status.username"></span>
                    </div>
                    <div x-show="status.last_heartbeat">
                        <strong>Last Heartbeat:</strong> <span x-text="formatTime(status.last_heartbeat)"></span>
                    </div>
                    <div x-show="status.session_id">
                        <strong>Session ID:</strong> <span x-text="status.session_id"></span>
                    </div>
                </div>
                
                <div class="text-sm text-gray-600" x-show="!status.connected && status.error">
                    <div class="bg-red-50 border border-red-200 rounded p-3">
                        <strong>Error:</strong> <span x-text="status.error"></span>
                    </div>
                </div>
            </div>

            <!-- Control Buttons -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <h2 class="text-xl font-semibold text-gray-700 mb-4">Bot Control</h2>
                
                <div class="flex space-x-4">
                    <button @click="connect()" 
                            :disabled="loading || status.connected"
                            :class="status.connected ? 'bg-gray-400 cursor-not-allowed' : 'bg-blue-500 hover:bg-blue-600'"
                            class="px-4 py-2 text-white rounded-lg font-medium transition-colors">
                        <span x-show="!loading">Connect</span>
                        <span x-show="loading">Connecting...</span>
                    </button>
                    
                    <button @click="disconnect()" 
                            :disabled="loading || !status.connected"
                            :class="!status.connected ? 'bg-gray-400 cursor-not-allowed' : 'bg-red-500 hover:bg-red-600'"
                            class="px-4 py-2 text-white rounded-lg font-medium transition-colors">
                        <span x-show="!loading">Disconnect</span>
                        <span x-show="loading">Disconnecting...</span>
                    </button>
                    
                    <button @click="refreshStatus()" 
                            :disabled="loading"
                            class="px-4 py-2 bg-gray-500 hover:bg-gray-600 text-white rounded-lg font-medium transition-colors">
                        Refresh
                    </button>
                </div>
            </div>

            <!-- Messages -->
            <div class="bg-white rounded-lg shadow-md p-6" x-show="message">
                <div class="p-4 rounded-lg" :class="messageType === 'success' ? 'bg-green-50 border border-green-200 text-green-800' : 'bg-red-50 border border-red-200 text-red-800'">
                    <p x-text="message"></p>
                </div>
            </div>
        </div>
    </div>

    <script>
        function botController() {
            return {
                status: {
                    connected: false,
                    last_heartbeat: null,
                    session_id: null,
                    username: null,
                    error: null
                },
                loading: false,
                message: '',
                messageType: 'success',

                async init() {
                    await this.refreshStatus();
                    // Auto-refresh every 30 seconds
                    setInterval(() => this.refreshStatus(), 30000);
                },

                async refreshStatus() {
                    try {
                        const response = await fetch('/api/websocket/status');
                        const data = await response.json();
                        this.status = data;
                        this.clearMessage();
                    } catch (error) {
                        this.showMessage('Failed to get status: ' + error.message, 'error');
                    }
                },

                async connect() {
                    this.loading = true;
                    this.clearMessage();
                    
                    try {
                        const response = await fetch('/api/websocket/connect', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                            }
                        });
                        
                        const data = await response.json();
                        
                        if (data.success) {
                            this.showMessage(data.message, 'success');
                            // Wait a bit then refresh status
                            setTimeout(() => this.refreshStatus(), 2000);
                        } else {
                            this.showMessage(data.message, 'error');
                        }
                    } catch (error) {
                        this.showMessage('Connection failed: ' + error.message, 'error');
                    } finally {
                        this.loading = false;
                    }
                },

                async disconnect() {
                    this.loading = true;
                    this.clearMessage();
                    
                    try {
                        const response = await fetch('/api/websocket/disconnect', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                            }
                        });
                        
                        const data = await response.json();
                        
                        if (data.success) {
                            this.showMessage(data.message, 'success');
                            await this.refreshStatus();
                        } else {
                            this.showMessage(data.message, 'error');
                        }
                    } catch (error) {
                        this.showMessage('Disconnection failed: ' + error.message, 'error');
                    } finally {
                        this.loading = false;
                    }
                },

                showMessage(msg, type) {
                    this.message = msg;
                    this.messageType = type;
                    setTimeout(() => this.clearMessage(), 5000);
                },

                clearMessage() {
                    this.message = '';
                },

                formatTime(timestamp) {
                    if (!timestamp) return '';
                    return new Date(timestamp).toLocaleString();
                }
            }
        }
    </script>
</body>
</html>
