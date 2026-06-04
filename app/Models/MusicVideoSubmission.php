<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class MusicVideoSubmission extends Model
{
    use SoftDeletes;

    public const STATUSES = [
        'submitted' => 'Submitted',
        'reviewing' => 'Reviewing',
        'scheduled' => 'Scheduled',
        'used' => 'Used',
        'rejected' => 'Rejected',
    ];

    protected $fillable = [
        'user_id',
        'channel_slug',
        'channel_name',
        'title',
        'artist_name',
        'submitter_name',
        'submitter_email',
        'submitter_phone',
        'purchase_reference',
        'purchase_confirmed_at',
        'notes',
        'poster_disk',
        'poster_path',
        'video_disk',
        'video_path',
        'video_original_name',
        'video_mime',
        'video_size',
        'status',
        'scheduled_at',
        'admin_notes',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'purchase_confirmed_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    public function getPosterUrlAttribute(): string
    {
        return Storage::disk($this->poster_disk)->url($this->poster_path);
    }

    public function getVideoUrlAttribute(): string
    {
        return Storage::disk($this->video_disk)->url($this->video_path);
    }

    public function getVideoSizeForHumansAttribute(): string
    {
        if (!$this->video_size) {
            return '-';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $size = (float) $this->video_size;
        $unit = 0;

        while ($size >= 1024 && $unit < count($units) - 1) {
            $size /= 1024;
            $unit++;
        }

        return round($size, $unit === 0 ? 0 : 1) . ' ' . $units[$unit];
    }
}
