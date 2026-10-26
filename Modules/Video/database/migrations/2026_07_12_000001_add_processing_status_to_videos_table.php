<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            if (! Schema::hasColumn('videos', 'processing_status')) {
                $table->string('processing_status', 40)->default('draft')->after('status')->index();
            }
            if (! Schema::hasColumn('videos', 'processing_message')) {
                $table->text('processing_message')->nullable()->after('processing_status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            if (Schema::hasColumn('videos', 'processing_message')) {
                $table->dropColumn('processing_message');
            }
            if (Schema::hasColumn('videos', 'processing_status')) {
                $table->dropColumn('processing_status');
            }
        });
    }
};
