<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('author_channels', function (Blueprint $table) {
            $table->boolean('movies_enabled')->default(true)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('author_channels', function (Blueprint $table) {
            $table->dropColumn('movies_enabled');
        });
    }
};
