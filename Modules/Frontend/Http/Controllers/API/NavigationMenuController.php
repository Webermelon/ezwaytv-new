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
        $data = Cache::remember('api:v3:navigation-menu:v4', 300, function () {
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
                    $this->menuItem('home', 'Home', '/', 'home'),
                    $this->menuItem('search', 'Search', '/search', 'search'),
                    $this->menuItem('on-demand', 'On Demand', '/on-demand', 'film'),
                    $this->menuItem('livetv', 'Live TV', '/livetv', 'tv'),
                    $this->menuItem('distribution', 'Distribution', '/distribution', 'globe'),
                ],
                'burger_menu' => [
                    $this->menuItem('home', 'Home', '/', 'home'),
                    $this->menuItem('movies', 'Movies', '/movies', 'film', ['visible' => $hasMovies]),
                    $this->menuItem('tvshows', 'TV Shows', '/tv-shows', 'tv', ['visible' => $hasTvShows]),
                    $this->menuItem('videos', 'Videos', '/videos', 'video', ['dropdown' => 'videos', 'visible' => true]),
                    $this->menuItem('on-demand', 'On Demand', '/on-demand', 'film', ['dropdown' => 'ondemand', 'visible' => true]),
                    $this->menuItem('livetv', 'Live TV', '/livetv', 'radio', ['dropdown' => 'livetv', 'visible' => true]),
                    $this->menuItem('distribution', 'Distribution', '/distribution', 'share', ['visible' => true]),
                    $this->menuItem('stream-music', 'Stream Your Music', '/music', 'music', ['visible' => true]),
                ],
                'burger_toggle' => [
                    'default_open' => false,
                    'open_icon' => 'menu',
                    'open_icon_svg' => $this->iconSvg('menu'),
                    'close_icon' => 'x',
                    'close_icon_svg' => $this->iconSvg('x'),
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

    private function menuItem(string $key, string $label, string $href, string $icon, array $extra = []): array
    {
        return array_merge([
            'key' => $key,
            'label' => $label,
            'href' => $href,
            'icon' => $icon,
            'icon_svg' => $this->iconSvg($icon),
        ], $extra);
    }

    private function iconSvg(string $icon): string
    {
        $attrs = 'xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"';

        return match ($icon) {
            'home' => '<svg ' . $attrs . '><path d="m3 9 9-7 9 7"/><path d="M9 22V12h6v10"/><path d="M5 10v12h14V10"/></svg>',
            'search' => '<svg ' . $attrs . '><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>',
            'film' => '<svg ' . $attrs . '><rect width="18" height="18" x="3" y="3" rx="2"/><path d="M7 3v18"/><path d="M17 3v18"/><path d="M3 7h4"/><path d="M3 17h4"/><path d="M17 7h4"/><path d="M17 17h4"/></svg>',
            'tv' => '<svg ' . $attrs . '><rect width="20" height="15" x="2" y="7" rx="2"/><path d="m17 2-5 5-5-5"/></svg>',
            'globe' => '<svg ' . $attrs . '><circle cx="12" cy="12" r="10"/><path d="M2 12h20"/><path d="M12 2a15.3 15.3 0 0 1 0 20"/><path d="M12 2a15.3 15.3 0 0 0 0 20"/></svg>',
            'video' => '<svg ' . $attrs . '><path d="m16 13 5 3V8l-5 3Z"/><rect width="14" height="12" x="2" y="6" rx="2"/></svg>',
            'radio' => '<svg ' . $attrs . '><path d="M4.9 19.1C1 15.2 1 8.8 4.9 4.9"/><path d="M7.8 16.2a6 6 0 0 1 0-8.5"/><circle cx="12" cy="12" r="2"/><path d="M16.2 7.8a6 6 0 0 1 0 8.5"/><path d="M19.1 4.9c3.9 3.9 3.9 10.2 0 14.1"/></svg>',
            'share' => '<svg ' . $attrs . '><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="m8.6 13.5 6.8 4"/><path d="m15.4 6.5-6.8 4"/></svg>',
            'music' => '<svg ' . $attrs . '><path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/></svg>',
            'menu' => '<svg ' . $attrs . '><path d="M4 12h16"/><path d="M4 6h16"/><path d="M4 18h16"/></svg>',
            'x' => '<svg ' . $attrs . '><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>',
            default => '<svg ' . $attrs . '><circle cx="12" cy="12" r="10"/></svg>',
        };
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
