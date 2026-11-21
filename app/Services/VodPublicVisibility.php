<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Public visibility is derived, so billing never destroys manual publication state. */
class VodPublicVisibility
{
    public static function enabled(): bool
    {
        return config('vod.enabled') || Schema::hasTable('vod_channel_entitlements');
    }

    public static function publicRequest(): bool
    {
        $request = request();
        if (! $request->route() || ! in_array($request->method(), ['GET', 'HEAD'], true)) return false;
        $controller = (string) $request->route()->getAction('controller');
        return ! $request->is('api/private/*', 'app', 'app/*', 'admin', 'admin/*', 'api/v3/ondemand/my')
            && ! str_starts_with((string) $request->route()->getName(), 'backend.')
            && ! preg_match('/\\\\(Backend|Admin|Private)\\\\/', $controller)
            && ! str_ends_with($controller, '@myChannels');
    }

    /** Correlated NOT EXISTS keeps unlinked legacy channels unchanged. */
    public static function channels($query, string $column = 'author_channels.id'): void
    {
        if (! self::enabled()) return;
        $query->whereNotExists(function ($sub) use ($column) {
            $sub->selectRaw('1')->from('vod_channel_entitlements as vod_visibility')
                ->whereColumn('vod_visibility.channel_id', $column)
                ->where(function ($blocked) {
                    $blocked->whereNotIn('vod_visibility.status', ['active', 'past_due'])
                        ->orWhereNull('vod_visibility.paid_until')
                        ->orWhere('vod_visibility.paid_until', '<=', now())
                        ->orWhere('vod_visibility.starts_at', '>', now());
                });
        });
    }

    public static function videos($query): void
    {
        if (! self::enabled()) return;
        // Publishing ownership wins over distribution into other channels.
        self::channels($query, 'videos.publishing_channel_id');
        $query->where(function ($q) {
            $q->whereNotNull('videos.publishing_channel_id')->orWhere(function ($legacy) {
                self::channels($legacy, 'videos.creator_channel_id');
                // Older uploads only have pivot ownership; preserve videos also carried by a visible channel.
                $legacy->where(function ($unowned) {
                    $unowned->whereNotNull('videos.creator_channel_id')
                        ->orWhereNotExists(function ($p) {
                            $p->selectRaw('1')->from('author_channel_video as vod_pivot')
                                ->whereColumn('vod_pivot.video_id', 'videos.id');
                        })->orWhereExists(function ($p) {
                            $p->selectRaw('1')->from('author_channel_video as vod_pivot')
                                ->join('author_channels as vod_channel', 'vod_channel.id', '=', 'vod_pivot.author_channel_id')
                                ->whereColumn('vod_pivot.video_id', 'videos.id')->where('vod_channel.is_active', true)
                                ->whereNull('vod_channel.deleted_at');
                            self::channels($p, 'vod_channel.id');
                        });
                });
            });
        });
    }

    /** Changes immediately on cancellation, renewal, start and expiry, including warm caches. */
    public static function cacheKey(string $key): string
    {
        if (! self::enabled()) return $key;
        $state = DB::table('vod_channel_entitlements')->orderBy('channel_id')
            ->get(['channel_id', 'status', 'starts_at', 'paid_until', 'version'])
            ->map(fn ($row) => [$row->channel_id, $row->version, app(VodChannelEntitlements::class)->active($row)])
            ->all();
        return 'vod-visibility:v1:'.hash('sha256', json_encode($state)).':'.$key;
    }
}
