<?php

namespace Modules\Statistics\Models;

use Illuminate\Database\Eloquent\Model;

class AppAnalyticsInstallation extends Model
{
    protected $table = 'app_analytics_installations';

    protected $fillable = [
        'installation_id', 'user_id', 'platform', 'source', 'installed_at', 'first_opened_at', 'last_opened_at',
    ];

    protected $casts = [
        'installed_at' => 'datetime',
        'first_opened_at' => 'datetime',
        'last_opened_at' => 'datetime',
    ];
}
