<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class MediaCompressorJob extends Model
{
    protected $fillable = [
        'remote_job_id',
        'user_id',
        'owner_type',
        'owner_id',
        'media_type',
        'status',
        'primary_url',
        'variants',
        'payload',
        'error_message',
        'completed_at',
    ];

    protected $casts = [
        'variants' => 'array',
        'payload' => 'array',
        'completed_at' => 'datetime',
    ];

    public function owner(): MorphTo
    {
        return $this->morphTo();
    }
}
