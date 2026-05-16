<?php

namespace Modules\LiveTV\Transformers;

use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Subscriptions\Transformers\PlanResource;
use Modules\Subscriptions\Models\Plan;
use Modules\LiveTV\Models\LiveTvChannel;
use Modules\LiveTV\Transformers\LiveTvChannelResource;
use Illuminate\Support\Facades\Http;

class LiveTvChannelDetailsResourceV3 extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray($request): array
    {
        $schedulesUrl = optional($this->TvChannelStreamContentMappings)->api_key ?? null;
        $nowPlaying   = null;
        $nextPlaying  = null;

        if ($schedulesUrl) {
            [$nowPlaying, $nextPlaying] = $this->resolveNowAndNext($schedulesUrl);
        }

        return [
            'id' => $this->id,
            'details' => [
                'name' => $this->name,
                'type' => 'livetv',
                'server_url'=> optional($this->TvChannelStreamContentMappings)->server_url ?? null,
                'access' => $this->access,
                'is_device_supported'=> $this->isDeviceSupported ?? null,
                "has_content_access"=> $this->has_content_access,
                "required_plan_level"=> $this->required_plan_level ?? 0,
                'description' => strip_tags($this->description),
                "thumbnail_image" => setBaseUrlWithFileName($this->poster_url,'image','livetv'),
                'category' => $this->TvCategory->name ?? null,
            ],
            'video_qualities'  => $this->video_qualities,
            'schedules_url'    => $schedulesUrl,
            'now_playing'      => $nowPlaying,
            'next_playing'     => $nextPlaying,
            'suggested_content'=> LiveTvChannelResourceV3::collection($this->moreItems),
        ];
    }

    private function resolveNowAndNext(string $url): array
    {
        try {
            $response = Http::timeout(5)->get($url);
            if (!$response->successful()) {
                return [null, null];
            }

            $schedules = $response->json('schedules', []);
            if (empty($schedules)) {
                return [null, null];
            }

            $now = now()->utc()->timestamp;
            $nowPlaying  = null;
            $nextPlaying = null;

            foreach ($schedules as $index => $item) {
                $start = strtotime($item['start_time'] ?? '');
                $end   = strtotime($item['end_time']   ?? '');
                if (!$start || !$end) continue;

                if ($now >= $start && $now <= $end) {
                    $nowPlaying = [
                        'title'      => $item['media']['title'] ?? null,
                        'start_time' => $item['start_time'],
                        'end_time'   => $item['end_time'],
                        'duration_seconds' => $item['media']['duration_seconds'] ?? null,
                        'elapsed_seconds'  => $now - $start,
                    ];
                    // next item
                    if (isset($schedules[$index + 1])) {
                        $next = $schedules[$index + 1];
                        $nextPlaying = [
                            'title'      => $next['media']['title'] ?? null,
                            'start_time' => $next['start_time'],
                            'end_time'   => $next['end_time'],
                            'duration_seconds' => $next['media']['duration_seconds'] ?? null,
                        ];
                    }
                    break;
                }
            }

            return [$nowPlaying, $nextPlaying];
        } catch (\Throwable $e) {
            return [null, null];
        }
    }
}
