<?php

namespace Modules\Statistics\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Statistics\Models\PlayEvent;
use Modules\Statistics\Models\StatSetting;
use Modules\Statistics\Models\ContentBoost;
// External stream API disabled: only internal ezway.tv data returned

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

        // Total plays = whichever source has more records for the period
        // stat_play_events is populated going forward; entertainment_views has historical data
        $evCount       = $this->playsQuery($startDate, $endDate)->count();
        $spCount       = $this->statPlaysQuery($startDate, $endDate)->count();
        $totalPlays    = max($evCount, $spCount);
        $evViewers     = $this->playsQuery($startDate, $endDate)->distinct('user_id')->count('user_id');
        $spViewers     = $this->statPlaysQuery($startDate, $endDate)->distinct('user_id')->count('user_id');
        $uniqueViewers = max($evViewers, $spViewers);
        $watchSeconds  = $this->statPlaysQuery($startDate, $endDate)->sum('watch_seconds');
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

        // Include per-content boosts into totals (stacked boosts)
        $boostPlaysSum = (int) ContentBoost::sum('boost_plays');
        $boostViewsSum = (int) ContentBoost::sum('boost_views');
        $boostWatchSecondsSum = Schema::hasColumn('stat_content_boosts', 'boost_watch_seconds')
            ? (int) ContentBoost::sum('boost_watch_seconds')
            : 0;
        $boostUniqueVisitorsSum = Schema::hasColumn('stat_content_boosts', 'boost_unique_visitors')
            ? (int) ContentBoost::sum('boost_unique_visitors')
            : 0;

        $totalPlays     = $totalPlays + $boostPlaysSum;
        $totalPageViews = $totalPageViews + $boostViewsSum;
        $uniqueVisitors = $uniqueVisitors + $boostUniqueVisitorsSum;
        $uniqueViewers  = $uniqueViewers + $boostUniqueVisitorsSum;
        $watchHours      = round(($watchSeconds + $boostWatchSecondsSum) / 3600, 1);
        if ($startDate && $period !== 'all') {
            // Apply the same fixed per-content boosts to previous totals for fair comparison.
            $prevPlays += $boostPlaysSum;
            $prevViews += $boostViewsSum;
        }

        // ── Boost (admin-only global multiplier/fixed) ─────
        $totalPlays     = $this->applyBoost($totalPlays,     'plays');
        $totalPageViews = $this->applyBoost($totalPageViews, 'views');
        $uniqueViewers  = $this->applyBoost($uniqueViewers,  'visitors');
        $uniqueVisitors = $this->applyBoost($uniqueVisitors, 'visitors');
        if ($startDate && $period !== 'all') {
            $prevPlays = $this->applyBoost($prevPlays, 'plays');
            $prevViews = $this->applyBoost($prevViews, 'views');
        }

        $playsChange = $this->calculatePercentChange($totalPlays, $prevPlays, $period);
        $pageViewsChange = $this->calculatePercentChange($totalPageViews, $prevViews, $period);

        // Respect display toggles (admin-controlled)
        $showPageViews = \Modules\Statistics\Models\StatSetting::get('show_page_views', '1') === '1';
        $showPlayerPlays = \Modules\Statistics\Models\StatSetting::get('show_player_plays', '1') === '1';

        if (!$showPageViews) {
            $totalPageViews = 0;
            $pageViewsChange = null;
        }
        if (!$showPlayerPlays) {
            $totalPlays = 0;
            $playsChange = null;
        }

        return response()->json([
            'total_views'      => number_format($totalPlays),
            'total_plays'      => number_format($totalPlays),
            'page_views'       => number_format($totalPageViews),
            'unique_visitors'  => number_format($uniqueVisitors > 0 ? $uniqueVisitors : $uniqueViewers),
            'watch_hours'      => number_format($watchHours, 1),
            'views_change'     => $playsChange,
            'plays_change'     => $playsChange,
            'page_views_change'=> $pageViewsChange,
            'boost_active'     => (float)(StatSetting::get('boost_multiplier', 1)) != 1.0
                                  || (int)(StatSetting::get('boost_fixed_plays', 0)) > 0
                                  || (int)(StatSetting::get('boost_fixed_views', 0)) > 0
                                  || $boostWatchSecondsSum > 0
                                  || $boostUniqueVisitorsSum > 0,
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
            // Page views from stat_page_views
            $pvRows = DB::table('stat_page_views')
                ->selectRaw("DATE_FORMAT(view_date, '{$groupFormat}') as label, COUNT(*) as total")
                ->when($startDate, fn($q) => $q->where('view_date', '>=', $startDate))
                ->groupBy('label')->orderBy('label')
                ->pluck('total', 'label')->toArray();

            // Also include entertainment_views as content views (primary data source)
            $evViewRows = DB::table('entertainment_views')
                ->selectRaw("DATE_FORMAT(DATE(created_at), '{$groupFormat}') as label, COUNT(*) as total")
                ->when($startDate, fn($q) => $q->whereDate('created_at', '>=', $startDate))
                ->whereNull('deleted_at')
                ->groupBy('label')->orderBy('label')
                ->pluck('total', 'label')->toArray();

            foreach ($evViewRows as $label => $count) {
                $pvRows[$label] = ($pvRows[$label] ?? 0) + $count;
            }
            ksort($pvRows);
            $viewData = $pvRows;

            $rawViewsTotal = (int) array_sum($viewData);
            $targetViewsTotal = $this->boostedTargetTotal($rawViewsTotal, 'views');
            $viewData = $this->injectSpikeBoost(
                $viewData,
                max(0, $targetViewsTotal - $rawViewsTotal),
                "chart_views_{$period}"
            );
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

            $rawPlaysTotal = (int) array_sum($playData);
            $targetPlaysTotal = $this->boostedTargetTotal($rawPlaysTotal, 'plays');
            $playData = $this->injectSpikeBoost(
                $playData,
                max(0, $targetPlaysTotal - $rawPlaysTotal),
                "chart_plays_{$period}"
            );
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

            // Get summed boosts (plays + views)
            $boostSums = ContentBoost::where('content_type', $row->content_type)
                ->where('content_id', $row->content_id)
                ->selectRaw('SUM(boost_plays) as boost_plays, SUM(boost_views) as boost_views')
                ->first();

            $boostPlays = (int) ($boostSums->boost_plays ?? 0);
            $boostViews = (int) ($boostSums->boost_views ?? 0);

            // real plays and real views
            $realPlays = (int) $row->total;
            $realViews = DB::table('stat_page_views')
                ->where('content_type', $row->content_type)
                ->where('content_id', $row->content_id)
                ->count();

            // Display views = real page views + boost views
            $displayViews = $realViews + $boostViews;

            return [
                'content_type'  => $row->content_type,
                'content_id'    => $row->content_id,
                'name'          => $name,
                'url'           => $url,
                'real_plays'    => $realPlays,
                'boost_plays'   => $boostPlays,
                'real_views'    => $realViews,
                'boost_views'   => $boostViews,
                'display_views' => $displayViews,
                'stream_count'  => 0,
                'total'         => $displayViews,
            ];
        })->keyBy(fn($r) => $r['content_type'] . '_' . $r['content_id']);

        // Inject boosted items that have no real play data at all (e.g. Live TV never in ev/sp)
        $allBoosts = ContentBoost::selectRaw('content_type, content_id, SUM(boost_plays) as total_plays')
            ->when($contentType !== 'all', fn($q) => $q->where('content_type', $contentType))
            ->groupBy('content_type', 'content_id')
            ->having('total_plays', '>', 0)
            ->get();

        foreach ($allBoosts as $boost) {
            $key = $boost->content_type . '_' . $boost->content_id;
            if (!$results->has($key)) {
                [$name, $url] = $this->resolveContentInfo($boost->content_type, $boost->content_id);
                $boostPlays = (int) $boost->total_plays;
                $realViews = DB::table('stat_page_views')
                    ->where('content_type', $boost->content_type)
                    ->where('content_id', $boost->content_id)
                    ->count();
                $boostViews = 0; // unknown from this aggregated row
                $displayViews = $realViews + $boostViews;

                $results->put($key, [
                    'content_type'  => $boost->content_type,
                    'content_id'    => $boost->content_id,
                    'name'          => $name ?: ($boost->content_name ?? "#{$boost->content_id}"),
                    'url'           => $url,
                    'real_plays'    => 0,
                    'boost_plays'   => $boostPlays,
                    'real_views'    => $realViews,
                    'boost_views'   => $boostViews,
                    'display_views' => $displayViews,
                    'stream_count'  => 0,
                    'total'         => $displayViews,
                ]);
            }
        }

        return response()->json($results->sortByDesc('total')->values()->take($limit));
    }

    /**
     * AJAX: Device breakdown (from stat_play_events).
     */
    public function devices(Request $request)
    {
        $period = $request->input('period', 'month');
        [$startDate] = $this->resolvePeriod($period);

        $spData = DB::table('stat_play_events')
            ->selectRaw('COALESCE(device_type, "unknown") as device, COUNT(*) as total')
            ->when($startDate, fn($q) => $q->where('play_date', '>=', $startDate))
            ->groupBy('device')
            ->orderByDesc('total')
            ->pluck('total', 'device')->toArray();

        // Merge entertainment_views (no device info → count as 'web')
        $evCount = DB::table('entertainment_views')
            ->when($startDate, fn($q) => $q->whereDate('created_at', '>=', $startDate))
            ->whereNull('deleted_at')->count();
        if ($evCount > 0) {
            $spData['web'] = ($spData['web'] ?? 0) + $evCount;
        }

        $rawTotal = (int) array_sum($spData);
        $targetTotal = $this->boostedTargetTotal($rawTotal, 'plays');
        $spData = $this->injectSpikeBoost(
            $spData,
            max(0, $targetTotal - $rawTotal),
            "devices_{$period}",
            ['web' => 1.3, 'mobile' => 1.15, 'tv' => 1.1]
        );

        arsort($spData);
        if (empty($spData)) {
            return response()->json(['labels' => ['No data yet'], 'values' => [1]]);
        }

        return response()->json(['labels' => array_keys($spData), 'values' => array_values($spData)]);
    }

    /**
     * AJAX: Country breakdown.
     */
    public function countries(Request $request)
    {
        $period = $request->input('period', 'month');
        $limit  = min((int) $request->input('limit', 10), 50);
        [$startDate] = $this->resolvePeriod($period);

        $spData = DB::table('stat_play_events')
            ->selectRaw('COALESCE(country_code, "Unknown") as country, COUNT(*) as total')
            ->when($startDate, fn($q) => $q->where('play_date', '>=', $startDate))
            ->groupBy('country')
            ->orderByDesc('total')
            ->pluck('total', 'country')->toArray();

        // Merge entertainment_views without country info as 'Unknown'
        $evCount = DB::table('entertainment_views')
            ->when($startDate, fn($q) => $q->whereDate('created_at', '>=', $startDate))
            ->whereNull('deleted_at')->count();
        if ($evCount > 0) {
            $spData['Unknown'] = ($spData['Unknown'] ?? 0) + $evCount;
        }

        $rawTotal = (int) array_sum($spData);
        $targetTotal = $this->boostedTargetTotal($rawTotal, 'plays');
        $spData = $this->injectSpikeBoost(
            $spData,
            max(0, $targetTotal - $rawTotal),
            "countries_{$period}",
            [
                'UNKNOWN' => 0.8,
                'BD' => 0.25,
                'BANGLADESH' => 0.25,
                'US' => 1.8,
                'USA' => 1.8,
                'UNITED STATES' => 1.8,
            ]
        );

        arsort($spData);
        $spData = array_slice($spData, 0, $limit, true);

        if (empty($spData)) {
            return response()->json(['labels' => ['No data yet'], 'values' => [1]]);
        }

        return response()->json(['labels' => array_keys($spData), 'values' => array_values($spData)]);
    }

    /**
     * AJAX: Platform breakdown.
     */
    public function platforms(Request $request)
    {
        $period = $request->input('period', 'month');
        [$startDate] = $this->resolvePeriod($period);

        $spData = DB::table('stat_play_events')
            ->selectRaw('COALESCE(platform, "web") as platform, COUNT(*) as total')
            ->when($startDate, fn($q) => $q->where('play_date', '>=', $startDate))
            ->groupBy('platform')
            ->orderByDesc('total')
            ->pluck('total', 'platform')->toArray();

        // Merge entertainment_views without platform info as 'web'
        $evCount = DB::table('entertainment_views')
            ->when($startDate, fn($q) => $q->whereDate('created_at', '>=', $startDate))
            ->whereNull('deleted_at')->count();
        if ($evCount > 0) {
            $spData['web'] = ($spData['web'] ?? 0) + $evCount;
        }

        $rawTotal = (int) array_sum($spData);
        $targetTotal = $this->boostedTargetTotal($rawTotal, 'plays');
        $spData = $this->injectSpikeBoost(
            $spData,
            max(0, $targetTotal - $rawTotal),
            "platforms_{$period}",
            ['web' => 1.25, 'android' => 1.15, 'ios' => 1.1]
        );

        arsort($spData);
        if (empty($spData)) {
            return response()->json(['labels' => ['No data yet'], 'values' => [1]]);
        }

        return response()->json(['labels' => array_keys($spData), 'values' => array_values($spData)]);
    }

    /**
     * AJAX: Traffic sources (from stat_page_views).
     */
    public function traffic(Request $request)
    {
        $period = $request->input('period', 'month');
        $limit  = min((int) $request->input('limit', 10), 50);
        [$startDate] = $this->resolvePeriod($period);

        $pvData = DB::table('stat_page_views')
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
            ->pluck('total', 'source')->toArray();

        // entertainment_views represent direct app plays (no referrer data)
        $evCount = DB::table('entertainment_views')
            ->when($startDate, fn($q) => $q->whereDate('created_at', '>=', $startDate))
            ->whereNull('deleted_at')->count();
        if ($evCount > 0) {
            $pvData['Direct'] = ($pvData['Direct'] ?? 0) + $evCount;
        }

        $rawTotal = (int) array_sum($pvData);
        $targetTotal = $this->boostedTargetTotal($rawTotal, 'views');
        $pvData = $this->injectSpikeBoost(
            $pvData,
            max(0, $targetTotal - $rawTotal),
            "traffic_{$period}",
            ['Direct' => 1.7, 'Google' => 1.35, 'Facebook' => 1.15, 'Other' => 0.9]
        );

        arsort($pvData);
        $pvData = array_slice($pvData, 0, $limit, true);

        if (empty($pvData)) {
            return response()->json(['labels' => ['No data yet'], 'values' => [1]]);
        }

        return response()->json(['labels' => array_keys($pvData), 'values' => array_values($pvData)]);
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
                'pv.page_url',
                DB::raw('NULL as page_name'), DB::raw('NULL as route_name'),
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
     * Slim admin-only Stats Booster page (only the enable/multiplier/fixed fields).
     */
    public function boosterSettings()
    {
        $module_title  = 'Stats Booster';
        $module_name   = 'statistics';
        $module_icon   = 'ph ph-rocket-launch';
        $module_action = 'Stats Booster';

        $settings = StatSetting::all()->pluck('value', 'key');

        return view('statistics::backend.statistics.booster_settings', compact(
            'module_title', 'module_name', 'module_icon', 'module_action', 'settings'
        ));
    }

    /**
     * Save statistics settings.
     */
    public function saveSettings(Request $request)
    {
        $form = $request->input('_form', 'main');

        // Only process tracking/general keys from the main settings form
        if ($form === 'main') {
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
        }

        // Boost settings (admin-only, boost form only)
        if ($form === 'boost' && auth()->user()->hasRole('admin')) {
            StatSetting::set('boost_enabled',      $request->has('boost_enabled') ? '1' : '0');
            StatSetting::set('boost_multiplier',   max(1, min(100, (float) $request->input('boost_multiplier', 1))));
            StatSetting::set('boost_fixed_plays',  max(0, (int) $request->input('boost_fixed_plays', 0)));
            StatSetting::set('boost_fixed_views',  max(0, (int) $request->input('boost_fixed_views', 0)));
            StatSetting::set('boost_fixed_visitors', max(0, (int) $request->input('boost_fixed_visitors', 0)));
        }

        // Display controls (admin-only, main form only)
        if ($form === 'main' && auth()->user()->hasRole('admin')) {
            StatSetting::set('show_page_views', $request->has('show_page_views') ? '1' : '0');
            StatSetting::set('show_player_plays', $request->has('show_player_plays') ? '1' : '0');
            StatSetting::set('show_views_frontend', $request->has('show_views_frontend') ? '1' : '0');
            StatSetting::set('show_plays_frontend', $request->has('show_plays_frontend') ? '1' : '0');
        }

        $redirect = $form === 'boost'
            ? redirect()->back()->with('success', 'Boost settings saved.')
            : redirect()->route('backend.statistics.settings')->with('success', 'Statistics settings saved.');

        return $redirect;
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    /**
     * Apply admin boost (multiplier + fixed add) to a stat number.
     * Returns the raw integer — only works when boost is enabled.
     */
    private function applyBoost(int $value, string $metric): int
    {
        if (StatSetting::get('boost_enabled', '0') !== '1') {
            return $value;
        }

        $multiplier = max(1, (float) StatSetting::get('boost_multiplier', 1));
        $fixed = match ($metric) {
            'plays'    => (int) StatSetting::get('boost_fixed_plays', 0),
            'views'    => (int) StatSetting::get('boost_fixed_views', 0),
            'visitors' => (int) StatSetting::get('boost_fixed_visitors', 0),
            default    => 0,
        };

        return (int) round($value * $multiplier) + $fixed;
    }

    /**
     * Target total after stacking content boosts and global boost settings.
     */
    private function boostedTargetTotal(int $rawTotal, string $metric): int
    {
        $contentBoost = $metric === 'plays'
            ? (int) ContentBoost::sum('boost_plays')
            : (int) ContentBoost::sum('boost_views');

        return $this->applyBoost($rawTotal + $contentBoost, $metric);
    }

    /**
     * Inject synthetic boosted points with deterministic spikes for realistic charts/breakdowns.
     */
    private function injectSpikeBoost(array $series, int $extra, string $seed, array $labelBias = []): array
    {
        if ($extra <= 0) {
            return $series;
        }

        if (empty($series)) {
            $series[now()->toDateString()] = 0;
        }

        $weights = [];
        foreach ($series as $label => $value) {
            $numericValue = max(0, (int) $value);
            $baseWeight = max(1.0, sqrt($numericValue + 1));
            $hash = (crc32($seed . '|' . $label) % 1000) / 1000;

            $spikeFactor = 1.0;
            if ($hash >= 0.82) {
                $spikeFactor = 2.8;
            } elseif ($hash >= 0.68) {
                $spikeFactor = 1.8;
            } elseif ($hash >= 0.52) {
                $spikeFactor = 1.25;
            }

            $normalizedLabel = strtoupper(trim((string) $label));
            $bias = max(0.2, (float) ($labelBias[$label] ?? $labelBias[$normalizedLabel] ?? 1.0));
            $weights[$label] = $baseWeight * $spikeFactor * $bias;
        }

        $totalWeight = array_sum($weights);
        if ($totalWeight <= 0) {
            $totalWeight = count($weights);
            $weights = array_fill_keys(array_keys($weights), 1);
        }

        $assigned = 0;
        $remainders = [];
        foreach ($weights as $label => $weight) {
            $portion = ($extra * $weight) / $totalWeight;
            $add = (int) floor($portion);
            $series[$label] = ((int) $series[$label]) + $add;
            $assigned += $add;
            $remainders[$label] = $portion - $add;
        }

        $remaining = $extra - $assigned;
        if ($remaining > 0) {
            arsort($remainders);
            $labels = array_keys($remainders);
            $count = count($labels);

            for ($i = 0; $i < $remaining; $i++) {
                $label = $labels[$i % $count];
                $series[$label] = ((int) $series[$label]) + 1;
            }
        }

        return $series;
    }

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

    private function calculatePercentChange(int $current, int $previous, string $period): ?float
    {
        if ($period === 'all' || $previous <= 0) {
            return null;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }

    private function resolveContentName(string $contentType, int $contentId): string
    {
        return $this->resolveContentInfo($contentType, $contentId)[0];
    }

    private function resolveContentInfo(string $contentType, int $contentId): array
    {
        $tableMap = [
            'video'         => 'videos',
            'movie'         => 'videos',
            'tvshow'        => 'videos',
            'entertainment' => 'videos',  // entertainment_views.entertainment_id → videos.id
            'episode'       => 'episodes',
            'livetv'        => 'live_tv_channel',
            'livetvchannel' => 'live_tv_channel',
        ];

        $table = $tableMap[$contentType] ?? null;
        if (!$table) {
            return ["#{$contentId}", null];
        }

        $columns = in_array($contentType, ['livetv', 'livetvchannel'])
            ? ['name', 'slug']
            : ['name', 'slug', 'type'];

        $row = DB::table($table)->where('id', $contentId)->first($columns);
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
