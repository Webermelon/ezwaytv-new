<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('filemanagers', function (Blueprint $table) {
            if (! Schema::hasColumn('filemanagers', 'status')) {
                $table->string('status', 40)->default('ready')->after('file_name')->index();
            }

            if (! Schema::hasColumn('filemanagers', 'remote_job_id')) {
                $table->string('remote_job_id')->nullable()->after('status')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('filemanagers', function (Blueprint $table) {
            if (Schema::hasColumn('filemanagers', 'remote_job_id')) {
                $table->dropColumn('remote_job_id');
            }

            if (Schema::hasColumn('filemanagers', 'status')) {
                $table->dropColumn('status');
            }
        });
    }
};
