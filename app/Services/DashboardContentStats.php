<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Modules\Entertainment\Models\Entertainment;
use Modules\Entertainment\Models\EntertainmentView;

final class DashboardContentStats
{
    private const CACHE_KEY = 'admin-dashboard:content-stats:v1';

    public function refresh(): void
    {
        $now = Carbon::now();
        $periods = [];
        foreach (['Year', 'Month', 'Week'] as $period) {
            [$totals, $types] = $this->queryViewing($period, $now->copy()->startOfYear(), $now->copy()->endOfDay(), $now);
            $periods[$period] = [
                'window' => $this->window($period, $now),
                'totals' => $totals,
                'types' => $types,
            ];
        }

        $inventory = [
            'totalOnDemandChannels' => DB::table('author_channels')->whereNull('deleted_at')->count(),
            'totalOnDemandVideos' => DB::table('author_channel_video')
                ->join('videos', 'videos.id', '=', 'author_channel_video.video_id')
                ->whereNull('videos.deleted_at')
                ->distinct('author_channel_video.video_id')
                ->count('author_channel_video.video_id'),
            'totalTvChannels' => DB::table('live_tv_channel')->whereNull('deleted_at')->count(),
        ];

        if (!Cache::forever(self::CACHE_KEY, compact('inventory', 'periods'))) {
            throw new \RuntimeException('TV dashboard content cache write failed.');
        }
    }

    public function inventory(): array
    {
        return Cache::get(self::CACHE_KEY)['inventory'] ?? [];
    }

    public function viewing(string $period, ?Carbon $from, ?Carbon $to, Carbon $now): array
    {
        if ($from?->isSameDay($now->copy()->startOfYear()) && $to?->isSameDay($now)) {
            $saved = Cache::get(self::CACHE_KEY)['periods'][$period] ?? null;
            if (($saved['window'] ?? null) === $this->window($period, $now)) {
                return [$saved['totals'], $saved['types']];
            }

            return [collect(), collect()];
        }

        return $this->queryViewing($period, $from, $to, $now);
    }

    private function window(string $period, Carbon $now): string
    {
        return match ($period) {
            'Year' => (string) $now->year,
            'Month' => $now->format('Y-m'),
            'Week' => $now->copy()->startOfWeek()->toDateString(),
        };
    }

    private function queryViewing(string $period, ?Carbon $from, ?Carbon $to, Carbon $now): array
    {
        $bucket = match ($period) {
            'Year' => 'MONTH(created_at)',
            'Month' => 'WEEK(created_at, 1)',
            'Week' => 'DAY(created_at)',
        };
        $alias = match ($period) {
            'Year' => 'month',
            'Month' => 'week',
            'Week' => 'day',
        };

        $query = EntertainmentView::query()
            ->selectRaw("YEAR(created_at) as year, MONTH(created_at) as calendar_month, $bucket as $alias, entertainment_id, COUNT(*) as total_views")
            ->groupBy('year', 'calendar_month', $alias, 'entertainment_id');

        if ($period !== 'Week') {
            $query->whereYear('created_at', $now->year);
        }
        if ($period === 'Month') {
            $query->whereMonth('created_at', $now->month);
        }
        if ($from && $to) {
            $query->whereBetween('created_at', [$from, $to]);
        } elseif ($period === 'Week') {
            $query->whereBetween('created_at', [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()]);
        }

        $totals = $query->toBase()->get()->groupBy('entertainment_id');
        $types = $totals->isEmpty() ? collect() : Entertainment::whereKey($totals->keys()->all())->pluck('type', 'id');

        return [$totals, $types];
    }
}
