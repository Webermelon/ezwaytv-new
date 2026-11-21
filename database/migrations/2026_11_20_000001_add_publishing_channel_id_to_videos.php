<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('videos', 'publishing_channel_id')) {
            Schema::table('videos', function (Blueprint $table): void {
                $table->unsignedBigInteger('publishing_channel_id')->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        // Preserve recorded publishing provenance when application code is rolled back.
    }
};
