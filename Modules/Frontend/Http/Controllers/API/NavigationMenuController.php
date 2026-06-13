<?php

namespace Modules\Frontend\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\AuthorChannel;
use Illuminate\Support\Facades\Cache;
use Modules\Entertainment\Models\Entertainment;
use Modules\LiveTV\Models\LiveTvChannel;
use Modules\Video\Models\Video;

class NavigationMenuController extends Controller
{
    public function show()
    {
        $data = Cache::remember('api:v3:navigation-menu', 300, function () {
            $videos = Video::query()
                ->where('status', 1)
                ->orderByDesc('updated_at')
                ->limit(14)
                ->get()
                ->map(fn (Video $video) => $this->videoItem($video))
                ->values();

            $liveTv = LiveTvChannel::query()
                ->with('TvCategory')
                ->where('status', 1)
                ->orderByDesc('updated_at')
                ->limit(14)
                ->get()
                ->map(fn (LiveTvChannel $channel) => $this->liveTvItem($channel))
                ->values();

            $onDemand = AuthorChannel::query()
                ->withCount('videos')
                ->where('is_active', 1)
                ->orderByDesc('updated_at')
                ->limit(14)
                ->get()
                ->map(fn (AuthorChannel $channel) => $this->onDemandItem($channel))
                ->values();

            $hasMovies = Entertainment::query()
                ->where('status', 1)
                ->where('type', 'movie')
                ->exists();

            $hasTvShows = Entertainment::query()
                ->where('status', 1)
                ->whereIn('type', ['tv_show', 'tvshow'])
                ->exists();

            return [
                'bottom_nav' => [
                    ['key' => 'home', 'label' => 'Home', 'href' => '/', 'icon' => 'home'],
                    ['key' => 'search', 'label' => 'Search', 'href' => '/search', 'icon' => 'search'],
                    ['key' => 'on-demand', 'label' => 'On Demand', 'href' => '/on-demand', 'icon' => 'film'],
                    ['key' => 'livetv', 'label' => 'Live TV', 'href' => '/livetv', 'icon' => 'tv'],
                    ['key' => 'distribution', 'label' => 'Distribution', 'href' => '/distribution', 'icon' => 'globe'],
                ],
                'burger_menu' => [
                    ['key' => 'home', 'label' => 'Home', 'href' => '/', 'icon' => 'home'],
                    ['key' => 'movies', 'label' => 'Movies', 'href' => '/movies', 'icon' => 'film', 'visible' => $hasMovies],
                    ['key' => 'tvshows', 'label' => 'TV Shows', 'href' => '/tv-shows', 'icon' => 'tv', 'visible' => $hasTvShows],
                    ['key' => 'videos', 'label' => 'Videos', 'href' => '/videos', 'icon' => 'video', 'dropdown' => 'videos', 'visible' => true],
                    ['key' => 'on-demand', 'label' => 'On Demand', 'href' => '/on-demand', 'icon' => 'film', 'dropdown' => 'ondemand', 'visible' => true],
                    ['key' => 'livetv', 'label' => 'Live TV', 'href' => '/livetv', 'icon' => 'radio', 'dropdown' => 'livetv', 'visible' => true],
                    ['key' => 'distribution', 'label' => 'Distribution', 'href' => '/distribution', 'icon' => 'share', 'visible' => true],
                    ['key' => 'stream-music', 'label' => 'Stream Your Music', 'href' => '/music', 'icon' => 'music', 'visible' => true],
                ],
                'burger_toggle' => [
                    'default_open' => false,
                    'open_icon' => 'menu',
                    'close_icon' => 'x',
                    'open_label' => 'Open menu',
                    'close_label' => 'Close menu',
                ],
                'dropdowns' => [
                    'videos' => $videos,
                    'livetv' => $liveTv,
                    'ondemand' => $onDemand,
                ],
            ];
        });

        return ApiResponse::success($data, 'Navigation menu data');
    }

    private function videoItem(Video $video): array
    {
        return [
            'id' => $video->id,
            'name' => $video->name,
            'slug' => $video->slug,
            'href' => $video->slug ? url('/video-details/' . $video->slug . '?autoplay=1') : url('/videos'),
            'poster_image' => $video->poster_url ? setBaseUrlWithFileName($video->poster_url, 'image', 'video') : null,
            'poster_tv_image' => $video->poster_tv_url ? setBaseUrlWithFileName($video->poster_tv_url, 'image', 'video') : null,
            'duration' => $video->duration,
            'access' => $video->access,
            'meta' => trim(collect([$video->duration, $video->access])->filter()->implode(' - ')) ?: 'Video',
        ];
    }

    private function liveTvItem(LiveTvChannel $channel): array
    {
        return [
            'id' => $channel->id,
            'name' => $channel->name,
            'slug' => $channel->slug,
            'href' => url('/livetv/' . ($channel->slug ?: $channel->id)),
            'poster_image' => $channel->poster_url ? setBaseUrlWithFileName($channel->poster_url, 'image', 'livetv') : null,
            'poster_tv_image' => $channel->poster_tv_url ? setBaseUrlWithFileName($channel->poster_tv_url, 'image', 'livetv') : null,
            'category' => $channel->TvCategory->name ?? null,
            'meta' => $channel->TvCategory->name ?? 'Live channel',
        ];
    }

    private function onDemandItem(AuthorChannel $channel): array
    {
        return [
            'id' => $channel->id,
            'name' => $channel->name,
            'username' => $channel->username,
            'href' => url('/on-demand/' . $channel->username),
            'cover_image_url' => $channel->banner ? setBaseUrlWithFileNameV2($channel->banner) : null,
            'avatar_image_url' => $channel->avatar ? setBaseUrlWithFileNameV2($channel->avatar) : null,
            'videos_count' => $channel->videos_count ?? 0,
            'meta' => ($channel->videos_count ?? 0) . ' videos',
        ];
    }
}
