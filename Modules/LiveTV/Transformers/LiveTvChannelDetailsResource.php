<?php

namespace Modules\LiveTV\Transformers;

use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Subscriptions\Transformers\PlanResource;
use Modules\Subscriptions\Models\Plan;
use Modules\LiveTV\Models\LiveTvChannel;
use Modules\LiveTV\Transformers\Backend\LiveTvChannelResourceV3;

class LiveTvChannelDetailsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray($request): array
    {
        $plans = [];
        $plan = $this->plan;
        if($plan){
            $plans = Plan::where('level', '<=', $plan->level)->get();
        }
        $moreItems = LiveTvChannel::where('category_id', $this->category_id)->get()->except($this->id);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'access' => $this->access,
            'plan_id' => $this->plan_id,
            'plan_level' => $this->plan->level ?? 0,
            'description' => strip_tags($this->description),
            'poster_image' => setBaseUrlWithFileName($this->poster_url, 'image', 'livetv'),
            'category' => optional($this->TvCategory)->name ?? null,
            'stream_type' => optional($this->TvChannelStreamContentMappings)->stream_type ?? null,
            'embedded' => optional($this->TvChannelStreamContentMappings)->embedded ?? null,
            'server_url' => optional($this->TvChannelStreamContentMappings)->server_url ?? null,
            'server_url1' => optional($this->TvChannelStreamContentMappings)->server_url1 ?? null,
            'plans' => PlanResource::collection($plans),
            'more_items' => LiveTvChannelResourceV3::collection($moreItems),
            'status' => $this->status,
            'enable_live_chat' => (bool) $this->enable_live_chat,
            'poster_tv_image' => setBaseUrlWithFileName($this->poster_tv_url, 'image', 'livetv'),
            'thumbnail_image' => $this->thumb_url != null ? setBaseUrlWithFileName($this->thumb_url, 'image', 'livetv') : setBaseUrlWithFileName($this->poster_url, 'image', 'livetv'),
            'schedules' => $this->whenLoaded('schedules') ? $this->schedules->map(function($s){
                return [
                    'id' => $s->id,
                    'title' => $s->title,
                    'start_at' => optional($s->start_at)->toIso8601String(),
                    'end_at' => optional($s->end_at)->toIso8601String(),
                    'meta' => $s->meta ? json_decode($s->meta, true) : null,
                ];
            })->toArray() : $this->schedules()->orderBy('start_at')->get()->map(function($s){
                return [
                    'id' => $s->id,
                    'title' => $s->title,
                    'start_at' => optional($s->start_at)->toIso8601String(),
                    'end_at' => optional($s->end_at)->toIso8601String(),
                    'meta' => $s->meta ? json_decode($s->meta, true) : null,
                ];
            })->toArray(),
            // expose api key (if present) so frontend can fetch external schedules
            'schedules_api_key' => (function(){
                $mapping = optional($this->TvChannelStreamContentMappings);
                $key = $mapping->api_key ?? null;
                if(empty($key)){
                    $candidates = [
                        $mapping->server_url ?? null,
                        $mapping->server_url1 ?? null,
                    ];
                    foreach($candidates as $url){
                        if(empty($url)) continue;
                        if(preg_match('/([a-f0-9]{32})/i', $url, $m)){
                            $key = $m[1];
                            break;
                        }
                    }
                }
                return $key ?? null;
            })(),
        ];
    }
}
