<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stat_page_views', function (Blueprint $table) {
            $table->id();
            $table->string('content_type')->nullable()->index(); // video, entertainment, livetv, episode, page
            $table->unsignedBigInteger('content_id')->nullable()->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable()->index();
            $table->string('country_code', 5)->nullable()->index();
            $table->string('device_type', 20)->nullable()->index(); // mobile, tablet, desktop, tv
            $table->string('browser', 100)->nullable();
            $table->string('os', 100)->nullable();
            $table->string('platform', 50)->nullable()->index(); // web, android, ios, tv
            $table->text('referrer')->nullable();
            $table->string('page_url', 500)->nullable();
            $table->string('session_id', 100)->nullable()->index();
            $table->date('view_date')->nullable()->index();
            $table->timestamps();

            $table->index(['content_type', 'content_id']);
            $table->index(['view_date', 'content_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stat_page_views');
    }
};
