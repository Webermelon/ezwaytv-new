<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('stat_content_boosts', 'boost_watch_seconds')) {
            Schema::table('stat_content_boosts', function (Blueprint $table) {
                $table->unsignedBigInteger('boost_watch_seconds')->default(0)->after('boost_views');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('stat_content_boosts', 'boost_watch_seconds')) {
            Schema::table('stat_content_boosts', function (Blueprint $table) {
                $table->dropColumn('boost_watch_seconds');
            });
        }
    }
};
