<?php

use App\Models\AuthorChannel;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\LiveTV\Models\LiveTvChannel;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('live_tv_channel', function (Blueprint $table) {
            if (! Schema::hasColumn('live_tv_channel', 'vod_channel_id')) {
                $table->unsignedBigInteger('vod_channel_id')->nullable()->after('enable_live_chat')->index();
            }

            if (! Schema::hasColumn('live_tv_channel', 'vod_channel_button_name')) {
                $table->string('vod_channel_button_name')->nullable()->after('vod_channel_id');
            }
        });

        $authorsVodChannel = AuthorChannel::query()
            ->where(function ($query) {
                $query->whereIn('username', ['the-authors-channel', 'the-author-channel'])
                    ->orWhereRaw('LOWER(name) IN (?, ?)', ['the authors channel', 'the author channel']);
            })
            ->first();

        if (! $authorsVodChannel) {
            return;
        }

        LiveTvChannel::query()
            ->whereNull('vod_channel_id')
            ->where(function ($query) {
                $query->whereIn('slug', ['the-authors-channel', 'the-author-channel'])
                    ->orWhereRaw('LOWER(name) IN (?, ?)', ['the authors channel', 'the author channel']);
            })
            ->update([
                'vod_channel_id' => $authorsVodChannel->id,
                'vod_channel_button_name' => 'VOD Authors Channel',
                'updated_at' => DB::raw('updated_at'),
            ]);
    }

    public function down(): void
    {
        Schema::table('live_tv_channel', function (Blueprint $table) {
            if (Schema::hasColumn('live_tv_channel', 'vod_channel_button_name')) {
                $table->dropColumn('vod_channel_button_name');
            }

            if (Schema::hasColumn('live_tv_channel', 'vod_channel_id')) {
                $table->dropColumn('vod_channel_id');
            }
        });
    }
};
