<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('music_video_submissions', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->after('id');
            $table->string('channel_slug')->default('ezway-music')->after('user_id');
            $table->string('channel_name')->default('eZWay Music')->after('channel_slug');
            $table->string('purchase_reference')->nullable()->after('submitter_phone');
            $table->timestamp('purchase_confirmed_at')->nullable()->after('purchase_reference');
            $table->index(['channel_slug', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('music_video_submissions', function (Blueprint $table) {
            $table->dropIndex(['channel_slug', 'status']);
            $table->dropColumn([
                'user_id',
                'channel_slug',
                'channel_name',
                'purchase_reference',
                'purchase_confirmed_at',
            ]);
        });
    }
};
