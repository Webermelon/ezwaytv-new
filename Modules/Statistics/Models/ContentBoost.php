<?php

namespace Modules\Statistics\Models;

use Illuminate\Database\Eloquent\Model;

class ContentBoost extends Model
{
    protected $table = 'stat_content_boosts';

    protected $fillable = [
        'content_type', 'content_id', 'content_name',
        'boost_plays', 'boost_views', 'boost_watch_seconds', 'boost_unique_visitors', 'note',
    ];

    protected $casts = [
        'boost_plays' => 'integer',
        'boost_views' => 'integer',
        'boost_watch_seconds' => 'integer',
        'boost_unique_visitors' => 'integer',
        'content_id'  => 'integer',
    ];
}
