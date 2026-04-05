<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stat_content_boosts', function (Blueprint $table) {
            $table->dropUnique(['content_type', 'content_id']);
        });
    }

    public function down(): void
    {
        Schema::table('stat_content_boosts', function (Blueprint $table) {
            $table->unique(['content_type', 'content_id']);
        });
    }
};
