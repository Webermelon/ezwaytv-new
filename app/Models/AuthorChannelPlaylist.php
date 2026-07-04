<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Video\Models\Video;

class AuthorChannelPlaylist extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'author_channel_id',
        'name',
        'description',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function channel()
    {
        return $this->belongsTo(AuthorChannel::class, 'author_channel_id');
    }

    public function videos()
    {
        return $this->belongsToMany(Video::class, 'author_channel_playlist_video', 'author_channel_playlist_id', 'video_id')
            ->withPivot('sort_order')
            ->withTimestamps()
            ->orderBy('author_channel_playlist_video.sort_order')
            ->orderBy('author_channel_playlist_video.created_at');
    }
}
