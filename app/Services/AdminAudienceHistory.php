<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use RuntimeException;

final class AdminAudienceHistory
{
    private const CACHE_KEY = 'tv-admin:audience-history:v1';

    private const TRAFFIC_SOURCE = "
            CASE
                WHEN page_url REGEXP '[?&]utm_medium=email([&#]|$)' THEN 'Email'
                WHEN referrer IS NULL OR referrer = '' THEN 'Direct'
                WHEN referrer LIKE '%google.%' THEN 'Google'
                WHEN referrer LIKE '%facebook.%' THEN 'Facebook'
                WHEN referrer LIKE '%twitter.%' OR referrer LIKE '%x.com%' THEN 'Twitter/X'
                WHEN referrer LIKE '%instagram.%' THEN 'Instagram'
                WHEN referrer LIKE '%youtube.%' THEN 'YouTube'
                WHEN referrer LIKE '%t.me%' OR referrer LIKE '%telegram.%' THEN 'Telegram'
                ELSE 'Other'
            END
        ";

    public function data(string $period, ?string $startDate, string $metric): array
    {
        if ($period === 'today') {
            return $this->collect($period, $startDate, $metric);
        }

        $history = Cache::get(self::CACHE_KEY, []);
        $period = in_array($period, ['week', 'month', 'year'], true) ? $period : 'all';

        return $history[$period] ?? [];
    }

    public function refresh(): void
    {
        $today = now()->startOfDay();
        $ranges = [
            'week' => $today->copy()->subDays(6)->toDateString(),
            'month' => $today->copy()->subDays(29)->toDateString(),
            'year' => $today->copy()->subDays(364)->toDateString(),
            'all' => null,
        ];
        $history = [];
        foreach ($ranges as $period => $startDate) {
            $history[$period] = $this->collect($period, $startDate);
        }

        if (!Cache::forever(self::CACHE_KEY, $history)) {
            throw new RuntimeException('Audience history cache write failed.');
        }
    }

    private function collect(string $period, ?string $startDate, ?string $metric = null): array
    {
        $format = $period === 'year' ? '%Y-%m' : '%Y-%m-%d';
        $views = TvAnalyticsStorage::table('stat_page_views')
            ->when($startDate, fn ($query) => $query->where('view_date', '>=', $startDate));
        $plays = TvAnalyticsStorage::table('stat_play_events')
            ->when($startDate, fn ($query) => $query->where('play_date', '>=', $startDate));
        $legacy = TvAnalyticsStorage::table('entertainment_views')->whereNull('deleted_at')
            ->when($startDate, fn ($query) => $query->where('created_at', '>=', $startDate.' 00:00:00'));

        $data = [];
        $legacyTotal = null;
        if ($metric === null || $metric === 'series') {
            $legacyByDate = $this->groupCounts($legacy, "DATE_FORMAT(DATE(created_at), '$format')", 'label');
            $viewSeries = $this->groupCounts($views, "DATE_FORMAT(view_date, '$format')", 'label');
            $playSeries = $this->groupCounts($plays, "DATE_FORMAT(play_date, '$format')", 'label');
            foreach ($legacyByDate as $label => $count) {
                $viewSeries[$label] = ($viewSeries[$label] ?? 0) + $count;
                $playSeries[$label] = ($playSeries[$label] ?? 0) + $count;
            }
            ksort($viewSeries);
            ksort($playSeries);

            $legacyTotal = array_sum($legacyByDate);
            $data = compact('viewSeries', 'playSeries');
        }
        if ($metric === 'series') {
            return $data;
        }

        $legacyTotal ??= $legacy->count();
        $dimensions = [
            'devices' => [$plays, 'COALESCE(device_type, "unknown")', 'device', 'web'],
            'countries' => [$plays, 'COALESCE(country_code, "Unknown")', 'country', 'Unknown'],
            'platforms' => [$plays, 'COALESCE(platform, "web")', 'platform', 'web'],
            'traffic' => [$views, self::TRAFFIC_SOURCE, 'source', 'Direct'],
        ];

        foreach ($dimensions as $name => [$query, $expression, $label, $legacyLabel]) {
            if ($metric !== null && $metric !== $name) {
                continue;
            }

            $counts = $this->groupCounts($query, $expression, $label);
            if ($legacyTotal > 0) {
                $counts[$legacyLabel] = ($counts[$legacyLabel] ?? 0) + $legacyTotal;
            }
            $data[$name] = $counts;
        }

        return $data;
    }

    private function groupCounts($query, string $expression, string $label): array
    {
        $query = (clone $query)->selectRaw("$expression as $label, COUNT(*) as total")->groupBy($label);
        $query = $label === 'label' ? $query->orderBy($label) : $query->orderByDesc('total');

        return $query->pluck('total', $label)->all();
    }
}
