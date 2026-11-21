<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class VerifyVodRequest
{
    public function handle(Request $request, Closure $next)
    {
        $secret = (string) config('vod.sync_secret');
        $time = (string) $request->header('X-Vod-Time');
        $nonce = (string) $request->header('X-Vod-Nonce');
        $signature = (string) $request->header('X-Vod-Signature');
        $valid = config('vod.enabled') && strlen($secret) >= 32 && ctype_digit($time)
            && abs(time() - (int) $time) <= 300 && preg_match('/\A[a-f0-9]{32}\z/', $nonce)
            && hash_equals(hash_hmac('sha256', implode("\n", [$time, $nonce, $request->getPathInfo(), $request->getContent()]), $secret), $signature);
        abort_unless($valid, 401, 'Unauthorized VOD request.');
        abort_unless(Cache::add('vod.nonce.'.hash('sha256', $secret.$nonce), true, now()->addMinutes(6)), 401, 'VOD request was already used.');

        return $next($request);
    }
}
