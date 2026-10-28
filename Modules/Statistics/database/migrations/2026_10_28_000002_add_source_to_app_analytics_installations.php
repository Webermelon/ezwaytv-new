<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_analytics_installations', function (Blueprint $table) {
            $table->string('source', 32)->default('telemetry')->index()->after('platform');
        });
    }

    public function down(): void
    {
        Schema::table('app_analytics_installations', function (Blueprint $table) {
            $table->dropIndex(['source']);
            $table->dropColumn('source');
        });
    }
};
