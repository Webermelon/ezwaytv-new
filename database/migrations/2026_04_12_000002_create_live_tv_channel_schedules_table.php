<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('live_tv_channel_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('live_tv_channel_id')->constrained('live_tv_channel')->onDelete('cascade');
            $table->string('title')->nullable();
            $table->timestamp('start_at');
            $table->timestamp('end_at')->nullable();
            $table->text('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('live_tv_channel_schedules');
    }
};
