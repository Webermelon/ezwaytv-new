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
        'username',
        'description',
        'avatar',
        'banner',
        'is_active',
    ];

    /**
     * Use username as the route key so /on-demand/{username} works.
     */
    public function getRouteKeyName(): string
    {
        return 'username';
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Auto-generate a unique username from the channel name.
     */
    public static function generateUsername(string $name, ?int $excludeId = null): string
    {
        $base = preg_replace('/[^a-z0-9]+/', '-', strtolower($name));
        $base = trim($base, '-') ?: 'channel';
        $slug = $base;
        $i = 2;
        while (static::where('username', $slug)->when($excludeId, fn($q) => $q->where('id', '!=', $excludeId))->exists()) {
            $slug = $base . '-' . $i++;
        }
        return $slug;
    }

    public function videos()
    {
        // Video model lives in the Video module
        return $this->belongsToMany(\Modules\Video\Models\Video::class, 'author_channel_video', 'author_channel_id', 'video_id')->withTimestamps();
    }
}
