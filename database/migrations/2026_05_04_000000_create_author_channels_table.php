<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('author_channels', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('avatar')->nullable();
            $table->string('banner')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('author_channel_video', function (Blueprint $table) {
            $table->unsignedBigInteger('author_channel_id');
            $table->unsignedBigInteger('video_id');
            $table->primary(['author_channel_id', 'video_id']);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('author_channel_video');
        Schema::dropIfExists('author_channels');
    }
};
