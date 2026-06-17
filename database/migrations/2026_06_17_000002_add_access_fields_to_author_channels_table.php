<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('author_channels', function (Blueprint $table) {
            $table->string('access')->default('free')->after('is_active');
            $table->unsignedBigInteger('plan_id')->nullable()->after('access')->index();
        });
    }

    public function down()
    {
        Schema::table('author_channels', function (Blueprint $table) {
            $table->dropIndex(['plan_id']);
            $table->dropColumn(['access', 'plan_id']);
        });
    }
};
