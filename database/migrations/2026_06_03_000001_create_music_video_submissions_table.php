<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('music_video_submissions', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('artist_name')->nullable();
            $table->string('submitter_name')->nullable();
            $table->string('submitter_email')->nullable();
            $table->string('submitter_phone')->nullable();
            $table->text('notes')->nullable();
            $table->string('poster_disk')->default('public');
            $table->text('poster_path');
            $table->string('video_disk')->default('public');
            $table->text('video_path');
            $table->string('video_original_name')->nullable();
            $table->string('video_mime')->nullable();
            $table->unsignedBigInteger('video_size')->nullable();
            $table->string('status')->default('submitted')->index();
            $table->timestamp('scheduled_at')->nullable();
            $table->text('admin_notes')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('music_video_submissions');
    }
};
