<?php

namespace Modules\Statistics\Http\Controllers\API;

use App\Services\TvLog;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class PlaybackTelemetryController
{
    public function __invoke(Request $request)
    {
        $data = $request->validate([
            'playback_id' => ['required', 'regex:/\A[0-9a-f]{32}\z/'],
            'content_type' => ['required', Rule::in(['video', 'movie', 'episode', 'livetv'])],
            'content_id' => ['required', 'integer', 'between:1,9007199254740991'],
            'watched_channel_id' => ['nullable', 'integer', 'between:1,9007199254740991'],
            'player_surface' => ['required', Rule::in(['watch', 'embed'])],
            'events' => ['required', 'array', 'max:20'],
            'events.*' => ['required', 'array:event,sequence_no,watch_delta_ms,startup_ms,buffer_delta_ms,error_code'],
            'events.*.event' => ['required', Rule::in(['started', 'progress', 'buffering', 'completed', 'error'])],
            'events.*.sequence_no' => ['required', 'integer', 'distinct', 'between:1,1000000'],
            'events.*.watch_delta_ms' => ['nullable', 'integer', 'between:1,60000'],
            'events.*.buffer_delta_ms' => ['nullable', 'integer', 'between:1,600000'],
            'events.*.startup_ms' => ['nullable', 'integer', 'between:0,600000'],
            'events.*.error_code' => ['nullable', 'regex:/\Amedia_[1-9][0-9]{0,2}\z/'],
        ]);

        $metrics = ['started' => 'startup_ms', 'progress' => 'watch_delta_ms', 'buffering' => 'buffer_delta_ms', 'error' => 'error_code'];
        $live = $data['content_type'] === 'livetv';
        foreach ($data['events'] as $index => $event) {
            $name = $event['event'];
            $metric = $metrics[$name] ?? null;
            $supplied = array_intersect_key(array_filter($event, fn ($value) => $value !== null), array_flip($metrics));
            if (array_diff_key($supplied, $metric ? [$metric => true] : [])
                || ($metric && $name !== 'started' && !isset($event[$metric]))
                || ($live && $name === 'completed')) {
                throw ValidationException::withMessages(["events.$index" => 'The playback event has invalid metrics.']);
            }
        }

        foreach ($data['events'] as $event) {
            $sequence = (int) $event['sequence_no'];
            $metric = $metrics[$event['event']] ?? null;
            TvLog::record('playback_events', 'playback.qoe_'.$event['event'], [
                'playback_id' => $data['playback_id'],
                'sequence_no' => $sequence,
                'content_type' => $data['content_type'],
                'content_id' => (int) $data['content_id'],
                'channel_id' => $live ? (int) $data['content_id'] : null,
                'watched_channel_id' => $data['watched_channel_id'] ?? null,
                'stream_type' => $live ? 'live' : 'vod',
                'player_version' => 'videojs',
                'player_surface' => $data['player_surface'],
            ] + ($metric && isset($event[$metric]) ? [$metric => $event[$metric]] : []),
                $request, substr(hash('sha256', 'tv-qoe:'.$data['playback_id'].':'.$sequence), 0, 32));
        }

        return response()->noContent();
    }
}
