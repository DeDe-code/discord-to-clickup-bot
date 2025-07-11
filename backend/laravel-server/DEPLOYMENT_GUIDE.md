# Discord to ClickUp Bot - Simplified Web-based Version

## Overview

This is a simplified solution for managing the Discord-ClickUp bot through a web interface. The bot uses WebSocket connection with the Discord Gateway and forwards all incoming messages directly to ClickUp.

## Key Features

- **Web Interface**: View and control bot status from a web browser
- **WebSocket Connection**: Real-time Discord message monitoring
- **Simple Installation**: No CLI access required
- **Cache-based State**: Bot status stored in cache (no database)
- **Background Processing**: WebSocket connection runs in the background

## Installation

### 1. Upload Files

Upload the `backend/laravel-server` folder to your hosting provider.

### 2. Environment Variables Setup

Edit the `.env` file:

```env
APP_NAME="Discord to ClickUp Bot"
APP_ENV=production
APP_KEY=base64:your-generated-key
APP_DEBUG=false
APP_URL=http://your-domain.com

# Discord Bot Settings
DISCORD_BOT_TOKEN=your_discord_bot_token_here
DISCORD_WATCHED_CHANNEL_IDS=["channel_id_1","channel_id_2"]

# ClickUp Settings
CLICKUP_CLIENT_ID=your_clickup_client_id
CLICKUP_CLIENT_SECRET=your_clickup_client_secret
CLICKUP_REDIRECT_URI=http://your-domain.com/api/auth/clickup/callback

# API Security
API_SECRET=your_secret_key_here

# Cache Settings
CACHE_DRIVER=file
SESSION_DRIVER=file
QUEUE_CONNECTION=sync
```

### 3. Dependencies Installation

If you have composer access:

```bash
composer install --no-dev --optimize-autoloader
```

### 4. Permissions Setup

Make sure the following folders are writable:

- `storage/`
- `bootstrap/cache/`

## Usage

### Web Interface Access

Open the website in your browser: `http://your-domain.com`

### Bot Control

1. **View Status**: The main page shows the WebSocket connection status
2. **Connect**: Click the "Connect" button to connect to the Discord Gateway
3. **Disconnect**: Click the "Disconnect" button to close the connection
4. **Refresh**: Use the "Refresh" button to update the status

### Message Handling

The bot automatically:

- Monitors configured Discord channels
- Forwards all messages (including bot messages) to ClickUp
- Logs events

## API Endpoints

### WebSocket Control

- `GET /api/websocket/status` - WebSocket connection status
- `POST /api/websocket/connect` - Connect to Discord Gateway
- `POST /api/websocket/disconnect` - Disconnect from Discord Gateway

### ClickUp Authentication

- `GET /api/auth/clickup` - ClickUp OAuth redirect
- `GET /api/auth/clickup/callback` - ClickUp OAuth callback
- `GET /api/auth/clickup/status` - ClickUp authentication status

## Technical Details

### File Structure

```
backend/laravel-server/
├── app/
│   ├── Console/Commands/
│   │   └── WebSocketConnect.php          # Background WebSocket connection
│   ├── Http/Controllers/
│   │   └── WebSocketController.php       # Web API controller
│   └── Services/
│       └── DiscordBotService.php         # Discord message processing
├── resources/views/
│   └── bot-control.blade.php             # Web interface
├── routes/
│   ├── web.php                           # Web routes
│   └── api.php                           # API routes
└── .env                                  # Configuration file
```

### Cache Usage

Bot status is stored in cache:

- `discord_websocket_status` - WebSocket connection status
- TTL: 3600 seconds (1 hour)

### Background Processes

WebSocket connection runs in the background:

- Artisan command: `php artisan websocket:connect-background`
- Process management: `pkill -f 'websocket:connect-background'`

## Troubleshooting

### WebSocket Won't Connect

1. Check Discord bot token validity
2. Check firewall settings
3. Review Laravel log files (`storage/logs/`)

### Messages Not Arriving

1. Check Discord channel IDs
2. Check bot permissions on Discord server
3. Check ClickUp API settings

### Web Interface Not Accessible

1. Check web server configuration
2. Check file permissions
3. Check Laravel route cache

## Hosting Requirements

- PHP 8.1+
- Composer (for installation)
- Writable `storage/` and `bootstrap/cache/` folders
- Outbound HTTPS connection (Discord API)
- WebSocket support (reactive PHP libraries)

## Security Notes

- API endpoints are protected (except WebSocket control)
- Keep API keys secure
- Do not share Discord bot token
- Use HTTPS in production environment

## Maintenance

- Regularly check log files
- Monitor bot status
- Update dependencies as needed
- Make regular security backups

## Support

If you encounter problems:

1. Check log files
2. Verify configuration
3. Test API endpoints
4. Check Discord bot permissions
