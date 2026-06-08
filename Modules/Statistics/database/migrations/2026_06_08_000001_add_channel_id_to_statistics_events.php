<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stat_page_views', function (Blueprint $table) {
            if (!Schema::hasColumn('stat_page_views', 'channel_id')) {
                $table->unsignedBigInteger('channel_id')->nullable()->after('content_id')->index();
                $table->index(['content_type', 'channel_id']);
            }
        });

        Schema::table('stat_play_events', function (Blueprint $table) {
            if (!Schema::hasColumn('stat_play_events', 'channel_id')) {
                $table->unsignedBigInteger('channel_id')->nullable()->after('content_id')->index();
                $table->index(['content_type', 'channel_id']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('stat_page_views', function (Blueprint $table) {
            if (Schema::hasColumn('stat_page_views', 'channel_id')) {
                $table->dropIndex(['channel_id']);
                $table->dropIndex(['content_type', 'channel_id']);
                $table->dropColumn('channel_id');
            }
        });

        Schema::table('stat_play_events', function (Blueprint $table) {
            if (Schema::hasColumn('stat_play_events', 'channel_id')) {
                $table->dropIndex(['channel_id']);
                $table->dropIndex(['content_type', 'channel_id']);
                $table->dropColumn('channel_id');
            }
        });
    }
};
