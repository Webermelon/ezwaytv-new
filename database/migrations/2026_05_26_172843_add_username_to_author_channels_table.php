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
        Schema::table('author_channels', function (Blueprint $table) {
            $table->string('username')->nullable()->unique()->after('name');
        });

        // Back-fill existing rows with a slug derived from their name
        DB::table('author_channels')->whereNull('username')->orderBy('id')->each(function ($row) {
            $base = preg_replace('/[^a-z0-9]+/', '-', strtolower($row->name));
            $base = trim($base, '-') ?: 'channel';
            $slug = $base;
            $i = 2;
            while (DB::table('author_channels')->where('username', $slug)->where('id', '!=', $row->id)->exists()) {
                $slug = $base . '-' . $i++;
            }
            DB::table('author_channels')->where('id', $row->id)->update(['username' => $slug]);
        });

        // Now make it non-nullable
        Schema::table('author_channels', function (Blueprint $table) {
            $table->string('username')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('author_channels', function (Blueprint $table) {
            $table->dropColumn('username');
        });
    }
};
