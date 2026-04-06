<?php

namespace Modules\LiveTV\Models;

use Illuminate\Database\Eloquent\Model;

class LiveTvChatMessage extends Model
{
    protected $table = 'live_tv_chat_messages';

    protected $fillable = [
        'live_tv_channel_id',
        'user_id',
        'guest_name',
        'message',
        'ip_address',
    ];

    public function channel()
    {
        return $this->belongsTo(LiveTvChannel::class, 'live_tv_channel_id');
    }
}