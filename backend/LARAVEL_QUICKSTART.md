# Quick Start Guide - Laravel Discord to ClickUp Bot

🚀 **Getting Started**

1. **Navigate to the Laravel server directory:**
   ```bash
   cd backend/laravel-server
   ```

2. **Run the installation script:**
   ```bash
   ./install.sh
   ```

3. **Configure your environment:**
   Edit the `.env` file with your Discord and ClickUp credentials:
   ```bash
   nano .env
   ```

4. **Start the server:**
   ```bash
   php artisan serve --host=0.0.0.0 --port=8000
   ```

5. **Visit the API documentation:**
   Open http://localhost:8000 in your browser

## 🔧 **Configuration**

### Required Environment Variables:
- `API_SECRET` - Your API secret key
- `DISCORD_BOT_TOKEN` - Your Discord bot token
- `CLICKUP_CLIENT_ID` - Your ClickUp app client ID
- `CLICKUP_CLIENT_SECRET` - Your ClickUp app client secret
- `CLICKUP_WORKSPACE_ID` - Your ClickUp workspace ID
- `CLICKUP_CHANNEL_ID` - Your ClickUp channel ID
- `WATCHED_CHANNEL_IDS` - Discord channel IDs to monitor (comma-separated)

### Optional (for real-time updates):
- `PUSHER_APP_ID` - Pusher app ID
- `PUSHER_APP_KEY` - Pusher app key
- `PUSHER_APP_SECRET` - Pusher app secret
- `PUSHER_APP_CLUSTER` - Pusher cluster

## 📚 **Available Commands**

```bash
# Bot management
php artisan bot:start     # Start Discord bot
php artisan bot:stop      # Stop Discord bot
php artisan bot:status    # Check bot status

# Database
php artisan migrate       # Run database migrations
php artisan migrate:fresh # Reset database

# Cache management
php artisan config:cache  # Cache configuration
php artisan config:clear  # Clear configuration cache
php artisan route:cache   # Cache routes
php artisan route:clear   # Clear route cache
```

## 🔗 **API Endpoints**

All endpoints require API key authentication (except webhooks):
- Header: `X-API-Key: your_api_secret`
- Query: `?api_key=your_api_secret`
- Body: `api_key: your_api_secret`

Visit http://localhost:8000 for complete API documentation.

## 🐛 **Troubleshooting**

1. **Permission errors:**
   ```bash
   chmod -R 755 storage bootstrap/cache
   ```

2. **Database errors:**
   ```bash
   php artisan migrate:fresh
   ```

3. **Configuration issues:**
   ```bash
   php artisan config:clear
   php artisan cache:clear
   ```

4. **Check logs:**
   ```bash
   tail -f storage/logs/laravel.log
   ```

## 🔄 **Switching Between Servers**

- **Node.js server:** `npm start` (runs on port 3000)
- **Laravel server:** `php artisan serve --port=8000` (runs on port 8000)

Both servers provide similar functionality but use different technologies:
- Node.js: Real-time Discord bot with Socket.IO
- Laravel: HTTP API with webhook support and database persistence
