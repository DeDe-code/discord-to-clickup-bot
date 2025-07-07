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
        Schema::table('discord_messages', function (Blueprint $table) {
            $table->boolean('clickup_sent')->default(false);
            $table->json('clickup_response')->nullable();
            $table->text('error_message')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('discord_messages', function (Blueprint $table) {
            $table->dropColumn(['clickup_sent', 'clickup_response', 'error_message']);
        });
    }
};
