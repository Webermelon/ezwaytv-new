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
        'avatar_url',
        'message',
        'promotion_packages',
        'ip_address',
    ];

    protected $casts = [
        'promotion_packages' => 'array',
    ];

    public function channel()
    {
        return $this->belongsTo(LiveTvChannel::class, 'live_tv_channel_id');
    }

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }
}
