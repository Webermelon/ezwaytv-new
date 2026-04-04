<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stat_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        // Seed default settings
        DB::table('stat_settings')->insert([
            ['key' => 'track_page_views',    'value' => '1', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'track_play_events',   'value' => '1', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'track_watch_time',    'value' => '1', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'track_guests',        'value' => '1', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'exclude_ips',         'value' => '',  'created_at' => now(), 'updated_at' => now()],
            ['key' => 'exclude_bots',        'value' => '1', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'retention_days',      'value' => '365','created_at' => now(), 'updated_at' => now()],
            ['key' => 'heartbeat_interval',  'value' => '30', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('stat_settings');
    }
};
