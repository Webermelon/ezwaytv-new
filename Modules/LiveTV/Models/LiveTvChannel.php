<?php

namespace Modules\LiveTV\Models;

use App\Models\BaseModel;
use App\Models\AuthorChannel;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\LiveTV\Models\TvChannelStreamContentMapping;
use Modules\Subscriptions\Models\Plan;
use Modules\LiveTV\Models\LiveTvChatMessage;

class LiveTvChannel extends BaseModel
{

    use SoftDeletes;

    private const FEATURED_CHANNEL_SLUG_GROUPS = [
        ['ezway-tv'],
        ['music-channel', 'ezway-music-channel', 'ezway-music'],
        ['xo-tv'],
        ['xpn-tv'],
        ['bill-duke-tv'],
        ['kate-linder-tv'],
        ['movie-channel'],
        ['podstream-tv'],
        ['the-womens-channel'],
        ['patrion-tv', 'patrion'],
    ];

    /**
     * The attributes that are mass assignable.
     */
    protected $table = 'live_tv_channel';
    protected $fillable = [
        'name','slug','category_id','poster_url','thumb_url','access','plan_id','description','status','poster_tv_url','enable_live_chat','channel_number','vod_channel_id','vod_channel_button_name'
    ];

    protected $casts = [
        'status' => 'boolean',
        'enable_live_chat' => 'boolean',
    ];
    // protected $appends = ['poster_url'];

    public function getBaseUrlAttribute($value)
    {
        return !empty($value) ? setBaseUrlWithFileNameV2() : NULL;
    }

    public function TvChannelStreamContentMappings()
    {
        return $this->hasOne(TvChannelStreamContentMapping::class,'tv_channel_id','id')
            ->orderByRaw("CASE WHEN api_key IS NOT NULL AND api_key <> '' THEN 0 ELSE 1 END")
            ->orderByRaw("CASE WHEN server_url IS NOT NULL AND server_url <> '' THEN 0 ELSE 1 END")
            ->orderBy('id');
    }
    protected static function boot()
    {
        parent::boot();
        static::creating(function ($liveTvChannel) {
            if (empty($liveTvChannel->slug) && !empty($liveTvChannel->name)) {
                $liveTvChannel->slug = \Illuminate\Support\Str::slug(trim($liveTvChannel->name));
            }

            if (empty($liveTvChannel->channel_number)) {
                $liveTvChannel->channel_number = ((int) static::withTrashed()->max('channel_number')) + 1;
            }
        });

        static::updating(function ($liveTvChannel) {
            if ($liveTvChannel->isDirty('name') && !empty($liveTvChannel->name)) {
                $liveTvChannel->slug = \Illuminate\Support\Str::slug(trim($liveTvChannel->name));
            }
            
            // Clear LiveTV dashboard cache when status or deleted_at changes
            if ($liveTvChannel->isDirty('status') || $liveTvChannel->isDirty('deleted_at')) {
                if (function_exists('clearLiveTvDashboardCache')) {
                    clearLiveTvDashboardCache();
                }
            }
        });

        static::deleting(function ($liveTvChannel) {
            // Clear LiveTV dashboard cache when channel is soft deleted
            if (function_exists('clearLiveTvDashboardCache')) {
                clearLiveTvDashboardCache();
            }
        });

        static::restoring(function ($liveTvChannel) {
            // Clear LiveTV dashboard cache when channel is restored
            if (function_exists('clearLiveTvDashboardCache')) {
                clearLiveTvDashboardCache();
            }
        });
        
        // Clear cache when channel is restored (after restore completes)
        static::restored(function ($liveTvChannel) {
            if (function_exists('clearLiveTvDashboardCache')) {
                clearLiveTvDashboardCache();
            }
        });
    }

    public function TvCategory()
    {
        return $this->hasOne(LiveTvCategory::class,'id','category_id');
    }

    public function plan()
    {
        return $this->hasOne(Plan::class, 'id', 'plan_id');
    }

    public function chatMessages()
    {
        return $this->hasMany(LiveTvChatMessage::class, 'live_tv_channel_id');
    }

    public function vodChannel()
    {
        return $this->belongsTo(AuthorChannel::class, 'vod_channel_id');
    }

    public function schedules()
    {
        return $this->hasMany(ChannelSchedule::class, 'live_tv_channel_id');
    }

