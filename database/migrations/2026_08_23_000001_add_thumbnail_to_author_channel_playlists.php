<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('author_channel_playlists', 'thumbnail')) {
            Schema::table('author_channel_playlists', function (Blueprint $table) {
                $table->string('thumbnail', 500)->nullable()->after('description');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('author_channel_playlists', 'thumbnail')) {
            Schema::table('author_channel_playlists', function (Blueprint $table) {
                $table->dropColumn('thumbnail');
            });
        }
    }
};
