<?php

namespace Modules\Ad\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class VideoAd extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'video_ads';

    protected $fillable = [
        'name',
        'title',
        'description',
        'video_file',
        'video_url',
        'duration',
        'click_through_url',
        'skip_offset',
        'is_skippable',
        'advertiser',
        'impression_url',
        'click_tracking_url',
        'width',
        'height',
        'mime_type',
        'status',
    ];

    protected $casts = [
        'is_skippable' => 'boolean',
        'status' => 'boolean',
    ];

    // Scope for active video ads
    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }

    // Get full URL for video
    public function getVideoUrlAttribute()
    {
        if ($this->attributes['video_url']) {
            return $this->attributes['video_url'];
        }

        if ($this->video_file) {
            // Check if video_file is already a full URL
            if (filter_var($this->video_file, FILTER_VALIDATE_URL)) {
                return $this->video_file;
            }
            // Otherwise, prepend storage URL
            return url('storage/' . $this->video_file);
        }

        return null;
    }

    // Get VAST XML URL for this ad
    public function getVastXmlUrlAttribute()
    {
        return route('api.vast-xml.generate', ['id' => $this->id]);
    }

    // Format duration in HH:MM:SS format
    public function getFormattedDurationAttribute()
    {
        if (!$this->duration) {
            return '00:00:00';
        }

        $hours = floor($this->duration / 3600);
        $minutes = floor(($this->duration % 3600) / 60);
        $seconds = $this->duration % 60;

        return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
    }
}