    public function scopeFeaturedFirst($query, string $table = 'live_tv_channel')
    {
        $caseParts = [];
        $bindings = [];
        $slugExpression = "LOWER(TRIM(COALESCE(NULLIF({$table}.slug, ''), REPLACE(REPLACE({$table}.name, '''', ''), ' ', '-'))))";

        foreach (self::FEATURED_CHANNEL_SLUG_GROUPS as $index => $slugs) {
            $placeholders = implode(',', array_fill(0, count($slugs), '?'));
            $caseParts[] = "WHEN {$slugExpression} IN ({$placeholders}) THEN {$index}";
            array_push($bindings, ...$slugs);
        }

        $featuredOrder = count(self::FEATURED_CHANNEL_SLUG_GROUPS);

        return $query->orderByRaw(
            'CASE ' . implode(' ', $caseParts) . " ELSE {$featuredOrder} END",
            $bindings
        );
    }

    public static function get_top_channel($channelIdsArray)
    {
        $channelIdsArray = is_array($channelIdsArray) ? $channelIdsArray : (array) $channelIdsArray;
        $channelIdsArray = array_values(array_filter($channelIdsArray, static fn ($id) => $id !== null && $id !== ''));

        if (count($channelIdsArray) === 0) {
            return collect();
        }

        $items = LiveTvChannel::select([
            'id','name','slug','channel_number','plan_id','description','status','access','category_id','poster_url','poster_tv_url',
        ])
        ->with([
            'plan:id,level',
            'TvCategory:id,name',
            'TvChannelStreamContentMappings:id,tv_channel_id,stream_type,embedded,server_url,server_url1'
        ])
        ->whereIn('id', $channelIdsArray)
        ->where('status', 1)
        ->where('deleted_at', null)
        ->featuredFirst()
        ->get();

        return $items->map(function ($item) {
            $item->plan_level = optional($item->plan)->level;
            $item->category = optional($item->TvCategory)->name;
            $item->stream_type = optional($item->TvChannelStreamContentMappings)->stream_type;
            $item->embedded = optional($item->TvChannelStreamContentMappings)->embedded;
            $item->server_url = optional($item->TvChannelStreamContentMappings)->server_url;
            $item->server_url1 = optional($item->TvChannelStreamContentMappings)->server_url1;
            $item->base_url = $item->poster_url; // preserve alias
            return $item;
        });
    }

    public static function get_channel()
    {
        $items = LiveTvChannel::select([
            'id','category_id','name','plan_id','slug','channel_number','description','status','access','poster_url','poster_tv_url',
        ])
        ->with([
            'plan:id,level',
            'TvCategory:id,name',
            'TvChannelStreamContentMappings:id,tv_channel_id,stream_type,embedded,server_url,server_url1'
        ])
        ->where('status',1)
        ->where('deleted_at',null)
        ->featuredFirst()
        ->orderBy('updated_at', 'desc')
        ->take(6)
        ->get();

        return $items->map(function ($item) {
            $item->plan_level = optional($item->plan)->level;
            $item->category = optional($item->TvCategory)->name;
            $item->stream_type = optional($item->TvChannelStreamContentMappings)->stream_type;
            $item->embedded = optional($item->TvChannelStreamContentMappings)->embedded;
            $item->server_url = optional($item->TvChannelStreamContentMappings)->server_url;
            $item->server_url1 = optional($item->TvChannelStreamContentMappings)->server_url1;
            $item->base_url = $item->poster_url;
            return $item;
        });
    }

    public static function get_tvChannels_catgory_wise($category)
    {
        $items = LiveTvChannel::select([
            'id','name','channel_number','plan_id','description','status','access','category_id','poster_url','poster_tv_url'
        ])
        ->with([
            'plan:id,level',
            'TvCategory:id,name',
            'TvChannelStreamContentMappings:id,tv_channel_id,stream_type,embedded,server_url,server_url1'
        ])
        ->where('category_id',$category)
        ->where('status',1)
        ->featuredFirst()
        ->orderBy('updated_at', 'desc')
        ->get();

        return $items->map(function ($item) {
            $item->plan_level = optional($item->plan)->level;
            $item->category = optional($item->TvCategory)->name;
            $item->stream_type = optional($item->TvChannelStreamContentMappings)->stream_type;
            $item->embedded = optional($item->TvChannelStreamContentMappings)->embedded;
            $item->server_url = optional($item->TvChannelStreamContentMappings)->server_url;
            $item->server_url1 = optional($item->TvChannelStreamContentMappings)->server_url1;
            $item->base_url = $item->poster_url;
            return $item;
        });
    }
}
