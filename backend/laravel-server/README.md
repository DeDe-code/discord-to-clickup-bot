# Discord to ClickUp Bot - Laravel Server

This is a Laravel-based implementation of the Discord to ClickUp bot that provides the same functionality as the Node.js server.js but using PHP and Laravel framework.

## Features

- ✅ Bot status monitoring and control
- ✅ ClickUp OAuth2 authentication
- ✅ Discord message processing via webhooks
- ✅ Real-time updates via Laravel Broadcasting (Pusher)
- ✅ API key authentication
- ✅ Message forwarding to ClickUp channels
- ✅ Recent messages storage and retrieval

## Installation

1. **Install Dependencies**

   ```bash
   cd laravel-server
   composer install
   ```

2. **Environment Setup**

   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

3. **Configure Environment Variables**
   Update your `.env` file with your Discord and ClickUp credentials:

   ```env
   API_SECRET=your_api_secret_here
   DISCORD_BOT_TOKEN=your_discord_bot_token
   CLICKUP_CLIENT_ID=your_clickup_client_id
   CLICKUP_CLIENT_SECRET=your_clickup_client_secret
   CLICKUP_WORKSPACE_ID=your_workspace_id
   CLICKUP_CHANNEL_ID=your_channel_id
   WATCHED_CHANNEL_IDS=channel_id_1,channel_id_2
   ```

4. **Database Setup**

   ```bash
   php artisan migrate
   ```

5. **Start the Server**
   ```bash
   php artisan serve --host=0.0.0.0 --port=8000
   ```

## API Endpoints

### Bot Control

- `GET /api/status` - Get bot and ClickUp authentication status
- `POST /api/bot/start` - Start the Discord bot (simulated)
- `POST /api/bot/stop` - Stop the Discord bot (simulated)
- `GET /api/messages` - Get recent Discord messages

### ClickUp Authentication

- `GET /api/auth/clickup` - Redirect to ClickUp OAuth
- `GET /api/auth/clickup/callback` - Handle OAuth callback
- `GET /api/auth/clickup/status` - Get authentication status
- `DELETE /api/auth/clickup/revoke` - Revoke ClickUp token

### Discord Integration

- `POST /api/discord/webhook` - Handle Discord webhooks (no API key required)
- `POST /api/discord/simulate` - Simulate Discord message for testing

### Utility

- `GET /api/health` - Health check endpoint

## Authentication

Most endpoints require an API key passed via:

- Header: `X-API-Key: your_api_secret`
- Query parameter: `?api_key=your_api_secret`
- Body parameter: `api_key: your_api_secret`

## Real-time Updates

The Laravel server uses Laravel Broadcasting with Pusher for real-time updates. Configure your Pusher credentials in the `.env` file:

```env
BROADCAST_CONNECTION=pusher
PUSHER_APP_ID=your_app_id
PUSHER_APP_KEY=your_app_key
PUSHER_APP_SECRET=your_app_secret
PUSHER_APP_CLUSTER=your_cluster
```

## Discord Integration Options

Since Laravel is a PHP framework and doesn't have native Discord.js support, there are several approaches to integrate with Discord:

### 1. Webhooks (Recommended)

Configure Discord webhooks to send messages to `/api/discord/webhook`

### 2. External Bot Process

Run a separate Discord bot process that communicates with the Laravel API

### 3. Scheduled API Polling

Use Laravel's scheduler to poll Discord's API for new messages

## Artisan Commands

- `php artisan bot:start` - Start the bot (simulated)
- `php artisan bot:stop` - Stop the bot
- `php artisan bot:status` - Check bot status

## Differences from Node.js Version

1. **Framework**: Uses Laravel instead of Express.js
2. **Language**: PHP instead of JavaScript
3. **Real-time**: Uses Pusher/Broadcasting instead of Socket.IO
4. **Discord Integration**: Uses webhooks/API instead of discord.js
5. **Caching**: Uses Laravel's cache system instead of in-memory variables
6. **File Storage**: Uses Laravel's storage system

## Development

To extend this implementation:

1. **Add new endpoints**: Create controllers and add routes in `routes/api.php`
2. **Add services**: Create service classes in `app/Services/`
3. **Add events**: Create event classes in `app/Events/` for broadcasting
4. **Add commands**: Use `php artisan make:command` for CLI commands

## Production Deployment

1. Set `APP_ENV=production` in `.env`
2. Set `APP_DEBUG=false`
3. Run `php artisan config:cache`
4. Run `php artisan route:cache`
5. Set up proper web server (Apache/Nginx)
6. Configure process management (Supervisor) for queue workers

## Logging

Laravel provides comprehensive logging. Check `storage/logs/laravel.log` for application logs including Discord and ClickUp integration events.
