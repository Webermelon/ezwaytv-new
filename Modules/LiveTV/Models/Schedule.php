<?php

namespace Modules\LiveTV\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Schedule extends BaseModel
{
    use HasFactory;

    protected $table = 'livetv_schedules';
    protected $fillable = ['livetv_id', 'title', 'start_at', 'end_at', 'meta'];

    protected $casts = [
        'start_at' => 'datetime',
        'end_at' => 'datetime',
    ];

    public function livetv()
    {
        return $this->belongsTo(LiveTV::class, 'livetv_id');
    }
}
