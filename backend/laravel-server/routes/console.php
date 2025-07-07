<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
|
| This file is where you may define all of your Closure based console
| commands. Each Closure is bound to a command instance allowing a
| simple approach to interacting with each command's IO methods.
|
*/

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('bot:start', function () {
    $this->info('Starting Discord bot...');
    // In a real implementation, this would start the Discord bot process
    // For now, we'll just update the cache to indicate the bot is running
    cache(['discord_bot_status' => true], now()->addHours(24));
    $this->info('✅ Discord bot started (simulated)');
})->purpose('Start the Discord bot');

Artisan::command('bot:stop', function () {
    $this->info('Stopping Discord bot...');
    cache()->forget('discord_bot_status');
    $this->info('❌ Discord bot stopped');
})->purpose('Stop the Discord bot');

Artisan::command('bot:status', function () {
    $isOnline = cache('discord_bot_status', false);
    $status = $isOnline ? '🟢 Bot is online' : '🔴 Bot is offline';
    $this->info("Bot Status: {$status}");
})->purpose('Check Discord bot status');
