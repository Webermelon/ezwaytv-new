<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('livetv_schedules', function (Blueprint $table) {
            if (!Schema::hasColumn('livetv_schedules', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        Schema::table('live_tv_channel_schedules', function (Blueprint $table) {
            if (!Schema::hasColumn('live_tv_channel_schedules', 'deleted_at')) {
                $table->softDeletes();
            }
        });
    }

    public function down()
    {
        Schema::table('livetv_schedules', function (Blueprint $table) {
            if (Schema::hasColumn('livetv_schedules', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });

        Schema::table('live_tv_channel_schedules', function (Blueprint $table) {
            if (Schema::hasColumn('live_tv_channel_schedules', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });
    }
};
