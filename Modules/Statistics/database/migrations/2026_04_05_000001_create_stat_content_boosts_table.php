<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stat_content_boosts', function (Blueprint $table) {
            $table->id();
            $table->string('content_type', 50);           // entertainment, video, episode, livetv
            $table->unsignedBigInteger('content_id');
            $table->string('content_name')->nullable();   // cached for display
            $table->unsignedBigInteger('boost_plays')->default(0);
            $table->unsignedBigInteger('boost_views')->default(0);
            $table->string('note')->nullable();
            $table->timestamps();

            $table->unique(['content_type', 'content_id']);
            $table->index(['content_type', 'content_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stat_content_boosts');
    }
};
