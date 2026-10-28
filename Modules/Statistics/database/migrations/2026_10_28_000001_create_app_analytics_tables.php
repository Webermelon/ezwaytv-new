<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_analytics_installations', function (Blueprint $table) {
            $table->id();
            $table->uuid('installation_id')->unique();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('platform', 16)->index();
            $table->timestamp('installed_at')->nullable()->index();
            $table->timestamp('first_opened_at')->nullable()->index();
            $table->timestamp('last_opened_at')->nullable();
            $table->timestamps();
        });

        Schema::create('app_analytics_activities', function (Blueprint $table) {
            $table->id();
            $table->uuid('installation_id');
            $table->unsignedBigInteger('user_id')->index();
            $table->date('activity_date')->index();
            $table->unsignedInteger('opens')->default(0);
            $table->unsignedInteger('engaged_seconds')->default(0);
            $table->timestamp('first_opened_at')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamps();

            $table->unique(['installation_id', 'activity_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_analytics_activities');
        Schema::dropIfExists('app_analytics_installations');
    }
};
