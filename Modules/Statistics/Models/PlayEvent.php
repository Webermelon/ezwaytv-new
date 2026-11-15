<?php

namespace Modules\Statistics\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\User;

class PlayEvent extends Model
{
    use \App\Services\UsesTvAnalyticsStorage;

    use HasFactory;

    protected $table = 'stat_play_events';

    protected static function booted(): void
    {
        static::created(function (self $play): void {
            $request = app()->bound('request') && request()->route() ? request() : null;
            \App\Services\TvLog::record('playback_events', 'playback.legacy_play', [
                'playback_id' => \App\Services\TvLog::legacyId('playback', $play->id),
                'sequence_no' => 0,
                'analytics_session_id' => \App\Services\TvLog::label($play->session_id, 96),
                'content_type' => $play->content_type,
                'content_id' => $play->content_id,
                'channel_id' => $play->channel_id,
                'quality' => \App\Services\TvLog::label($play->quality, 32),
                'details' => [
                    'legacy_play_id' => $play->id,
                    'tv_user_id' => $play->user_id,
                    'platform' => \App\Services\TvLog::label($play->platform, 32),
                    'device_type' => \App\Services\TvLog::label($play->device_type, 32),
                    'country' => $play->country_code,
                ],
            ] + ($request?->attributes->get('tv_viewing_context') ?? []),
                $request, \App\Services\TvLog::legacyId('play', $play->id));
        });
    }

    protected $fillable = [
        'content_type',
        'content_id',
        'user_id',
        'ip_address',
        'country_code',
        'device_type',
        'platform',
        'watch_seconds',
        'quality',
        'channel_id',
        'session_id',
        'play_date',
    ];

    protected $casts = [
        'play_date' => 'date',
        'watch_seconds' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
