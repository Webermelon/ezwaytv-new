<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('stat_page_views', function (Blueprint $table) {
            $table->string('page_name', 255)->nullable()->after('page_url');
            $table->string('route_name', 100)->nullable()->after('page_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stat_page_views', function (Blueprint $table) {
            $table->dropColumn(['page_name', 'route_name']);
        });
    }
};
