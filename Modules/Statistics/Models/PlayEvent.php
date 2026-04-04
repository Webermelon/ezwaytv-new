<?php

namespace Modules\Statistics\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\User;

class PlayEvent extends Model
{
    use HasFactory;

    protected $table = 'stat_play_events';

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
