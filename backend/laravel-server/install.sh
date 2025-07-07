#!/bin/bash

echo "🚀 Installing Discord to ClickUp Bot - Laravel Server"
echo "=================================================="

cd "$(dirname "$0")"

# Check if Composer is installed
if ! command -v composer &> /dev/null; then
    echo "❌ Composer is not installed. Please install Composer first."
    echo "Visit: https://getcomposer.org/download/"
    exit 1
fi

# Check if PHP is installed and version
if ! command -v php &> /dev/null; then
    echo "❌ PHP is not installed. Please install PHP 8.1 or higher."
    exit 1
fi

PHP_VERSION=$(php -r "echo PHP_VERSION;" 2>/dev/null)
echo "✅ PHP Version: $PHP_VERSION"

# Install Composer dependencies
echo "📦 Installing Composer dependencies..."
composer install --no-dev --optimize-autoloader

if [ $? -ne 0 ]; then
    echo "❌ Failed to install Composer dependencies."
    exit 1
fi

# Copy environment file if it doesn't exist
if [ ! -f .env ]; then
    echo "📝 Creating .env file..."
    cp .env.example .env
    echo "⚠️  Please update the .env file with your Discord and ClickUp credentials."
else
    echo "✅ .env file already exists."
fi

# Generate application key
echo "🔑 Generating application key..."
php artisan key:generate --force

# Create storage directories
echo "📁 Creating storage directories..."
mkdir -p storage/logs
mkdir -p storage/framework/cache
mkdir -p storage/framework/sessions
mkdir -p storage/framework/views
mkdir -p storage/app/public
mkdir -p bootstrap/cache

# Set permissions
echo "🔐 Setting permissions..."
chmod -R 755 storage
chmod -R 755 bootstrap/cache

# Create SQLite database file
echo "🗄️  Creating SQLite database..."
touch database/database.sqlite

# Run migrations
echo "🏗️  Running database migrations..."
php artisan migrate --force

# Clear and cache config
echo "⚡ Optimizing application..."
php artisan config:cache
php artisan route:cache

echo ""
echo "✅ Installation completed successfully!"
echo ""
echo "🔧 Next steps:"
echo "1. Update your .env file with Discord and ClickUp credentials"
echo "2. Start the server with: php artisan serve --host=0.0.0.0 --port=8000"
echo "3. Visit http://localhost:8000 to see the API documentation"
echo ""
echo "📚 Available commands:"
echo "  php artisan bot:start    - Start the Discord bot"
echo "  php artisan bot:stop     - Stop the Discord bot"
echo "  php artisan bot:status   - Check bot status"
echo ""
