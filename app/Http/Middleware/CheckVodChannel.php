<?php

namespace App\Http\Middleware;

use App\Services\VodChannelEntitlements;
use Closure;
use Illuminate\Http\Request;

class CheckVodChannel
{
    public function handle(Request $request, Closure $next)
    {
        if (! in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            $channel = (int) $request->route('id');
            $hasTable = config('vod.enabled') || \Illuminate\Support\Facades\Schema::hasTable('vod_channel_entitlements');
            $local = $hasTable && \Illuminate\Support\Facades\DB::table('vod_channel_entitlements')->where('channel_id', $channel)->exists();
            if ($channel > 0 && (config('vod.enabled') || $local)) {
                abort_unless(\App\Models\AuthorChannel::query()->where('id', $channel)->where('user_id', $request->user()?->id)->exists(), 404);
                if ($local) {
                    app(VodChannelEntitlements::class)->assertWritable($channel);
                } else {
                    abort_unless(app(\App\Services\VodCoreCreation::class)->canPublish($request->user(), $channel), 403, 'Your membership or channel subscription does not currently include publishing access.');
                }
            }
        }

        return $next($request);
    }
}
