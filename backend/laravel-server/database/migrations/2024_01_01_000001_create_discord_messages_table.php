<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('discord_messages', function (Blueprint $table) {
            $table->id();
            $table->string('discord_message_id')->unique();
            $table->string('channel_id');
            $table->string('guild_id');
            $table->string('username');
            $table->text('content');
            $table->json('attachments')->nullable();
            $table->timestamp('discord_timestamp');
            $table->boolean('sent_to_clickup')->default(false);
            $table->string('clickup_message_id')->nullable();
            $table->timestamps();
            
            $table->index(['channel_id', 'discord_timestamp']);
            $table->index('sent_to_clickup');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('discord_messages');
    }
};
