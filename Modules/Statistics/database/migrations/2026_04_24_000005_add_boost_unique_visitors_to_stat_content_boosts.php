<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('stat_content_boosts', 'boost_unique_visitors')) {
            Schema::table('stat_content_boosts', function (Blueprint $table) {
                $table->unsignedBigInteger('boost_unique_visitors')->default(0)->after('boost_watch_seconds');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('stat_content_boosts', 'boost_unique_visitors')) {
            Schema::table('stat_content_boosts', function (Blueprint $table) {
                $table->dropColumn('boost_unique_visitors');
            });
        }
    }
};
