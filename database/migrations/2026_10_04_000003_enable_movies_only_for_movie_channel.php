<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('author_channels', function (Blueprint $table) {
            $table->boolean('movies_enabled')->default(false)->change();
        });

        DB::table('author_channels')->update(['movies_enabled' => false]);
        DB::table('author_channels')
            ->where('username', 'ezway-movie-channel')
            ->update(['movies_enabled' => true]);
    }

    public function down(): void
    {
        Schema::table('author_channels', function (Blueprint $table) {
            $table->boolean('movies_enabled')->default(true)->change();
        });

        DB::table('author_channels')->update(['movies_enabled' => true]);
    }
};
