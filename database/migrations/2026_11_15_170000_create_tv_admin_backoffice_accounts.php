<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tv_admin_backoffice_accounts', function (Blueprint $table) {
            $table->unsignedBigInteger('tv_user_id')->primary();
            $table->unsignedBigInteger('backoffice_user_id')->unique();
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tv_admin_backoffice_accounts');
    }
};
