<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('live_tv_channel', function (Blueprint $table) {
            $table->boolean('enable_live_chat')->default(false)->after('status');
        });

        Schema::create('live_tv_chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('live_tv_channel_id')->constrained('live_tv_channel')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('guest_name', 40);
            $table->text('message');
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index(['live_tv_channel_id', 'created_at'], 'livetv_chat_channel_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('live_tv_chat_messages');

        Schema::table('live_tv_channel', function (Blueprint $table) {
            $table->dropColumn('enable_live_chat');
        });
    }
};