<?php

namespace Modules\Statistics\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Modules\Statistics\Models\ContentBoost;
use Modules\Statistics\Models\StatSetting;

class ContentBoostController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'admin']);
        // Extra gate: only 'admin' role (not demo_admin)
        $this->middleware(function ($request, $next) {
            if (!auth()->user()->hasRole('admin')) {
                abort(403);
            }
            return $next($request);
        });
    }

    public function index()
    {
        $module_title  = 'Content Booster';
        $module_name   = 'statistics';
        $module_icon   = 'ph ph-rocket-launch';
        $module_action = 'Booster';

        $hasWatchBoostColumn = $this->hasWatchBoostColumn();
        $hasVisitorBoostColumn = $this->hasVisitorBoostColumn();
        $watchSelect = $hasWatchBoostColumn
            ? ', SUM(boost_watch_seconds) as boost_watch_seconds'
            : ', 0 as boost_watch_seconds';
        $visitorSelect = $hasVisitorBoostColumn
            ? ', SUM(boost_unique_visitors) as boost_unique_visitors'
            : ', 0 as boost_unique_visitors';

        $boosts = ContentBoost::selectRaw(
                'content_type, content_id, MAX(content_name) as content_name, ' .
                'SUM(boost_plays) as boost_plays, SUM(boost_views) as boost_views' .
                $watchSelect .
                $visitorSelect .
                ', COUNT(*) as entries, MAX(updated_at) as updated_at'
            )
            ->groupBy('content_type', 'content_id')
            ->orderByDesc('updated_at')
            ->get();

        // Load settings so admin booster form can render here
        $settings = \Modules\Statistics\Models\StatSetting::all()->pluck('value', 'key');

        return view('statistics::backend.statistics.booster', compact(
            'module_title', 'module_name', 'module_icon', 'module_action', 'boosts', 'settings'
        ));
    }

    /**
     * AJAX: Search content by name across videos, episodes, live_tv_channel.
     */
    public function search(Request $request)
    {
        $q     = trim($request->input('q', ''));
        $type  = $request->input('type', 'all');
        $limit = 20;

        if (strlen($q) < 2) {
            return response()->json([]);
        }

        $hasWatchBoostColumn = $this->hasWatchBoostColumn();
        $hasVisitorBoostColumn = $this->hasVisitorBoostColumn();

        $results = collect();

        $searchTypes = $type === 'all'
            ? ['video', 'ondemand_channel', 'ondemand_video', 'episode', 'livetv']
            : [$type];

        foreach ($searchTypes as $ctype) {
            [$table, $label] = match ($ctype) {
                'video'   => ['videos', 'Video'],
                'ondemand_channel' => ['author_channels', 'On Demand Channel'],
                'ondemand_video' => ['videos', 'On Demand Video'],
                'episode' => ['episodes', 'Episode'],
                'livetv'  => ['live_tv_channel', 'Live TV'],
                default   => [null, null],
            };

            if (!$table) continue;

            $rows = DB::table($table)
                ->where('name', 'like', "%{$q}%")
                ->when($ctype === 'ondemand_channel', fn($query) => $query->where('is_active', 1))
                ->whereNull('deleted_at')
                ->limit($limit)
                ->get(['id', 'name']);

            foreach ($rows as $row) {
                // Get real play count
                $realPlays = in_array($ctype, ['ondemand_video', 'ondemand_channel'], true) ? 0 : DB::table('entertainment_views')
                    ->where('entertainment_id', $row->id)
                    ->whereNull('deleted_at')
                    ->count();
                if ($ctype === 'ondemand_channel') {
                    $videoIds = DB::table('author_channel_video')
                        ->where('author_channel_id', $row->id)
                        ->pluck('video_id');

                    $realPlays += DB::table('stat_play_events')
                        ->where('content_type', 'video')
                        ->whereIn('content_id', $videoIds)
                        ->count();
                    $realPlays += DB::table('stat_play_events')
                        ->where('content_type', 'ondemand_video')
                        ->where('channel_id', $row->id)
                        ->count();
                    $realPlays += DB::table('entertainment_views')
                        ->whereIn('entertainment_id', $videoIds)
                        ->whereNull('deleted_at')
                        ->count();
                } else {
                    $realPlays += DB::table('stat_play_events')
                        ->where('content_id', $row->id)
                        ->where('content_type', $ctype)
                        ->count();
                }

                // Get existing boost if any (summed)
                $boostSums = ContentBoost::where('content_type', $ctype)
                    ->where('content_id', $row->id)
                    ->selectRaw(
                        'SUM(boost_plays) as total_plays, SUM(boost_views) as total_views, ' .
                        ($hasWatchBoostColumn ? 'SUM(boost_watch_seconds)' : '0') . ' as total_watch_seconds, ' .
                        ($hasVisitorBoostColumn ? 'SUM(boost_unique_visitors)' : '0') . ' as total_unique_visitors, COUNT(*) as entries'
                    )
                    ->first();

                $results->push([
                    'content_type'  => $ctype,
                    'content_id'    => $row->id,
                    'name'          => $row->name,
                    'type_label'    => $label,
                    'real_plays'    => $realPlays,
                    'boost_plays'   => (int)($boostSums->total_plays ?? 0),
                    'boost_views'   => (int)($boostSums->total_views ?? 0),
                    'boost_watch_seconds' => (int)($boostSums->total_watch_seconds ?? 0),
                    'boost_unique_visitors' => (int)($boostSums->total_unique_visitors ?? 0),
                    'boost_entries' => (int)($boostSums->entries ?? 0),
                ]);
            }
        }

        return response()->json($results->take($limit)->values());
    }

    /**
     * AJAX: Get current real stats + existing boost for a specific content item.
     */
    public function stats(Request $request)
    {
        $type = $request->input('content_type');
        $id   = (int) $request->input('content_id');

        if (!$type || !$id) {
            return response()->json(['error' => 'Missing params'], 422);
        }

        $hasWatchBoostColumn = $this->hasWatchBoostColumn();
        $hasVisitorBoostColumn = $this->hasVisitorBoostColumn();

        // Real counts
        $realPlays = in_array($type, ['ondemand_video', 'ondemand_channel'], true) ? 0 : DB::table('entertainment_views')
            ->where('entertainment_id', $id)
            ->whereNull('deleted_at')
            ->count();
        if ($type === 'ondemand_channel') {
            $videoIds = DB::table('author_channel_video')->where('author_channel_id', $id)->pluck('video_id');
            $realPlays += DB::table('stat_play_events')->where('content_type', 'video')->whereIn('content_id', $videoIds)->count();
            $realPlays += DB::table('stat_play_events')->where('content_type', 'ondemand_video')->where('channel_id', $id)->count();
            $realPlays += DB::table('entertainment_views')->whereIn('entertainment_id', $videoIds)->whereNull('deleted_at')->count();

            $realViews = DB::table('stat_page_views')->where('content_type', 'video')->whereIn('content_id', $videoIds)->count();
            $realViews += DB::table('stat_page_views')->where('content_type', 'ondemand_video')->where('channel_id', $id)->count();
            $realViews += DB::table('stat_page_views')->where('content_type', 'ondemand_channel')->where(fn($q) => $q->where('content_id', $id)->orWhere('channel_id', $id))->count();

            $realUniqueVisitors = (int) DB::table('stat_page_views')
                ->where(function ($q) use ($videoIds, $id) {
                    $q->where(fn($sub) => $sub->where('content_type', 'video')->whereIn('content_id', $videoIds))
                        ->orWhere(fn($sub) => $sub->where('content_type', 'ondemand_video')->where('channel_id', $id))
                        ->orWhere(fn($sub) => $sub->where('content_type', 'ondemand_channel')->where(fn($inner) => $inner->where('content_id', $id)->orWhere('channel_id', $id)));
                })
                ->distinct('ip_address')
                ->count('ip_address');

            $realWatchSeconds = (int) DB::table('stat_play_events')->where('content_type', 'video')->whereIn('content_id', $videoIds)->sum('watch_seconds');
            $realWatchSeconds += (int) DB::table('stat_play_events')->where('content_type', 'ondemand_video')->where('channel_id', $id)->sum('watch_seconds');
        } else {
            $realPlays += DB::table('stat_play_events')
                ->where('content_id', $id)
                ->where('content_type', $type)
                ->count();

            $realViews = DB::table('stat_page_views')
                ->where('content_id', $id)
                ->where('content_type', $type)
                ->count();

            $realUniqueVisitors = (int) DB::table('stat_page_views')
                ->where('content_id', $id)
                ->where('content_type', $type)
                ->distinct('ip_address')
                ->count('ip_address');

            $realWatchSeconds = (int) DB::table('stat_play_events')
                ->where('content_id', $id)
                ->where('content_type', $type)
                ->sum('watch_seconds');
        }

        // Summed boosts
        $boostSums = ContentBoost::where('content_type', $type)
            ->where('content_id', $id)
            ->selectRaw(
                'SUM(boost_plays) as total_plays, SUM(boost_views) as total_views, ' .
                ($hasWatchBoostColumn ? 'SUM(boost_watch_seconds)' : '0') . ' as total_watch_seconds, ' .
                ($hasVisitorBoostColumn ? 'SUM(boost_unique_visitors)' : '0') . ' as total_unique_visitors'
            )
            ->first();

        // Boost history (newest first)
        $history = ContentBoost::where('content_type', $type)
            ->where('content_id', $id)
            ->orderByDesc('created_at')
            ->get(array_filter([
                'id',
                'boost_plays',
                'boost_views',
                $hasWatchBoostColumn ? 'boost_watch_seconds' : null,
                $hasVisitorBoostColumn ? 'boost_unique_visitors' : null,
                'note',
                'created_at',
            ]));

        return response()->json([
            'real_plays'   => $realPlays,
            'real_views'   => $realViews,
            'real_unique_visitors' => $realUniqueVisitors,
            'real_watch_seconds' => $realWatchSeconds,
            'boost_plays'  => (int)($boostSums->total_plays ?? 0),
            'boost_views'  => (int)($boostSums->total_views ?? 0),
            'boost_watch_seconds' => (int)($boostSums->total_watch_seconds ?? 0),
            'boost_unique_visitors' => (int)($boostSums->total_unique_visitors ?? 0),
            'views_display_mode' => $this->displayModeFor('views', $type, $id),
            'plays_display_mode' => $this->displayModeFor('plays', $type, $id),
            'show_player_views' => StatSetting::get("show_player_views:{$type}:{$id}", $type === 'livetv' ? '0' : '1') === '1',
            'global_views_display_mode' => StatSetting::get('views_display_mode', 'combined'),
            'global_plays_display_mode' => StatSetting::get('plays_display_mode', 'combined'),
            'history'      => $history,
        ]);
    }

    public function saveDisplayMode(Request $request)
    {
        $validated = $request->validate([
            'content_type' => 'required|string|max:50',
            'content_id' => 'required|integer|min:1',
            'views_display_mode' => 'required|string|in:inherit,combined,real,boosted,hidden',
            'plays_display_mode' => 'required|string|in:inherit,combined,real,boosted,hidden',
            'show_player_views' => 'required|boolean',
        ]);

        $this->setDisplayModeOverride('views', $validated['content_type'], (int) $validated['content_id'], $validated['views_display_mode']);
        $this->setDisplayModeOverride('plays', $validated['content_type'], (int) $validated['content_id'], $validated['plays_display_mode']);
        StatSetting::set(
            "show_player_views:{$validated['content_type']}:{$validated['content_id']}",
            $validated['show_player_views'] ? '1' : '0'
        );
        $this->clearFrontendStatsCache($validated['content_type']);

        return response()->json([
            'success' => true,
            'views_display_mode' => $this->displayModeFor('views', $validated['content_type'], (int) $validated['content_id']),
            'plays_display_mode' => $this->displayModeFor('plays', $validated['content_type'], (int) $validated['content_id']),
            'show_player_views' => (bool) $validated['show_player_views'],
        ]);
    }

    /**
     * Add a new boost entry (always inserts — stacked/additive).
     */
    public function save(Request $request)
    {
        $validated = $request->validate([
            'content_type' => 'required|string|max:50',
            'content_id'   => 'required|integer|min:1',
            'boost_plays'  => 'required|integer|min:0',
            'boost_views'  => 'required|integer|min:0',
            'boost_watch_seconds' => 'nullable|integer|min:0',
            'boost_unique_visitors' => 'nullable|integer|min:0',
            'note'         => 'nullable|string|max:255',
        ]);

        // Resolve content name for display
        $name = $this->resolveName($validated['content_type'], $validated['content_id']);

        $payload = [
            'content_type' => $validated['content_type'],
            'content_id'   => $validated['content_id'],
            'content_name' => $name,
            'boost_plays'  => $validated['boost_plays'],
            'boost_views'  => $validated['boost_views'],
            'note'         => $validated['note'] ?? null,
        ];

        if ($this->hasWatchBoostColumn()) {
            $payload['boost_watch_seconds'] = (int) ($validated['boost_watch_seconds'] ?? 0);
        }

        if ($this->hasVisitorBoostColumn()) {
            $payload['boost_unique_visitors'] = (int) ($validated['boost_unique_visitors'] ?? 0);
        }

        ContentBoost::create($payload);
        $this->clearFrontendStatsCache($validated['content_type']);

        return response()->json(['success' => true, 'name' => $name]);
    }

    /**
     * Remove a content boost.
     */
    public function destroy(int $id)
    {
        $boost = ContentBoost::findOrFail($id);
        $contentType = $boost->content_type;
        $boost->delete();
        $this->clearFrontendStatsCache($contentType);

        return response()->json(['success' => true]);
    }

    private function resolveName(string $type, int $id): string
    {
        $table = match ($type) {
            'video'   => 'videos',
            'ondemand_channel' => 'author_channels',
            'ondemand_video' => 'videos',
            'episode' => 'episodes',
            'livetv'  => 'live_tv_channel',
            default   => 'videos',
        };

        $row = DB::table($table)->where('id', $id)->first(['name']);
        return $row->name ?? "#{$id}";
    }

    private function hasWatchBoostColumn(): bool
    {
        static $hasColumn = null;

        if ($hasColumn === null) {
            $hasColumn = Schema::hasColumn('stat_content_boosts', 'boost_watch_seconds');
        }

        return $hasColumn;
    }

    private function hasVisitorBoostColumn(): bool
    {
        static $hasColumn = null;

        if ($hasColumn === null) {
            $hasColumn = Schema::hasColumn('stat_content_boosts', 'boost_unique_visitors');
        }

        return $hasColumn;
    }

    private function displayModeFor(string $metric, string $type, int $id): string
    {
        $allowed = ['combined', 'real', 'boosted', 'hidden'];
        $override = StatSetting::get("{$metric}_display_mode:{$type}:{$id}");
        if (in_array($override, $allowed, true)) {
            return $override;
        }

        $global = StatSetting::get("{$metric}_display_mode", 'combined');
        return in_array($global, $allowed, true) ? $global : 'combined';
    }

    private function setDisplayModeOverride(string $metric, string $type, int $id, string $mode): void
    {
        $key = "{$metric}_display_mode:{$type}:{$id}";

        if ($mode === 'inherit') {
            StatSetting::where('key', $key)->delete();
            return;
        }

        StatSetting::set($key, $mode);
    }

    private function clearFrontendStatsCache(string $contentType): void
    {
        if ($contentType === 'livetv' && function_exists('clearLiveTvDashboardCache')) {
            clearLiveTvDashboardCache();
        }

        Cache::forget('stat_settings_all');
    }
}
