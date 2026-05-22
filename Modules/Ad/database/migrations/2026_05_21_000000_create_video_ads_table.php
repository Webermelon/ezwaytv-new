<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('video_ads', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('title')->nullable(); // Ad title shown in player
            $table->text('description')->nullable(); // Ad description
            $table->string('video_file'); // Path to uploaded video ad
            $table->string('video_url')->nullable(); // Full URL to video
            $table->integer('duration')->nullable(); // Duration in seconds
            $table->string('click_through_url')->nullable(); // Where user goes when clicking ad
            $table->string('skip_offset')->nullable(); // e.g., "00:00:05" or "5"
            $table->boolean('is_skippable')->default(0);
            $table->string('advertiser')->nullable(); // Advertiser name
            $table->string('impression_url')->nullable(); // Tracking pixel URL
            $table->string('click_tracking_url')->nullable(); // Click tracking URL
            $table->integer('width')->default(1920); // Video width
            $table->integer('height')->default(1080); // Video height
            $table->string('mime_type')->default('video/mp4'); // MIME type
            $table->boolean('status')->default(1);
            $table->timestamps();
            $table->softDeletes();

            // Add indexes for better performance
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('video_ads');
    }
};
