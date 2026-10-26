<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('custom_ads_setting', function (Blueprint $table) {
            $table->string('redirect_target', 20)->default('new_tab')->after('redirect_url');
        });
    }

    public function down(): void
    {
        Schema::table('custom_ads_setting', function (Blueprint $table) {
            $table->dropColumn('redirect_target');
        });
    }
};
