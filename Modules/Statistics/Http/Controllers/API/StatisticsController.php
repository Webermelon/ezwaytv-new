<?php

namespace Modules\Statistics\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Modules\Statistics\Models\PageView;
use Modules\Statistics\Models\PlayEvent;
use Modules\Statistics\Models\StatSetting;
use Modules\Statistics\Models\ContentBoost;

class StatisticsController extends Controller
{
    /**
     * Track a page or content view.
     *
     * POST /api/statistics/track-view
     * Body: content_type, content_id, platform, session_id, page_url, referrer
     */
    public function trackView(Request $request)
    {
        $validated = $request->validate([
            'content_type' => 'nullable|string|max:50',
            'content_id'   => 'nullable|integer|min:1',
            'channel_id'   => 'nullable|integer|min:1',
            'platform'     => 'nullable|string|max:50',
            'session_id'   => 'nullable|string|max:100',
            'page_url'     => 'nullable|string|max:500',
            'referrer'     => 'nullable|string|max:1000',
            'page_name'    => 'nullable|string|max:255',
            'route_name'   => 'nullable|string|max:255',
        ]);

        if (!StatSetting::get('track_page_views', '1')) {
            return response()->json(['status' => 'disabled']);
        }

        $contentType = $this->normalizeContentType($validated['content_type'] ?? null);
        if (($validated['content_type'] ?? null) && !$contentType) {
            return response()->json(['status' => 'invalid_content_type'], 422);
        }

        $ip = $this->getClientIp($request);

        // Respect excluded IPs
        if ($this->isExcludedIp($ip)) {
            return response()->json(['status' => 'excluded']);
        }

        // Skip bots
        if (StatSetting::get('exclude_bots', '1') && $this->isBot($request->userAgent())) {
            return response()->json(['status' => 'bot']);
        }

        // Skip guests if not tracking them
        if (!StatSetting::get('track_guests', '1') && !$request->user()) {
            return response()->json(['status' => 'guest_tracking_disabled']);
        }

        $ua = $this->parseUserAgent($request->userAgent() ?? '');

        PageView::create([
            'content_type' => $contentType,
            'content_id'   => $validated['content_id'] ?? null,
            'channel_id'   => $validated['channel_id'] ?? null,
            'user_id'      => optional($request->user())->id,
            'ip_address'   => $ip,
            'country_code' => $this->resolveCountry($ip),
            'device_type'  => $ua['device_type'],
            'browser'      => $ua['browser'],
            'os'           => $ua['os'],
            'platform'     => $validated['platform'] ?? $ua['platform'],
            'referrer'     => $validated['referrer'] ?? null,
            'page_url'     => $validated['page_url'] ?? null,
            'page_name'    => $validated['page_name'] ?? null,
            'route_name'   => $validated['route_name'] ?? null,
            'session_id'   => $validated['session_id'] ?? null,
            'view_date'    => now()->toDateString(),
        ]);

        return response()->json(['status' => 'ok']);
    }

    /**
     * Track a play event (user pressed play).
     *
     * POST /api/statistics/track-play
     * Body: content_type, content_id, platform, quality, session_id
     */
    public function trackPlay(Request $request)
    {
        $validated = $request->validate([
            'content_type' => 'required|string|max:50',
            'content_id'   => 'required|integer|min:1',
            'channel_id'   => 'nullable|integer|min:1',
            'platform'     => 'nullable|string|max:50',
            'quality'      => 'nullable|string|max:20',
            'session_id'   => 'nullable|string|max:100',
        ]);

        if (!StatSetting::get('track_play_events', '1')) {
            return response()->json(['status' => 'disabled']);
        }

        $contentType = $this->normalizeContentType($validated['content_type'] ?? null);
        if (!$contentType) {
            return response()->json(['status' => 'invalid_content_type'], 422);
        }

        $ip = $this->getClientIp($request);

        if ($this->isExcludedIp($ip)) {
            return response()->json(['status' => 'excluded']);
        }

        if (StatSetting::get('exclude_bots', '1') && $this->isBot($request->userAgent())) {
            return response()->json(['status' => 'bot']);
        }

        $ua = $this->parseUserAgent($request->userAgent() ?? '');

        $event = PlayEvent::create([
            'content_type' => $contentType,
            'content_id'   => $validated['content_id'],
            'channel_id'   => $validated['channel_id'] ?? null,
            'user_id'      => optional($request->user())->id,
            'ip_address'   => $ip,
            'country_code' => $this->resolveCountry($ip),
            'device_type'  => $ua['device_type'],
            'platform'     => $validated['platform'] ?? $ua['platform'],
            'watch_seconds' => 0,
            'quality'      => $validated['quality'] ?? null,
            'session_id'   => $validated['session_id'] ?? null,
            'play_date'    => now()->toDateString(),
        ]);

        return response()->json(['status' => 'ok', 'play_id' => $event->id]);
    }

