<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TvContentFeedService
{
    /**
     * Returns catalog metadata only. Playback and source URLs are deliberately excluded.
     *
     * @param array{content_type?: string, from?: string, to?: string, author?: string, name?: string, slug?: string, id?: int, access?: string} $filters
     */
    public function page(int $page = 1, int $perPage = 12, bool $childProfile = false, array $filters = []): array
    {
        // Query catalog tables directly and never expose playback metadata.
        $videos = $this->published('videos', $childProfile)->select([
            'id', 'name', 'slug', 'description', 'poster_url', 'access', 'created_at',
        ])->selectSub($this->authorSubquery('author_channel_video', 'video_id', 'videos.id'), 'author')
            ->selectRaw("'video' as content_type, NULL as show_id");

        $entertainments = $this->published('entertainments', $childProfile)
            ->whereIn('type', ['movie', 'tvshow'])
            ->select(['id', 'name', 'slug', 'description', 'poster_url', 'movie_access as access', 'created_at'])
            ->selectSub($this->authorSubquery('author_channel_entertainment', 'entertainment_id', 'entertainments.id'), 'author')
            ->selectRaw('type as content_type, NULL as show_id');

        $episodes = $this->published('episodes', $childProfile)
            ->whereIn('entertainment_id', $this->published('entertainments', $childProfile)
                ->where('type', 'tvshow')->select('id'))
            ->whereIn('season_id', DB::table('seasons')->where('status', 1)->whereNull('deleted_at')->select('id'))
            ->select(['id', 'name', 'slug', 'description', 'poster_url', 'access', 'created_at'])
            ->selectRaw("NULL as author, 'episode' as content_type, entertainment_id as show_id");

        $liveTv = DB::table('live_tv_channel')->where('status', 1)->whereNull('deleted_at')
            ->select(['id', 'name', 'slug', 'description', 'poster_url', 'access', 'created_at'])
            ->selectRaw("NULL as author, 'livetv' as content_type, NULL as show_id");

        $query = DB::query()->fromSub($videos->unionAll($entertainments)->unionAll($episodes)->unionAll($liveTv), 'tv_feed');
        $this->applyFilters($query, $filters);
        $total = (clone $query)->count();
        $items = $query->orderByDesc('created_at')->orderBy('content_type')->orderByDesc('id')
            ->forPage($page, $perPage)->get()->map(fn ($item) => $this->item($item))->all();

        return [
            'status' => true,
            'data' => $items,
            'hasMore' => ($page * $perPage) < $total,
            'meta' => ['page' => $page, 'per_page' => $perPage, 'total' => $total, 'filters' => $this->activeFilters($filters)],
        ];
    }

    private function applyFilters(Builder $query, array $filters): void
    {
        foreach (['content_type', 'slug', 'id', 'access'] as $key) {
            if (isset($filters[$key]) && $filters[$key] !== '') {
                $query->where($key, $filters[$key]);
            }
        }

        if (! empty($filters['from'])) $query->whereDate('created_at', '>=', $filters['from']);
        if (! empty($filters['to'])) $query->whereDate('created_at', '<=', $filters['to']);
        if (! empty($filters['name'])) $query->where('name', 'like', '%' . addcslashes($filters['name'], '%_\\') . '%');
        if (! empty($filters['author'])) $query->where('author', 'like', '%' . addcslashes($filters['author'], '%_\\') . '%');
    }

    private function activeFilters(array $filters): array
    {
        return array_filter($filters, fn ($value) => $value !== null && $value !== '');
    }

    private function authorSubquery(string $pivotTable, string $contentColumn, string $contentIdColumn): Builder
    {
        return DB::table($pivotTable . ' as channel_content')
            ->join('author_channels as author_channel', 'author_channel.id', '=', 'channel_content.author_channel_id')
            ->whereColumn('channel_content.' . $contentColumn, $contentIdColumn)
            ->whereNull('author_channel.deleted_at')
            ->selectRaw("COALESCE(NULLIF(author_channel.name, ''), author_channel.username)")
            ->limit(1);
    }

    private function published(string $table, bool $childProfile): Builder
    {
        return DB::table($table)->where('status', 1)->whereNull('deleted_at')
            ->where(fn (Builder $query) => $query->whereDate('release_date', '<=', now())->orWhereNull('release_date'))
            ->when($childProfile, fn (Builder $query) => $query->where('is_restricted', 0));
    }

    private function item(object $item): array
    {
        $type = $item->content_type;
        $path = match ($type) {
            'movie' => '/watch-movie/' . $item->id,
            'tvshow' => '/tv-shows/' . $item->id,
            'episode' => '/tv-shows/' . $item->show_id . '/episodes/' . $item->id,
            'livetv' => '/livetv/' . $item->id,
            default => '/video-details/' . $item->id,
        };
        $category = match ($type) {
            'movie' => 'Movie',
            'tvshow' => 'TV Show',
            'episode' => 'Episode',
            'livetv' => 'Live TV',
            default => 'Video',
        };
        $mediaDirectory = match ($type) {
            'livetv' => 'livetv',
            'episode' => 'episode',
            default => $type,
        };

        return [
            'id' => $type . ':' . $item->id,
            'content_id' => $item->id,
            'content_type' => $type,
            'title' => $item->name,
            'slug' => $item->slug,
            'description' => Str::limit(trim(html_entity_decode(strip_tags($item->description ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8')), 240),
            // Internal eZWay TV page, never the video or stream source URL.
            'platform_url' => url($path),
            'thumbnail' => $item->poster_url ? setBaseUrlWithFileName($item->poster_url, 'image', $mediaDirectory) : null,
            'author' => $item->author,
            'source' => 'eZWay TV',
            'category' => $category,
            'access' => $item->access ?: 'free',
            'published_at_iso' => $item->created_at ? Carbon::parse($item->created_at)->toIso8601String() : null,
        ];
    }
}
