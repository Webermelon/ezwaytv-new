<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Video\Models\Video;
use Throwable;

final class TvCreatorEvents
{
    public static function record(string $event, Request $request, User $creator, int $channelId, ?Video $video = null): void
    {
        TvLog::record('creator_events', $event, [
            'connect_user_id' => $creator->connect_user_id,
            'core_user_id' => $creator->network_user_id,
            'tv_user_id' => $creator->id,
            'publishing_channel_id' => $channelId,
            'video_id' => $video?->id,
            'release_date' => $video?->release_date?->toDateString(),
        ], $request, TvLog::legacyId($event, $video?->id ?? $channelId));
    }

    public static function isPublished(Video $video): bool
    {
        return (int) $video->status === 1 && filled($video->video_url_input);
    }

    public static function published(Request $request, Video $video): void
    {
        if (!config('tv-logs.enabled')) {
            return;
        }

        try {
            $channelId = self::publishingChannel($video);
            $creatorId = $channelId ? DB::table('author_channels')->where('id', $channelId)->value('user_id') : null;
            $creator = $creatorId ? User::find($creatorId) : null;
            if ($channelId && $creator) {
                self::record('creator.video_published', $request, $creator, $channelId, $video);
            }
        } catch (Throwable) {
            error_log('TV creator publication could not be logged.');
        }
    }

    public static function viewingContext(?string $contentType, array $data): array
    {
        if (!config('tv-logs.enabled') || !in_array($contentType, ['video', 'ondemand_video'], true)) {
            return [];
        }

        $context = ['player_surface' => $data['player_surface'] ?? null];
        try {
            $video = Video::query()->select(['id', 'created_by', 'publishing_channel_id'])
                ->find($data['content_id'] ?? 0);
            if (!$video) {
                return $context;
            }

            $context['publishing_channel_id'] = self::publishingChannel($video);
            $watched = $data['watched_channel_id'] ?? null;
            if ($watched && $video->authorChannels()
                ->where('author_channels.id', $watched)->where('author_channels.is_active', 1)->exists()) {
                $context['watched_channel_id'] = $watched;
            }
        } catch (Throwable) {
            error_log('TV viewing attribution could not be resolved.');
        }

        return $context;
    }

    private static function publishingChannel(Video $video): ?int
    {
        if ($video->publishing_channel_id) {
            return (int) $video->publishing_channel_id;
        }

        if (!$video->created_by) {
            return null;
        }

        // Older videos can be assigned to multiple channels. Only use an unambiguous owner match.
        $channels = DB::table('author_channel_video AS placement')
            ->join('author_channels AS channel', 'channel.id', '=', 'placement.author_channel_id')
            ->where('placement.video_id', $video->id)
            ->where('channel.user_id', $video->created_by)
            ->limit(2)->pluck('channel.id');

        return $channels->count() === 1 ? (int) $channels->first() : null;
    }
}
