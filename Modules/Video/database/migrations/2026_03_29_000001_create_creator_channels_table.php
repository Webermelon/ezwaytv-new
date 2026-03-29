<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('creator_channels', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');          // owner (subscriber)
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->text('poster_url')->nullable();
            $table->text('banner_url')->nullable();
            $table->boolean('status')->default(0);
            $table->integer('created_by')->unsigned()->nullable();
            $table->integer('updated_by')->unsigned()->nullable();
            $table->integer('deleted_by')->unsigned()->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('creator_channels');
    }
};
