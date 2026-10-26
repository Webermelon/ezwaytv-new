<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tribeca_one_distribution_submissions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('status')->default('submitted')->index();
            $table->string('title_name')->nullable()->index();
            $table->string('client_company_name')->nullable()->index();
            $table->string('main_contact_person')->nullable();
            $table->string('contact_email')->nullable()->index();
            $table->string('submitted_by')->nullable();
            $table->string('submitter_role')->nullable();
            $table->timestamp('submitted_at')->nullable()->index();
            $table->json('licensor_data')->nullable();
            $table->longText('banking_data')->nullable();
            $table->json('title_data')->nullable();
            $table->json('credits_data')->nullable();
            $table->json('links_data')->nullable();
            $table->json('delivery_data')->nullable();
            $table->json('master_data')->nullable();
            $table->json('audio_data')->nullable();
            $table->json('captions_data')->nullable();
            $table->json('trailer_data')->nullable();
            $table->json('artwork_data')->nullable();
            $table->json('final_review_data')->nullable();
            $table->text('additional_notes')->nullable();
            $table->longText('email_html')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tribeca_one_distribution_submissions');
    }
};
