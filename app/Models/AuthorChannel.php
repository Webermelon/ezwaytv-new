<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AuthorChannel extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'name',
        'description',
        'avatar',
        'banner',
        'is_active',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function videos()
    {
        // Video model lives in the Video module
        return $this->belongsToMany(\Modules\Video\Models\Video::class, 'author_channel_video', 'author_channel_id', 'video_id')->withTimestamps();
    }
}
