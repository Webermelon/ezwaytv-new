<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('author_channel_entertainment', function (Blueprint $table) {
            $table->unsignedBigInteger('author_channel_id');
            $table->unsignedBigInteger('entertainment_id');
            $table->primary(['author_channel_id', 'entertainment_id'], 'author_channel_entertainment_primary');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('author_channel_entertainment');
    }
};
