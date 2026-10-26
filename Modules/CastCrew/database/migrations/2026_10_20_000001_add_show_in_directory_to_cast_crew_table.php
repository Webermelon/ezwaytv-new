<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cast_crew', function (Blueprint $table) {
            $table->boolean('show_in_directory')->default(false)->index()->after('status');
        });

        // Existing public profiles have an uploaded image; credit-only names remain hidden.
        DB::table('cast_crew')
            ->whereNotNull('file_url')
            ->where('file_url', '!=', '')
            ->update(['show_in_directory' => true]);
    }

    public function down(): void
    {
        Schema::table('cast_crew', function (Blueprint $table) {
            $table->dropIndex(['show_in_directory']);
            $table->dropColumn('show_in_directory');
        });
    }
};
