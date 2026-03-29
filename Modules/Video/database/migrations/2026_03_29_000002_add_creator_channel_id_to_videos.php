<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->unsignedBigInteger('creator_channel_id')->nullable()->after('plan_id');
            $table->foreign('creator_channel_id')
                  ->references('id')
                  ->on('creator_channels')
                  ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->dropForeign(['creator_channel_id']);
            $table->dropColumn('creator_channel_id');
        });
    }
};
