<?php

namespace Modules\LiveTV\Transformers;

use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Subscriptions\Transformers\PlanResource;
use Modules\Subscriptions\Models\Plan;
use Modules\LiveTV\Models\LiveTvChannel;
use Modules\LiveTV\Transformers\LiveTvChannelResource;
use Carbon\Carbon;
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
        $fullSchedule = [];

        if ($schedulesUrl) {
            [$nowPlaying, $nextPlaying, $fullSchedule] = $this->resolveScheduleData($schedulesUrl);
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
            'full_schedule'    => $fullSchedule,
            'suggested_content'=> LiveTvChannelResourceV3::collection($this->moreItems),
        ];
    }

    private function resolveScheduleData(string $url): array
    {
        try {
            $response = Http::timeout(5)->get($url);
            if (!$response->successful()) {
                return [null, null, []];
            }

            $schedules = $response->json('schedules', []);
            if (empty($schedules)) {
                return [null, null, []];
            }

            $now = now()->utc()->timestamp;
            $nowPlaying  = null;
            $nextPlaying = null;
            $fullSchedule = [];
            $currentIndex = null;

            foreach ($schedules as $index => $item) {
                $start = $this->scheduleTimestamp($item['start_time'] ?? null);
                $end   = $this->scheduleTimestamp($item['end_time'] ?? null);
                if (!$start || !$end) continue;

                $fullSchedule[] = [
                    'id' => $item['id'] ?? $index,
                    'title' => $item['media']['title'] ?? $item['title'] ?? null,
                    'start_time' => $item['start_time'],
                    'end_time' => $item['end_time'],
                    'duration_seconds' => $item['media']['duration_seconds'] ?? null,
                ];
            }

            foreach ($fullSchedule as $index => $item) {
                $start = $this->scheduleTimestamp($item['start_time'] ?? null);
                $end   = $this->scheduleTimestamp($item['end_time'] ?? null);
                if (!$start || !$end) continue;

                if ($now >= $start && $now <= $end) {
                    $currentIndex = $index;
                    $nowPlaying = [
                        'title'      => $item['title'] ?? null,
                        'start_time' => $item['start_time'],
                        'end_time'   => $item['end_time'],
                        'duration_seconds' => $item['duration_seconds'] ?? null,
                        'elapsed_seconds'  => $now - $start,
                    ];
                    if (isset($fullSchedule[$index + 1])) {
                        $next = $fullSchedule[$index + 1];
                        $nextPlaying = [
                            'title'      => $next['title'] ?? null,
                            'start_time' => $next['start_time'],
                            'end_time'   => $next['end_time'],
                            'duration_seconds' => $next['duration_seconds'] ?? null,
                        ];
                    }
                    break;
                }
            }

            $sliceStart = $currentIndex !== null ? max(0, $currentIndex - 8) : 0;

            return [$nowPlaying, $nextPlaying, array_slice($fullSchedule, $sliceStart, 72)];
        } catch (\Throwable $e) {
            return [null, null, []];
        }
    }

    private function scheduleTimestamp(?string $value): ?int
    {
        if (!$value) {
            return null;
        }

        try {
            $trimmed = trim($value);
            $hasTimezone = (bool) preg_match('/(?:z|[+-]\d{2}:?\d{2})$/i', $trimmed);
            $date = $hasTimezone ? Carbon::parse($trimmed) : Carbon::parse($trimmed, 'Asia/Dhaka');

            return $date->utc()->timestamp;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
