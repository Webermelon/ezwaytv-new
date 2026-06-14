<?php

namespace Modules\Frontend\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Support\Facades\DB;
use Modules\LiveTV\Models\LiveTvChannel;

class FooterController extends Controller
{
    public function show()
    {
        $footerData = function_exists('getFooterData') ? getFooterData() : [];
        $copyrightText = setting('copyright_text');

        if (empty($copyrightText)) {
            $copyrightText = '© ' . now()->year . ' ' . setting('app_name') . '. All Rights Reserved.';
        }

        return ApiResponse::success([
            'short_description' => $footerData['short_description'] ?? null,
            'inquriy_email' => $footerData['inquriy_email'] ?? null,
            'helpline_number' => $footerData['helpline_number'] ?? null,
            'facebook_url' => $footerData['facebook_url'] ?? null,
            'instagram_url' => $footerData['instagram_url'] ?? null,
            'youtube_url' => $footerData['youtube_url'] ?? null,
            'x_url' => $footerData['x_url'] ?? null,
            'play_store_url' => $footerData['play_store_url'] ?? null,
            'app_store_url' => $footerData['app_store_url'] ?? null,
            'copyright_text' => $copyrightText,
            'premium_shows' => $this->mapContentItems($footerData['premiumShows'] ?? []),
            'top_movies' => $this->mapContentItems($footerData['topMovies'] ?? []),
            'top_channels' => $this->mapChannels($this->topLiveTvChannels()),
            'live_tv_channels' => $this->mapChannels($this->recentLiveTvChannels()),
            'pages' => $this->mapPages($footerData['pages'] ?? []),
        ], 'Footer data retrieved', 200);
    }

    private function mapContentItems($items): array
    {
        return collect($items)->map(function ($item) {
            $type = $item->type ?? null;

            return [
                'id' => $item->id ?? null,
                'name' => $item->name ?? null,
                'slug' => $item->slug ?? null,
                'type' => $type,
                'url' => $type === 'movie'
                    ? route('movie-details', $item->slug ?? '', false)
                    : route('tvshow-details', $item->slug ?? '', false),
            ];
        })->filter(fn ($item) => !empty($item['name']) && !empty($item['slug']))->values()->all();
    }

    private function mapPages($pages): array
    {
        return collect($pages)->map(fn ($page) => [
            'id' => $page->id ?? null,
            'name' => $page->name ?? null,
            'slug' => $page->slug ?? null,
            'url' => route('page.show', ['slug' => $page->slug ?? ''], false),
        ])->filter(fn ($page) => !empty($page['name']) && !empty($page['slug']))->values()->all();
    }

    private function topLiveTvChannels()
    {
        return LiveTvChannel::query()
            ->leftJoin('stat_page_views', function ($join) {
                $join->on('live_tv_channel.id', '=', 'stat_page_views.content_id')
                    ->where('stat_page_views.content_type', 'livetv');
            })
            ->select('live_tv_channel.*', DB::raw('COUNT(stat_page_views.id) as total_views'))
            ->where('live_tv_channel.status', 1)
            ->whereNull('live_tv_channel.deleted_at')
            ->groupBy('live_tv_channel.id')
            ->orderByDesc('total_views')
            ->orderByDesc('live_tv_channel.updated_at')
            ->take(4)
            ->get();
    }

    private function recentLiveTvChannels()
    {
        return LiveTvChannel::query()
            ->where('status', 1)
            ->whereNull('deleted_at')
            ->orderByDesc('updated_at')
            ->take(4)
            ->get();
    }

    private function mapChannels($channels): array
    {
        return collect($channels)->map(fn ($channel) => [
            'id' => $channel->id ?? null,
            'name' => $channel->name ?? null,
            'slug' => $channel->slug ?? null,
            'type' => 'livetv',
            'url' => '/livetv/' . ($channel->slug ?? $channel->id),
        ])->filter(fn ($channel) => !empty($channel['name']) && (!empty($channel['slug']) || !empty($channel['id'])))->values()->all();
    }
}
