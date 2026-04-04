<?php

namespace Modules\Statistics\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\User;

class PageView extends Model
{
    use HasFactory;

    protected $table = 'stat_page_views';

    protected $fillable = [
        'content_type',
        'content_id',
        'user_id',
        'ip_address',
        'country_code',
        'device_type',
        'browser',
        'os',
        'platform',
        'referrer',
        'page_url',
        'page_name',
        'route_name',
        'session_id',
        'view_date',
    ];

    protected $casts = [
        'view_date' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
