<?php

namespace Modules\Statistics\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Statistics\Models\PlayEvent;
use Modules\Statistics\Models\StatSetting;

class StatisticsController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'admin']);
    }

    // ──────────────────────────────────────────────────────────
    // The primary source of play/view data is entertainment_views
    // (populated by the existing /api/save-entertainment-views endpoint).
    // stat_play_events stores richer data (device, platform, watch-time)
    // added going forward via the hook in EntertainmentsController.
    // The two tables are UNIONed so historical + new data both appear.
    // ──────────────────────────────────────────────────────────

    public function index()
    {
        $module_title  = 'Statistics';
        $module_name   = 'statistics';
        $module_icon   = 'ph ph-chart-bar';
        $module_action = 'Overview';

        return view('statistics::backend.statistics.index', compact(
            'module_title', 'module_name', 'module_icon', 'module_action'
        ));
    }

    /**
     * AJAX: Overview stats cards.
     */
    public function overview(Request $request)
    {
        $period = $request->input('period', 'week');
        [$startDate, $endDate] = $this->resolvePeriod($period);

        // Total plays = entertainment_views + stat_play_events (Union for dedup across sources)
        $totalPlays    = $this->playsQuery($startDate, $endDate)->count();
        $uniqueViewers = $this->playsQuery($startDate, $endDate)->distinct('user_id')->count('user_id');
        $watchSeconds  = $this->statPlaysQuery($startDate, null)->sum('watch_seconds');
        $watchHours    = round($watchSeconds / 3600, 1);

        // Content views (stat_page_views, web/app tracking)
        $totalPageViews = DB::table('stat_page_views')
            ->when($startDate, fn($q) => $q->where('view_date', '>=', $startDate))
            ->when($endDate,   fn($q) => $q->where('view_date', '<=', $endDate))
            ->count();

        // Unique visitors from page views
        $uniqueVisitors = DB::table('stat_page_views')
            ->when($startDate, fn($q) => $q->where('view_date', '>=', $startDate))
            ->when($endDate,   fn($q) => $q->where('view_date', '<=', $endDate))
            ->distinct('ip_address')->count('ip_address');

        // Period comparison
        $prevPlays = 0;
        $prevViews = 0;
        if ($startDate && $period !== 'all') {
            [$prevStart, $prevEnd] = $this->resolvePreviousPeriod($period, $startDate);
            $prevPlays = $this->playsQuery($prevStart, $prevEnd)->count();
            $prevViews = DB::table('stat_page_views')
                ->whereBetween('view_date', [$prevStart, $prevEnd])->count();
        }

        return response()->json([
            'total_views'      => number_format($totalPlays),
            'total_plays'      => number_format($totalPlays),
            'page_views'       => number_format($totalPageViews),
            'unique_visitors'  => number_format($uniqueVisitors > 0 ? $uniqueVisitors : $uniqueViewers),
            'watch_hours'      => number_format($watchHours, 1),
            'views_change'     => $prevPlays > 0 ? round((($totalPlays - $prevPlays) / $prevPlays) * 100, 1) : null,
            'plays_change'     => $prevViews > 0 ? round((($totalPageViews - $prevViews) / $prevViews) * 100, 1) : null,
            'page_views_change'=> $prevViews > 0 ? round((($totalPageViews - $prevViews) / $prevViews) * 100, 1) : null,
        ]);
    }

    /**
     * AJAX: Views/plays over time for charting.
     */
    public function chart(Request $request)
    {
        $period      = $request->input('period', 'week');
        $type        = $request->input('type', 'both');
        [$startDate] = $this->resolvePeriod($period);

        $groupFormat  = $period === 'year' ? '%Y-%m' : '%Y-%m-%d';
        $viewData     = [];
        $playData     = [];

        if ($type !== 'plays') {
            // Page views over time
            $rows = DB::table('stat_page_views')
                ->selectRaw("DATE_FORMAT(view_date, '{$groupFormat}') as label, COUNT(*) as total")
                ->when($startDate, fn($q) => $q->where('view_date', '>=', $startDate))
                ->groupBy('label')->orderBy('label')
                ->pluck('total', 'label')->toArray();
            $viewData = $rows;
        }

        if ($type !== 'views') {
            // Plays: entertainment_views (created_at) + stat_play_events (play_date)
            $evRows = DB::table('entertainment_views')
                ->selectRaw("DATE_FORMAT(DATE(created_at), '{$groupFormat}') as label, COUNT(*) as total")
                ->when($startDate, fn($q) => $q->whereDate('created_at', '>=', $startDate))
                ->whereNull('deleted_at')
                ->groupBy('label')->orderBy('label')
                ->pluck('total', 'label')->toArray();

            $spRows = DB::table('stat_play_events')
                ->selectRaw("DATE_FORMAT(play_date, '{$groupFormat}') as label, COUNT(*) as total")
                ->when($startDate, fn($q) => $q->where('play_date', '>=', $startDate))
                ->groupBy('label')->orderBy('label')
                ->pluck('total', 'label')->toArray();

            // Merge both sources
            foreach ($spRows as $label => $count) {
                $evRows[$label] = ($evRows[$label] ?? 0) + $count;
            }
            ksort($evRows);
            $playData = $evRows;
        }

        $labels = array_unique(array_merge(array_keys($viewData), array_keys($playData)));
        sort($labels);

        $views = array_map(fn($l) => $viewData[$l] ?? 0, $labels);
        $plays = array_map(fn($l) => $playData[$l] ?? 0, $labels);

        return response()->json(compact('labels', 'views', 'plays'));
    }

    /**
     * AJAX: Top content by plays (from entertainment_views + stat_play_events).
     */
    public function topContent(Request $request)
    {
        $period      = $request->input('period', 'month');
        $contentType = $request->input('content_type', 'all');
        $limit       = min((int) $request->input('limit', 10), 50);
        [$startDate] = $this->resolvePeriod($period);

        // entertainment_views: all content_type treated as 'entertainment'
        $evQuery = DB::table('entertainment_views as ev')
            ->selectRaw('"entertainment" as content_type, ev.entertainment_id as content_id, COUNT(*) as total')
            ->when($startDate, fn($q) => $q->whereDate('ev.created_at', '>=', $startDate))
            ->whereNull('ev.deleted_at')
            ->groupBy('ev.entertainment_id');

        // stat_play_events: has explicit content_type
        $spQuery = DB::table('stat_play_events')
            ->selectRaw('content_type, content_id, COUNT(*) as total')
            ->whereNotNull('content_id')
            ->when($startDate, fn($q) => $q->where('play_date', '>=', $startDate))
            ->when($contentType !== 'all', fn($q) => $q->where('content_type', $contentType))
            ->groupBy('content_type', 'content_id');

        // For 'entertainment' filter – only ev, else fallback to sp
        if ($contentType === 'all' || $contentType === 'entertainment') {
            $rows = $evQuery->orderByDesc('total')->limit($limit)->get();
        } else {
            $rows = $spQuery->orderByDesc('total')->limit($limit)->get();
        }

        $results = $rows->map(function ($row) {
            [$name, $url] = $this->resolveContentInfo($row->content_type, $row->content_id);
            return [
                'content_type' => $row->content_type,
                'content_id'   => $row->content_id,
                'name'         => $name,
                'url'          => $url,
                'total'        => $row->total,
            ];
        });

        return response()->json($results);
    }

    /**
     * AJAX: Device breakdown (from stat_play_events).
     */
    public function devices(Request $request)
    {
        $period = $request->input('period', 'month');
        [$startDate] = $this->resolvePeriod($period);

        $data = DB::table('stat_play_events')
            ->selectRaw('COALESCE(device_type, "unknown") as device, COUNT(*) as total')
            ->when($startDate, fn($q) => $q->where('play_date', '>=', $startDate))
            ->groupBy('device')
            ->orderByDesc('total')
            ->get();

        if ($data->isEmpty()) {
            // Fallback: show "no breakdown yet" placeholder
            return response()->json(['labels' => ['No data yet'], 'values' => [1]]);
        }

        return response()->json(['labels' => $data->pluck('device'), 'values' => $data->pluck('total')]);
    }

    /**
     * AJAX: Country breakdown.
     */
    public function countries(Request $request)
    {
        $period = $request->input('period', 'month');
        $limit  = min((int) $request->input('limit', 10), 50);
        [$startDate] = $this->resolvePeriod($period);

        $data = DB::table('stat_play_events')
            ->selectRaw('COALESCE(country_code, "Unknown") as country, COUNT(*) as total')
            ->when($startDate, fn($q) => $q->where('play_date', '>=', $startDate))
            ->groupBy('country')
            ->orderByDesc('total')
            ->limit($limit)
            ->get();

        if ($data->isEmpty()) {
            return response()->json(['labels' => ['No data yet'], 'values' => [1]]);
        }

        return response()->json(['labels' => $data->pluck('country'), 'values' => $data->pluck('total')]);
    }

    /**
     * AJAX: Platform breakdown.
     */
    public function platforms(Request $request)
    {
        $period = $request->input('period', 'month');
        [$startDate] = $this->resolvePeriod($period);

        $data = DB::table('stat_play_events')
            ->selectRaw('COALESCE(platform, "web") as platform, COUNT(*) as total')
            ->when($startDate, fn($q) => $q->where('play_date', '>=', $startDate))
            ->groupBy('platform')
            ->orderByDesc('total')
            ->get();

        if ($data->isEmpty()) {
            return response()->json(['labels' => ['No data yet'], 'values' => [1]]);
        }

        return response()->json(['labels' => $data->pluck('platform'), 'values' => $data->pluck('total')]);
    }

    /**
     * AJAX: Traffic sources (from stat_page_views).
     */
    public function traffic(Request $request)
    {
        $period = $request->input('period', 'month');
        $limit  = min((int) $request->input('limit', 10), 50);
        [$startDate] = $this->resolvePeriod($period);

        $data = DB::table('stat_page_views')
            ->selectRaw("
                CASE
                    WHEN referrer IS NULL OR referrer = '' THEN 'Direct'
                    WHEN referrer LIKE '%google.%' THEN 'Google'
                    WHEN referrer LIKE '%facebook.%' THEN 'Facebook'
                    WHEN referrer LIKE '%twitter.%' OR referrer LIKE '%x.com%' THEN 'Twitter/X'
                    WHEN referrer LIKE '%instagram.%' THEN 'Instagram'
                    WHEN referrer LIKE '%youtube.%' THEN 'YouTube'
                    WHEN referrer LIKE '%t.me%' OR referrer LIKE '%telegram.%' THEN 'Telegram'
                    ELSE 'Other'
                END as source,
                COUNT(*) as total
            ")
            ->when($startDate, fn($q) => $q->where('view_date', '>=', $startDate))
            ->groupBy('source')
            ->orderByDesc('total')
            ->limit($limit)
            ->get();

        if ($data->isEmpty()) {
            return response()->json(['labels' => ['No data yet'], 'values' => [1]]);
        }

        return response()->json(['labels' => $data->pluck('source'), 'values' => $data->pluck('total')]);
    }

    /**
     * AJAX: Top active users (from entertainment_views + stat_play_events).
     */
    public function topUsers(Request $request)
    {
        $period = $request->input('period', 'month');
        $limit  = min((int) $request->input('limit', 10), 50);
        [$startDate] = $this->resolvePeriod($period);

        // Count plays per user from entertainment_views
        $evData = DB::table('entertainment_views')
            ->selectRaw('user_id, COUNT(*) as plays')
            ->whereNotNull('user_id')
            ->whereNull('deleted_at')
            ->when($startDate, fn($q) => $q->whereDate('created_at', '>=', $startDate))
            ->groupBy('user_id')
            ->pluck('plays', 'user_id')
            ->toArray();

        // Count plays + watch seconds from stat_play_events
        $spData = DB::table('stat_play_events')
            ->selectRaw('user_id, COUNT(*) as plays, SUM(watch_seconds) as watch_seconds')
            ->whereNotNull('user_id')
            ->when($startDate, fn($q) => $q->where('play_date', '>=', $startDate))
            ->groupBy('user_id')
            ->get()
            ->keyBy('user_id')
            ->toArray();

        // Merge
        $merged = [];
        foreach ($evData as $uid => $plays) {
            $merged[$uid] = ['plays' => $plays, 'watch_seconds' => 0];
        }
        foreach ($spData as $uid => $row) {
            $merged[$uid] = [
                'plays'         => ($merged[$uid]['plays'] ?? 0) + $row->plays,
                'watch_seconds' => ($merged[$uid]['watch_seconds'] ?? 0) + $row->watch_seconds,
            ];
        }

        arsort($merged);
        $merged = array_slice($merged, 0, $limit, true);

        $results = collect($merged)->map(function ($data, $uid) {
            $user = DB::table('users')->where('id', $uid)->first(['id', 'username', 'email', 'first_name', 'last_name']);
            return [
                'user_id'     => $uid,
                'name'        => $user ? (trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: ($user->username ?? $user->email)) : 'Deleted User',
                'email'       => $user->email ?? '',
                'plays'       => $data['plays'],
                'watch_hours' => round($data['watch_seconds'] / 3600, 1),
            ];
        })->values();

        return response()->json($results);
    }

    /**
     * AJAX: Individual page-view records (for detail table).
     */
    public function pageViews(Request $request)
    {
        $period = $request->input('period', 'week');
        $limit  = min((int) $request->input('limit', 50), 200);
        $offset = (int) $request->input('offset', 0);
        [$startDate, $endDate] = $this->resolvePeriod($period);

        $rows = DB::table('stat_page_views as pv')
            ->leftJoin('users as u', 'u.id', '=', 'pv.user_id')
            ->select([
                'pv.id', 'pv.content_type', 'pv.content_id',
                'pv.page_name', 'pv.route_name', 'pv.page_url',
                'pv.device_type', 'pv.browser', 'pv.os', 'pv.platform',
                'pv.country_code', 'pv.ip_address', 'pv.view_date', 'pv.created_at',
                DB::raw("COALESCE(NULLIF(TRIM(CONCAT(COALESCE(u.first_name,''),' ',COALESCE(u.last_name,''))), ''), u.username, u.email) as user_name"),
                'u.email as user_email',
            ])
            ->when($startDate, fn($q) => $q->where('pv.view_date', '>=', $startDate))
            ->when($endDate,   fn($q) => $q->where('pv.view_date', '<=', $endDate))
            ->orderByDesc('pv.created_at')
            ->limit($limit)->offset($offset)
            ->get();

        $total = DB::table('stat_page_views')
            ->when($startDate, fn($q) => $q->where('view_date', '>=', $startDate))
            ->when($endDate,   fn($q) => $q->where('view_date', '<=', $endDate))
            ->count();

        $rows = $rows->map(function ($row) {
            $row->content_name = ($row->content_id && $row->content_type)
                ? $this->resolveContentName($row->content_type, (int) $row->content_id)
                : null;
            return $row;
        });

        return response()->json(compact('rows', 'total'));
    }

    /**
     * AJAX: Individual play-event records (for detail table).
     */
    public function playEvents(Request $request)
    {
        $period = $request->input('period', 'week');
        $limit  = min((int) $request->input('limit', 50), 200);
        $offset = (int) $request->input('offset', 0);
        [$startDate, $endDate] = $this->resolvePeriod($period);

        $rows = DB::table('stat_play_events as pe')
            ->leftJoin('users as u', 'u.id', '=', 'pe.user_id')
            ->select([
                'pe.id', 'pe.content_type', 'pe.content_id',
                'pe.device_type', 'pe.platform', 'pe.country_code',
                'pe.ip_address', 'pe.watch_seconds', 'pe.quality',
                'pe.play_date', 'pe.created_at',
                DB::raw("COALESCE(NULLIF(TRIM(CONCAT(COALESCE(u.first_name,''),' ',COALESCE(u.last_name,''))), ''), u.username, u.email) as user_name"),
                'u.email as user_email',
            ])
            ->when($startDate, fn($q) => $q->where('pe.play_date', '>=', $startDate))
            ->when($endDate,   fn($q) => $q->where('pe.play_date', '<=', $endDate))
            ->orderByDesc('pe.created_at')
            ->limit($limit)->offset($offset)
            ->get();

        $total = DB::table('stat_play_events')
            ->when($startDate, fn($q) => $q->where('play_date', '>=', $startDate))
            ->when($endDate,   fn($q) => $q->where('play_date', '<=', $endDate))
            ->count();

        $rows = $rows->map(function ($row) {
            $row->content_name = ($row->content_id && $row->content_type)
                ? $this->resolveContentName($row->content_type, (int) $row->content_id)
                : null;
            $row->watch_time = $row->watch_seconds > 0
                ? gmdate('H:i:s', $row->watch_seconds)
                : '—';
            return $row;
        });

        return response()->json(compact('rows', 'total'));
    }

    /**
     * Statistics settings page.
     */
    public function settings()
    {
        $module_title  = 'Statistics Settings';
        $module_name   = 'statistics';
        $module_icon   = 'ph ph-gear';
        $module_action = 'Settings';

        $settings = StatSetting::all()->pluck('value', 'key');

        return view('statistics::backend.statistics.settings', compact(
            'module_title', 'module_name', 'module_icon', 'module_action', 'settings'
        ));
    }

    /**
     * Save statistics settings.
     */
    public function saveSettings(Request $request)
    {
        $keys = [
            'track_page_views', 'track_play_events', 'track_watch_time',
            'track_guests', 'exclude_bots', 'retention_days',
            'heartbeat_interval', 'exclude_ips',
        ];

        foreach ($keys as $key) {
            if ($request->has($key)) {
                StatSetting::set($key, $request->input($key));
            } else {
                $checkboxKeys = ['track_page_views', 'track_play_events', 'track_watch_time', 'track_guests', 'exclude_bots'];
                if (in_array($key, $checkboxKeys)) {
                    StatSetting::set($key, '0');
                }
            }
        }

        return redirect()->route('backend.statistics.settings')->with('success', 'Statistics settings saved.');
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    /**
     * Unified plays query: entertainment_views UNION stat_play_events.
     */
    private function playsQuery(?string $startDate, ?string $endDate)
    {
        return DB::table('entertainment_views')
            ->whereNull('deleted_at')
            ->when($startDate, fn($q) => $q->whereDate('created_at', '>=', $startDate))
            ->when($endDate,   fn($q) => $q->whereDate('created_at', '<=', $endDate));
    }

    private function statPlaysQuery(?string $startDate, ?string $endDate)
    {
        return DB::table('stat_play_events')
            ->when($startDate, fn($q) => $q->where('play_date', '>=', $startDate))
            ->when($endDate,   fn($q) => $q->where('play_date', '<=', $endDate));
    }

    private function resolvePeriod(string $period): array
    {
        return match ($period) {
            'today'  => [now()->toDateString(), now()->toDateString()],
            'week'   => [now()->subDays(6)->toDateString(), now()->toDateString()],
            'month'  => [now()->subDays(29)->toDateString(), now()->toDateString()],
            'year'   => [now()->subDays(364)->toDateString(), now()->toDateString()],
            default  => [null, null],
        };
    }

    private function resolvePreviousPeriod(string $period, string $startDate): array
    {
        $days = match ($period) {
            'today' => 1,
            'week'  => 7,
            'month' => 30,
            'year'  => 365,
            default => 30,
        };

        $end   = \Carbon\Carbon::parse($startDate)->subDay()->toDateString();
        $start = \Carbon\Carbon::parse($startDate)->subDays($days)->toDateString();

        return [$start, $end];
    }

    private function resolveContentName(string $contentType, int $contentId): string
    {
        return $this->resolveContentInfo($contentType, $contentId)[0];
    }

    private function resolveContentInfo(string $contentType, int $contentId): array
    {
        $tableMap = [
            'video'         => 'videos',
            'movie'         => 'entertainments',
            'tvshow'        => 'entertainments',
            'entertainment' => 'entertainments',
            'episode'       => 'episodes',
            'livetv'        => 'live_tv_channels',
            'livetvchannel' => 'live_tv_channels',
        ];

        $table = $tableMap[$contentType] ?? null;
        if (!$table) {
            return ["#{$contentId}", null];
        }

        $row = DB::table($table)->where('id', $contentId)->first(['name', 'slug', 'type']);
        if (!$row) {
            return ["#{$contentId}", null];
        }

        $name = $row->name ?? "#{$contentId}";
        $slug = $row->slug ?? $contentId;
        $type = $row->type ?? $contentType;

        $url = match ($contentType) {
            'video'         => url('/video-details/' . $slug),
            'episode'       => url('/episode-details/' . $slug),
            'livetv',
            'livetvchannel' => url('/livetv-details/' . $contentId),
            'movie'         => url('/movie-details/' . $slug),
            'tvshow'        => url('/tvshow-details/' . $slug),
            'entertainment' => match ($type) {
                'movie'  => url('/movie-details/' . $slug),
                'tvshow' => url('/tvshow-details/' . $slug),
                default  => url('/movie-details/' . $slug),
            },
            default => null,
        };

        return [$name, $url];
    }
}