    /**
     * Update watch time for an existing play event.
     *
     * POST /api/statistics/update-watch-time
     * Body: play_id, watch_seconds
     */
    public function updateWatchTime(Request $request)
    {
        $validated = $request->validate([
            'play_id' => 'required|integer|min:1',
            'watch_seconds' => 'required|integer|min:1|max:86400',
        ]);

        if (!StatSetting::get('track_watch_time', '1')) {
            return response()->json(['status' => 'disabled']);
        }

        $playId = $validated['play_id'];
        $seconds = $validated['watch_seconds'];

        if (!$playId || $seconds <= 0) {
            return response()->json(['status' => 'invalid'], 422);
        }

        // Only update if the play event exists and belongs to this session/user/IP
        $query = PlayEvent::where('id', $playId);

        if ($request->user()) {
            $query->where('user_id', $request->user()->id);
        } else {
            $query->where('ip_address', $this->getClientIp($request));
        }

        $updated = $query->update(['watch_seconds' => $seconds]);

        return response()->json(['status' => $updated ? 'ok' : 'not_found']);
    }

    // ──────────────────────────────────────────────────────

    private function getClientIp(Request $request): string
    {
        return (string) $request->ip();
    }

    private function normalizeContentType(?string $type): ?string
    {
        if (!$type) {
            return null;
        }

        $type = strtolower(trim($type));
        $aliases = [
            'live_tv' => 'livetv',
            'livetvchannel' => 'livetv',
            'on_demand' => 'ondemand_channel',
            'ondemand' => 'ondemand_channel',
            'ondemandchannel' => 'ondemand_channel',
            'ondemand-video' => 'ondemand_video',
            'ondemand_video' => 'ondemand_video',
        ];

        $type = $aliases[$type] ?? $type;
        $allowed = ['page', 'video', 'movie', 'tvshow', 'entertainment', 'episode', 'livetv', 'ondemand_channel', 'ondemand_video'];

        return in_array($type, $allowed, true) ? $type : null;
    }

    private function isExcludedIp(string $ip): bool
    {
        $excluded = StatSetting::get('exclude_ips', '');
        if (empty($excluded)) {
            return false;
        }
        $list = array_map('trim', explode("\n", $excluded));
        return in_array($ip, $list, true);
    }

    private function isBot(?string $userAgent): bool
    {
        if (!$userAgent) {
            return false;
        }
        $bots = ['bot', 'crawler', 'spider', 'scraper', 'curl', 'wget', 'python', 'Go-http-client', 'okhttp'];
        foreach ($bots as $bot) {
            if (stripos($userAgent, $bot) !== false) {
                return true;
            }
        }
        return false;
    }

    private function parseUserAgent(string $ua): array
    {
        // Device type detection
        $deviceType = 'desktop';
        if (preg_match('/TV|SmartTV|SMART-TV|Tizen|WebOS|BRAVIA|HbbTV/i', $ua)) {
            $deviceType = 'tv';
        } elseif (preg_match('/Mobile|Android|iPhone|iPod/i', $ua)) {
            $deviceType = 'mobile';
        } elseif (preg_match('/Tablet|iPad/i', $ua)) {
            $deviceType = 'tablet';
        }

        // Browser detection
        $browser = 'Unknown';
        if (preg_match('/Chrome\/(\S+)/i', $ua, $m)) {
            $browser = 'Chrome';
        } elseif (preg_match('/Firefox\/(\S+)/i', $ua, $m)) {
            $browser = 'Firefox';
        } elseif (preg_match('/Safari\/(\S+)/i', $ua, $m)) {
            $browser = 'Safari';
        } elseif (preg_match('/Edge\/(\S+)/i', $ua, $m)) {
            $browser = 'Edge';
        } elseif (preg_match('/MSIE|Trident/i', $ua)) {
            $browser = 'IE';
        }

        // OS detection
        $os = 'Unknown';
        if (preg_match('/Windows/i', $ua)) {
            $os = 'Windows';
        } elseif (preg_match('/Android/i', $ua)) {
            $os = 'Android';
        } elseif (preg_match('/iPhone|iPad|iPod/i', $ua)) {
            $os = 'iOS';
        } elseif (preg_match('/Macintosh|Mac OS/i', $ua)) {
            $os = 'macOS';
        } elseif (preg_match('/Linux/i', $ua)) {
            $os = 'Linux';
        }

        // Platform guess from UA
        $platform = 'web';
        if (preg_match('/EzwayAndroid|okhttp/i', $ua)) {
            $platform = 'android';
        } elseif (preg_match('/EzwayiOS|Darwin/i', $ua)) {
            $platform = 'ios';
        } elseif ($deviceType === 'tv') {
            $platform = 'tv';
        }

        return [
            'device_type' => $deviceType,
            'browser'     => $browser,
            'os'          => $os,
            'platform'    => $platform,
        ];
    }

