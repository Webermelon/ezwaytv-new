<?php

namespace Modules\Video\Transformers;

use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Subscriptions\Transformers\PlanResource;
use Modules\Entertainment\Models\Watchlist;
use Modules\Subscriptions\Models\Plan;
use Modules\Entertainment\Models\Entertainment;

class VideoResourceV3 extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray($request): array
    {
         return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'poster_image' => setBaseUrlWithFileName($this->poster_image,'image','video'),
            'poster_tv_image' => setBaseUrlWithFileName($this->poster_tv_url,'image','video'),
            'trailer_url_type' => $this->trailer_url_type,
            'trailer_url' => $this->trailer_url_type == 'Local'
                ? setBaseUrlWithFileName($this->trailer_url, 'video', 'video')
                : $this->trailer_url,
            'video_upload_type' => $this->video_upload_type,
            'video_url_input' => $this->video_upload_type == 'Local'
                ? setBaseUrlWithFileName($this->video_url_input, 'video', 'video')
                : $this->video_url_input,
            'duration' => $this->duration,
            'details'=>[
                'name' => $this->name,
                'slug' => $this->slug,
                'type' => $this->type ?? 'video',
                'release_date' => $this->release_date ? formatDate($this->release_date):null,
                'access' => $this->access,
                'is_restricted' => $this->is_restricted,
                'is_device_supported'=> $this->isDeviceSupported,
                "has_content_access"=> $this->has_content_access ?? 0,
                "required_plan_level"=>$this->required_plan_level,
                'is_restricted' => $this->is_restricted ?? 0,
            ]
        ];
    }
}
