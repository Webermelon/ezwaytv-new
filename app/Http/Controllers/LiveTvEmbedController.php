<?php

namespace App\Http\Controllers;

use Modules\LiveTV\Models\LiveTvChannel;

class LiveTvEmbedController extends Controller
{
    public function show(string $slug)
    {
        $channel = LiveTvChannel::query()
            ->with('TvChannelStreamContentMappings')
            ->where('slug', $slug)
            ->where('status', 1)
            ->firstOrFail();

        $mapping = $channel->TvChannelStreamContentMappings;
        $streamUrl = $mapping?->server_url;

        abort_unless(is_string($streamUrl) && filter_var($streamUrl, FILTER_VALIDATE_URL), 404);

        return view('livetv::embed', [
            'title' => $channel->name,
            'streamUrl' => $streamUrl,
            'posterUrl' => $channel->poster_url ? setBaseUrlWithFileName($channel->poster_url, 'image', 'livetv') : null,
        ]);
    }
}
