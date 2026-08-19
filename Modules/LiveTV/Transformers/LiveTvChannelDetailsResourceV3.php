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
        $schedulesUrl = $this->resolveSchedulesUrl(optional($this->TvChannelStreamContentMappings)->api_key ?? null);
        $nowPlaying   = null;
        $nextPlaying  = null;
        $fullSchedule = [];

        if ($schedulesUrl) {
            [$nowPlaying, $nextPlaying, $fullSchedule] = $this->resolveScheduleData($schedulesUrl);
        } elseif ($this->relationLoaded('schedules') && $this->schedules->isNotEmpty()) {
            [$nowPlaying, $nextPlaying, $fullSchedule] = $this->resolveStoredScheduleData($this->schedules);
        }

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'channel_number' => $this->channel_number !== null ? (int) $this->channel_number : null,
            'details' => [
                'name' => $this->name,
                'type' => 'livetv',
                'server_url'=> optional($this->TvChannelStreamContentMappings)->server_url ?? null,
                'access' => $this->access,
                'is_device_supported'=> $this->isDeviceSupported ?? null,
                "has_content_access"=> $this->has_content_access,
                "required_plan_level"=> $this->required_plan_level ?? 0,
                "required_plan_name"=> optional($this->plan)->name,
                'description' => strip_tags($this->description),
                "thumbnail_image" => setBaseUrlWithFileName($this->poster_url,'image','livetv'),
                'category' => $this->TvCategory->name ?? null,
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
                    'timezone' => $item['timezone'] ?? null,
                    'status' => $item['status'] ?? null,
                    'duration_seconds' => $item['media']['duration_seconds'] ?? null,
                ];
            }

            usort($fullSchedule, function (array $left, array $right) {
                return ($this->scheduleTimestamp($left['start_time'] ?? null) ?? 0) <=> ($this->scheduleTimestamp($right['start_time'] ?? null) ?? 0);
            });

            foreach ($fullSchedule as $index => $item) {
                $start = $this->scheduleTimestamp($item['start_time'] ?? null);
                $end   = $this->scheduleTimestamp($item['end_time'] ?? null);
                if (!$start || !$end) continue;

                if ($this->isScheduleStatusPlaying($item['status'] ?? null) || ($now >= $start && $now <= $end)) {
                    $currentIndex = $index;
                    $duration = (int) ($item['duration_seconds'] ?? max(0, $end - $start));
                    $elapsed = max(0, $now - $start);

                    if ($this->isScheduleStatusPlaying($item['status'] ?? null) && $duration > 0) {
                        $elapsed = $elapsed % $duration;
                    }

                    $nowPlaying = [
                        'title'      => $item['title'] ?? null,
                        'start_time' => $item['start_time'],
                        'end_time'   => $item['end_time'],
                        'timezone'   => $item['timezone'] ?? null,
                        'status'     => $item['status'] ?? null,
                        'duration_seconds' => $duration ?: null,
                        'elapsed_seconds'  => $elapsed,
                    ];
                    if (isset($fullSchedule[$index + 1])) {
                        $next = $fullSchedule[$index + 1];
                        $nextPlaying = [
                            'title'      => $next['title'] ?? null,
                            'start_time' => $next['start_time'],
                            'end_time'   => $next['end_time'],
                            'timezone'   => $next['timezone'] ?? null,
                            'status'     => $next['status'] ?? null,
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

    private function resolveStoredScheduleData($schedules): array
    {
        $fullSchedule = [];

        foreach ($schedules as $index => $item) {
            $startValue = optional($item->start_at)->toIso8601String() ?? $item->start_at ?? null;
            $endValue = optional($item->end_at)->toIso8601String() ?? $item->end_at ?? null;
            $start = $this->scheduleTimestamp($startValue);
            $end = $this->scheduleTimestamp($endValue);

            if (!$start || !$end) {
                continue;
            }

            $fullSchedule[] = [
                'id' => $item->id ?? $index,
                'title' => $item->title ?? null,
                'start_time' => $startValue,
                'end_time' => $endValue,
                'duration_seconds' => max(0, $end - $start),
            ];
        }

        usort($fullSchedule, function (array $left, array $right) {
            return ($this->scheduleTimestamp($left['start_time'] ?? null) ?? 0) <=> ($this->scheduleTimestamp($right['start_time'] ?? null) ?? 0);
        });

        $now = now()->utc()->timestamp;
        $nowPlaying = null;
        $nextPlaying = null;
        $currentIndex = null;

        foreach ($fullSchedule as $index => $item) {
            $start = $this->scheduleTimestamp($item['start_time'] ?? null);
            $end   = $this->scheduleTimestamp($item['end_time'] ?? null);
            if (!$start || !$end) continue;

            if ($now >= $start && $now <= $end) {
                $currentIndex = $index;
                $nowPlaying = [
                    'title' => $item['title'] ?? null,
                    'start_time' => $item['start_time'],
                    'end_time' => $item['end_time'],
                    'duration_seconds' => $item['duration_seconds'] ?? null,
                    'elapsed_seconds' => $now - $start,
                ];

                if (isset($fullSchedule[$index + 1])) {
                    $next = $fullSchedule[$index + 1];
                    $nextPlaying = [
                        'title' => $next['title'] ?? null,
                        'start_time' => $next['start_time'],
                        'end_time' => $next['end_time'],
                        'duration_seconds' => $next['duration_seconds'] ?? null,
                    ];
                }

                break;
            }
        }

        $sliceStart = $currentIndex !== null ? max(0, $currentIndex - 8) : 0;

        return [$nowPlaying, $nextPlaying, array_slice($fullSchedule, $sliceStart, 72)];
    }

    private function resolveSchedulesUrl(?string $value): ?string
    {
        if (!$value) {
            return null;
        }

        $trimmed = trim($value);

        if ($trimmed === '') {
            return null;
        }

        if (preg_match('/^https?:\/\//i', $trimmed)) {
            return $trimmed;
        }

        return 'https://stream.ezway.tv/api/public/schedules/' . rawurlencode($trimmed);
    }

    private function isScheduleStatusPlaying(?string $status): bool
    {
        $normalized = strtolower(trim((string) $status));

        return in_array($normalized, ['playing', 'on_air', 'on air', 'on-air', 'looping'], true);
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
