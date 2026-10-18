<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('live_tv_chat_messages', function (Blueprint $table) {
            $table->string('avatar_url', 2048)->nullable()->after('guest_name');
        });
    }

    public function down(): void
    {
        Schema::table('live_tv_chat_messages', function (Blueprint $table) {
            $table->dropColumn('avatar_url');
        });
    }
};
