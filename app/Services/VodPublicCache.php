<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class VodPublicCache
{
    public static function remember($key, $ttl, $callback)
    {
        return Cache::remember(VodPublicVisibility::cacheKey($key), $ttl, $callback);
    }

    public static function rememberForever($key, $callback)
    {
        // Entitlement versions change keys; bound abandoned catalog generations to one day.
        return self::remember($key, now()->addDay(), $callback);
    }
}
