<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('live_tv_channel', function (Blueprint $table) {
            $table->string('chat_title', 80)->default('Live Chat')->after('enable_live_chat');
            $table->boolean('chat_ads_enabled')->default(false)->after('chat_title');
            $table->unsignedSmallInteger('chat_ad_rotation_seconds')->default(5)->after('chat_ads_enabled');
            $table->json('chat_ad_banners')->nullable()->after('chat_ad_rotation_seconds');
        });
    }

    public function down(): void
    {
        Schema::table('live_tv_channel', function (Blueprint $table) {
            $table->dropColumn([
                'chat_title',
                'chat_ads_enabled',
                'chat_ad_rotation_seconds',
                'chat_ad_banners',
            ]);
        });
    }
};
