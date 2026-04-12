<?php

namespace Modules\LiveTV\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ChannelSchedule extends BaseModel
{
    use HasFactory;

    protected $table = 'live_tv_channel_schedules';
    protected $fillable = ['live_tv_channel_id', 'title', 'start_at', 'end_at', 'meta'];

    protected $casts = [
        'start_at' => 'datetime',
        'end_at' => 'datetime',
    ];

    public function channel()
    {
        return $this->belongsTo(LiveTvChannel::class, 'live_tv_channel_id');
    }
}
