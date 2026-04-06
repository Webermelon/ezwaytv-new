<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (Schema::hasTable('users') && ! Schema::hasColumn('users', 'is_network_user')) {
            Schema::table('users', function (Blueprint $table) {
                $table->tinyInteger('is_network_user')->default(0)->after('status');
                $table->bigInteger('network_user_id')->nullable()->after('is_network_user');
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'is_network_user')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn(['is_network_user', 'network_user_id']);
            });
        }
    }
};
