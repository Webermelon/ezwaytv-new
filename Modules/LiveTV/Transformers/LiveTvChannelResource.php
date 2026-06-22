<?php

namespace Modules\LiveTV\Transformers;

use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Subscriptions\Transformers\PlanResource;
use Modules\Subscriptions\Models\Plan;

class LiveTvChannelResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray($request): array
    {

        return [
            'id' => $this->id,
            'show_premium_badge' => $this->show_premium_badge ?? false,
            'name' => $this->name,
            'plan_id' => $this->plan_id,
            'plan_level' => $this->plan->level ?? 0,
            'slug' => $this->slug,
            'description' => strip_tags($this->description),
            'poster_image' => setBaseUrlWithFileName($this->poster_url, 'image', 'livetv'),
            'thumbnail_image' => $this->thumb_url != null ? setBaseUrlWithFileName($this->thumb_url, 'image', 'livetv') : setBaseUrlWithFileName($this->poster_url, 'image', 'livetv'),
            'category' => optional($this->TvCategory)->name ?? null,
            'stream_type' => optional($this->TvChannelStreamContentMappings)->stream_type ?? null,
            'embedded' => optional($this->TvChannelStreamContentMappings)->embedded ?? null,
            'server_url' => optional($this->TvChannelStreamContentMappings)->server_url ?? null,
            'server_url1' => optional($this->TvChannelStreamContentMappings)->server_url1 ?? null,
            'stats' => [
                'real_views' => (int) ($this->real_views ?? 0),
                'boost_views' => (int) ($this->boost_views ?? 0),
                'display_views' => (int) ($this->display_views ?? $this->total_views ?? 0),
                'total_views' => (int) ($this->total_views ?? 0),
                'real_plays' => (int) ($this->real_plays ?? 0),
                'boost_plays' => (int) ($this->boost_plays ?? 0),
                'display_plays' => (int) ($this->display_plays ?? $this->total_plays ?? 0),
                'total_plays' => (int) ($this->total_plays ?? 0),
                'views_display_mode' => $this->views_display_mode ?? 'combined',
                'plays_display_mode' => $this->plays_display_mode ?? 'combined',
                'show_views_frontend' => (bool) ($this->show_views_frontend ?? true),
                'show_plays_frontend' => (bool) ($this->show_plays_frontend ?? true),
            ],
            'status' => $this->status,
            'access'=>$this->access,
            'poster_tv_image' => setBaseUrlWithFileName($this->poster_tv_url, 'image', 'livetv'),
        ];
    }
}
