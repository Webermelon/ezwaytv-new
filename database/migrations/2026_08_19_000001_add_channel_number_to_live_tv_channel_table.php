<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('live_tv_channel', function (Blueprint $table) {
            if (!Schema::hasColumn('live_tv_channel', 'channel_number')) {
                $table->unsignedInteger('channel_number')->nullable()->after('slug');
            }
        });

        $ezway = DB::table('live_tv_channel')
            ->whereNull('deleted_at')
            ->where(function ($query) {
                $query->where('slug', 'ezway-tv')
                    ->orWhere('name', 'eZWay TV');
            })
            ->orderByRaw("CASE WHEN slug = 'ezway-tv' THEN 0 ELSE 1 END")
            ->orderBy('created_at')
            ->orderBy('id')
            ->first();

        if ($ezway) {
            DB::table('live_tv_channel')
                ->where('id', $ezway->id)
                ->update(['channel_number' => 1]);
        }

        $nextNumber = 2;

        DB::table('live_tv_channel')
            ->whereNull('deleted_at')
            ->when($ezway, fn ($query) => $query->where('id', '!=', $ezway->id))
            ->orderBy('created_at')
            ->orderBy('id')
            ->select('id')
            ->chunk(100, function ($channels) use (&$nextNumber) {
                foreach ($channels as $channel) {
                    DB::table('live_tv_channel')
                        ->where('id', $channel->id)
                        ->update(['channel_number' => $nextNumber]);

                    $nextNumber++;
                }
            });

        Schema::table('live_tv_channel', function (Blueprint $table) {
            $table->unique('channel_number', 'live_tv_channel_channel_number_unique');
        });
    }

    public function down(): void
    {
        Schema::table('live_tv_channel', function (Blueprint $table) {
            if (Schema::hasColumn('live_tv_channel', 'channel_number')) {
                $table->dropUnique('live_tv_channel_channel_number_unique');
                $table->dropColumn('channel_number');
            }
        });
    }
};
