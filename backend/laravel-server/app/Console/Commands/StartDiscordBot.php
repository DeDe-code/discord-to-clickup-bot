<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\DiscordBotService;

class StartDiscordBot extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'discord:start';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Start the Discord bot service';

    /**
     * Create a new command instance.
     */
    public function __construct(
        private DiscordBotService $discordService
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🤖 Starting Discord bot service...');
        
        try {
            $this->discordService->start();
            $this->info('✅ Discord bot started successfully!');
            return 0;
        } catch (\Exception $e) {
            $this->error('❌ Failed to start Discord bot: ' . $e->getMessage());
            return 1;
        }
    }
}
