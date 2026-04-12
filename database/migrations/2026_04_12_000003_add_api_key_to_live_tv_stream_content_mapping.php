<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('live_tv_stream_content_mapping', function (Blueprint $table) {
            if (!Schema::hasColumn('live_tv_stream_content_mapping', 'api_key')) {
                $table->string('api_key')->nullable()->after('server_url1');
            }
        });
    }

    public function down()
    {
        Schema::table('live_tv_stream_content_mapping', function (Blueprint $table) {
            if (Schema::hasColumn('live_tv_stream_content_mapping', 'api_key')) {
                $table->dropColumn('api_key');
            }
        });
    }
};
