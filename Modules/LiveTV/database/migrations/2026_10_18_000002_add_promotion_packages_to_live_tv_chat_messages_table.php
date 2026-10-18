<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('live_tv_chat_messages', function (Blueprint $table) {
            $table->json('promotion_packages')->nullable()->after('message');
        });
    }

    public function down(): void
    {
        Schema::table('live_tv_chat_messages', function (Blueprint $table) {
            $table->dropColumn('promotion_packages');
        });
    }
};
