<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $enabled = filter_var(env('MEDIA_COMPRESSOR_API_ENABLED', false), FILTER_VALIDATE_BOOLEAN) ? '1' : '0';
        $now = now();

        DB::table('settings')->updateOrInsert(
            ['name' => 'media_compressor_api_enabled'],
            [
                'val' => $enabled,
                'type' => 'storage_settings',
                'datatype' => 'storageconfig',
                'updated_at' => $now,
                'created_at' => $now,
            ]
        );
    }

    public function down(): void
    {
        DB::table('settings')->where('name', 'media_compressor_api_enabled')->delete();
    }
};
