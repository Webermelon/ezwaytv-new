<?php

namespace Modules\Ad\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;

class AdBannerSlide extends Model
{
    use SoftDeletes;

    protected $table = 'ad_banner_slides';

    protected $fillable = [
        'title', 'image', 'link_url', 'placements', 'sort_order', 'status',
    ];

    protected $casts = [
        'placements' => 'array',
        'status'     => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }

    public function getImageProxyUrlAttribute(): string
    {
        return route('api.v3.showcase-media.image', ['slide' => $this->id]);
    }

    /**
     * Get active slides for a given placement, with caching.
     */
    public static function getActiveByPlacement(string $placement): \Illuminate\Database\Eloquent\Collection
    {
        return Cache::remember("ad_banner_slides_{$placement}", 300, function () use ($placement) {
            return self::active()
                ->where(function ($q) use ($placement) {
                    $q->whereJsonContains('placements', $placement)
                      ->orWhereJsonContains('placements', 'all');
                })
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get();
        });
    }

    public static function clearCache(): void
    {
        foreach (['home', 'tvshow', 'video', 'livetv', 'all'] as $placement) {
            Cache::forget("ad_banner_slides_{$placement}");
        }
    }
}
