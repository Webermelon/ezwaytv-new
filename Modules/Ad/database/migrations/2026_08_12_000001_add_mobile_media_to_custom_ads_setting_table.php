<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('custom_ads_setting', function (Blueprint $table) {
            $table->string('mobile_media')->nullable()->after('media');
        });
    }

    public function down(): void
    {
        Schema::table('custom_ads_setting', function (Blueprint $table) {
            $table->dropColumn('mobile_media');
        });
    }
};