    private function resolveCountry(string $ip): ?string
    {
        // Localhost/private IPs → no country
        if (in_array($ip, ['127.0.0.1', '::1'], true) || filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return null;
        }

        // Use ip-api.com free service for country resolution
        // In production you'd use a local MaxMind DB for speed
        try {
            $context = stream_context_create(['http' => ['timeout' => 2]]);
            return Cache::remember('stat_country_' . md5($ip), now()->addDays(7), function () use ($ip, $context) {
                $response = @file_get_contents("http://ip-api.com/json/{$ip}?fields=countryCode", false, $context);
                if ($response) {
                    $data = json_decode($response, true);
                    return $data['countryCode'] ?? null;
                }

                return null;
            });
        } catch (\Throwable) {
            // silent fail
        }

        return null;
    }

    /**
     * GET /api/statistics/content-stats?content_type=video&content_id=123
     * Public: returns real plays/views and summed boost totals and display flags.
     */
    public function contentStats(Request $request)
    {
        $type = $request->input('content_type');
        $id   = (int) $request->input('content_id');

        if (!$type || !$id) {
            return response()->json(['error' => 'Missing params'], 422);
        }

        $type = $this->normalizeContentType($type);
        if (!$type) {
            return response()->json(['error' => 'Invalid content type'], 422);
        }

        // Real counts
        $realPlays = in_array($type, ['entertainment', 'video', 'movie', 'tvshow'], true)
            ? DB::table('entertainment_views')
                ->where('entertainment_id', $id)
                ->whereNull('deleted_at')
                ->count()
            : 0;

        $realPlays += DB::table('stat_play_events')
            ->where('content_type', $type)
            ->where('content_id', $id)
            ->count();

        $realViews = DB::table('stat_page_views')
            ->where('content_type', $type)
            ->where('content_id', $id)
            ->count();

        // Boost sums
        $boostPlays = (int) ContentBoost::where('content_type', $type)->where('content_id', $id)->sum('boost_plays');
        $boostViews = (int) ContentBoost::where('content_type', $type)->where('content_id', $id)->sum('boost_views');
        $engagementViews = (int) $realViews + (int) $realPlays;

        // Display flags and modes
        $showPageViews = StatSetting::get('show_page_views', '1') === '1';
        $showPlayerPlays = StatSetting::get('show_player_plays', '1') === '1';
        $viewsMode = $this->displayModeFor('views', $type, $id);
        $playsMode = $this->displayModeFor('plays', $type, $id);
        $showPlayerViews = StatSetting::get("show_player_views:{$type}:{$id}", '1') === '1';
        $showViewsFrontend = StatSetting::get('show_views_frontend', '1') === '1' && $showPlayerViews && $viewsMode !== 'hidden';
        $showPlaysFrontend = StatSetting::get('show_plays_frontend', '1') === '1' && $playsMode !== 'hidden';
        $displayViews = $this->displayCount($viewsMode, $engagementViews, $boostViews);
        $displayPlays = $this->displayCount($playsMode, (int) $realPlays, $boostPlays);

        return response()->json([
            'real_plays' => (int) $realPlays,
            'real_views' => (int) $realViews,
            'engagement_views' => $engagementViews,
            'boost_plays' => $boostPlays,
            'boost_views' => $boostViews,
            'display_plays' => $displayPlays,
            'display_views' => $displayViews,
            'total_plays' => $displayPlays,
            'total_views' => $displayViews,
            'views_display_mode' => $viewsMode,
            'plays_display_mode' => $playsMode,
            'show_player_views' => $showPlayerViews,
            'show_page_views' => $showPageViews,
            'show_player_plays' => $showPlayerPlays,
            'show_views_frontend' => $showViewsFrontend,
            'show_plays_frontend' => $showPlaysFrontend,
        ]);
    }

    private function displayModeFor(string $metric, string $type, int $id): string
    {
        $allowed = ['combined', 'real', 'boosted', 'hidden'];
        $override = StatSetting::get("{$metric}_display_mode:{$type}:{$id}");
        $mode = in_array($override, $allowed, true)
            ? $override
            : StatSetting::get("{$metric}_display_mode", 'combined');

        return in_array($mode, $allowed, true) ? $mode : 'combined';
    }

    private function displayCount(string $mode, int $real, int $boost): int
    {
        return match ($mode) {
            'real' => $real,
            'boosted' => $boost,
            'hidden' => 0,
            default => $real + $boost,
        };
    }
}
