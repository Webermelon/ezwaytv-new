<?php

namespace Modules\Statistics\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Statistics\Models\PageView;
use Modules\Statistics\Models\PlayEvent;
use Modules\Statistics\Models\StatSetting;

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
        if (!StatSetting::get('track_page_views', '1')) {
            return response()->json(['status' => 'disabled']);
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
            'content_type' => $request->input('content_type'),
            'content_id'   => $request->input('content_id'),
            'user_id'      => optional($request->user())->id,
            'ip_address'   => $ip,
            'country_code' => $this->resolveCountry($ip),
            'device_type'  => $ua['device_type'],
            'browser'      => $ua['browser'],
            'os'           => $ua['os'],
            'platform'     => $request->input('platform', $ua['platform']),
            'referrer'     => $request->input('referrer'),
            'page_url'     => $request->input('page_url'),
            'page_name'    => $request->input('page_name'),
            'route_name'   => $request->input('route_name'),
            'session_id'   => $request->input('session_id'),
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
        if (!StatSetting::get('track_play_events', '1')) {
            return response()->json(['status' => 'disabled']);
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
            'content_type' => $request->input('content_type'),
            'content_id'   => $request->input('content_id'),
            'user_id'      => optional($request->user())->id,
            'ip_address'   => $ip,
            'country_code' => $this->resolveCountry($ip),
            'device_type'  => $ua['device_type'],
            'platform'     => $request->input('platform', $ua['platform']),
            'watch_seconds' => 0,
            'quality'      => $request->input('quality'),
            'session_id'   => $request->input('session_id'),
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
        if (!StatSetting::get('track_watch_time', '1')) {
            return response()->json(['status' => 'disabled']);
        }

        $playId = $request->input('play_id');
        $seconds = (int) $request->input('watch_seconds', 0);

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
        $ip = $request->header('X-Forwarded-For') ?? $request->ip();
        // Take first IP if comma-separated
        return trim(explode(',', $ip)[0]);
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
            $response = @file_get_contents("http://ip-api.com/json/{$ip}?fields=countryCode", false, $context);
            if ($response) {
                $data = json_decode($response, true);
                return $data['countryCode'] ?? null;
            }
        } catch (\Throwable) {
            // silent fail
        }

        return null;
    }
}
