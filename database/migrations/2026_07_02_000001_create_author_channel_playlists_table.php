<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('author_channel_playlists', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('author_channel_id')->index();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('author_channel_playlist_video', function (Blueprint $table) {
            $table->unsignedBigInteger('author_channel_playlist_id');
            $table->unsignedBigInteger('video_id');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->primary(['author_channel_playlist_id', 'video_id'], 'ac_playlist_video_primary');
        });
    }

    public function down()
    {
        Schema::dropIfExists('author_channel_playlist_video');
        Schema::dropIfExists('author_channel_playlists');
    }
};
