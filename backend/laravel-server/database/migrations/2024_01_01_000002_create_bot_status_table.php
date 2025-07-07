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
        Schema::create('bot_status', function (Blueprint $table) {
            $table->id();
            $table->string('service_name');
            $table->boolean('is_online')->default(false);
            $table->timestamp('last_ping')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            
            $table->unique('service_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bot_status');
    }
};
