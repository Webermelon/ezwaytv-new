<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ad_banner_slides', function (Blueprint $table) {
            $table->id();
            $table->string('title')->nullable();
            $table->text('image')->nullable();
            $table->string('link_url')->nullable();
            $table->text('placements')->nullable(); // JSON: ["home","tvshow","video","livetv"]
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->boolean('status')->default(1);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_banner_slides');
    }
};
