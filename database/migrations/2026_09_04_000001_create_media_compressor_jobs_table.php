<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_compressor_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('remote_job_id')->unique();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('owner_type')->index();
            $table->unsignedBigInteger('owner_id')->index();
            $table->string('media_type', 20)->index();
            $table->string('status', 40)->default('queued')->index();
            $table->text('primary_url')->nullable();
            $table->json('variants')->nullable();
            $table->json('payload')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['owner_type', 'owner_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_compressor_jobs');
    }
};
