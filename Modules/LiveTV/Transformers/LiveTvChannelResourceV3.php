<?php

namespace Modules\LiveTV\Transformers;

use Illuminate\Http\Resources\Json\JsonResource;

class LiveTvChannelResourceV3 extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'poster_image' => $this->poster_image,
            'poster_tv_image' => setBaseUrlWithFileName($this->poster_tv_url,'image','livetv'),
            'details'=>[
                'name' => $this->name,
                'slug' => $this->slug,
                'type' => 'livetv',
                'access' => $this->access,
                'is_device_supported'=> $this->isDeviceSupported ?? null,
                "has_content_access"=> $this->has_content_access, //->access == 'free'|| $this->access == 'pay-per-view' ? 1 : ($this->plan_id ?? 0),
                "required_plan_level"=> $this->required_plan_level ?? null,
                'is_restricted' => $this->is_restricted ?? 0,
                "category"=> $this->TvCategory->name ?? null,
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

            ]
        ];
    }
}
