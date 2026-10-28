<?php

namespace Modules\Statistics\Models;

use Illuminate\Database\Eloquent\Model;

class AppAnalyticsActivity extends Model
{
    protected $table = 'app_analytics_activities';

    protected $fillable = [
        'installation_id', 'user_id', 'activity_date', 'opens', 'engaged_seconds', 'first_opened_at', 'last_activity_at',
    ];

    protected $casts = [
        'activity_date' => 'date',
        'first_opened_at' => 'datetime',
        'last_activity_at' => 'datetime',
    ];
}
