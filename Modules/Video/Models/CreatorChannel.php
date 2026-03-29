<?php

namespace Modules\Video\Models;

use App\Models\BaseModel;
use App\Models\User;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class CreatorChannel extends BaseModel
{
    use SoftDeletes;

    protected $table = 'creator_channels';

    protected $fillable = [
        'user_id',
        'name',
        'slug',
        'description',
        'poster_url',
        'banner_url',
        'status',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (CreatorChannel $channel) {
            if (empty($channel->slug) && !empty($channel->name)) {
                $channel->slug = Str::slug(trim($channel->name));
            }
        });

        static::updating(function (CreatorChannel $channel) {
            if ($channel->isDirty('name') && !empty($channel->name)) {
                $channel->slug = Str::slug(trim($channel->name));
            }
        });
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function videos()
    {
        return $this->hasMany(Video::class, 'creator_channel_id', 'id');
    }
}
