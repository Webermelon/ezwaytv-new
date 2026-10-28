<?php

namespace Modules\Statistics\Http\Controllers;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Statistics\Models\AppAnalyticsActivity;
use Modules\Statistics\Models\AppAnalyticsInstallation;

class AppAnalyticsController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'event' => ['required', 'in:install,open,heartbeat'],
            'installation_id' => ['required', 'uuid'],
            'platform' => ['required', 'in:android,ios,pwa'],
            'engaged_seconds' => ['nullable', 'integer', 'min:0', 'max:3600'],
        ]);

        $user = $request->user();
        abort_unless($user, 401);

        $now = now();
        $installation = AppAnalyticsInstallation::firstOrCreate(
            ['installation_id' => $data['installation_id']],
            [
                'user_id' => $user->id,
                'platform' => $data['platform'],
                'source' => 'telemetry',
                'installed_at' => $now,
            ]
        );

        $installation->forceFill([
            'user_id' => $user->id,
            'platform' => $data['platform'],
            'source' => 'telemetry',
            'installed_at' => $installation->installed_at ?: $now,
        ]);

        if ($data['event'] === 'open') {
            $installation->first_opened_at ??= $now;
            $installation->last_opened_at = $now;
        }
        $installation->save();

        if ($data['event'] !== 'install') {
            $activity = AppAnalyticsActivity::firstOrCreate(
                ['installation_id' => $data['installation_id'], 'activity_date' => $now->toDateString()],
                ['user_id' => $user->id, 'first_opened_at' => $now, 'last_activity_at' => $now]
            );

            $activity->user_id = $user->id;
            $activity->last_activity_at = $now;
            if ($data['event'] === 'open') {
                $activity->increment('opens');
            }
            if ($data['event'] === 'heartbeat') {
                $activity->engaged_seconds = min(86400, $activity->engaged_seconds + (int) ($data['engaged_seconds'] ?? 0));
            }
            $activity->save();
        }

        return response()->json(['status' => 'ok']);
    }

    public function report(Request $request): JsonResponse
    {
        $data = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $to = isset($data['to']) ? Carbon::createFromFormat('Y-m-d', $data['to'])->endOfDay() : now()->endOfDay();
        $from = isset($data['from']) ? Carbon::createFromFormat('Y-m-d', $data['from'])->startOfDay() : $to->copy()->subYear()->addDay()->startOfDay();
        abort_if($from->gt($to) || $from->diffInDays($to) > 366, 422, 'Choose a range of up to 366 days.');

        $installations = AppAnalyticsInstallation::query()
            ->where('source', 'telemetry')
            ->whereBetween('installed_at', [$from, $to]);
        $activities = AppAnalyticsActivity::query()
            ->whereBetween('activity_date', [$from->toDateString(), $to->toDateString()]);

        $activeUsers = (clone $activities)->distinct('user_id')->count('user_id');
        $observedInstalls = (clone $installations)->count();
        $openedInstallationIds = (clone $activities)->distinct()->pluck('installation_id');
        $neverOpened = (clone $installations)->whereNull('first_opened_at')->count();
        $inactiveUsers = (clone $installations)
            ->whereNotIn('installation_id', $openedInstallationIds)
            ->whereNotNull('user_id')
            ->distinct('user_id')
            ->count('user_id');
        $opens = (int) (clone $activities)->sum('opens');
        $engagedSeconds = (int) (clone $activities)->sum('engaged_seconds');

        $dailyRows = (clone $activities)
            ->selectRaw('activity_date, COUNT(DISTINCT user_id) as active_users, SUM(opens) as opens, SUM(engaged_seconds) as engaged_seconds')
            ->groupBy('activity_date')
            ->orderBy('activity_date')
            ->get()
            ->keyBy(fn ($row) => Carbon::parse($row->activity_date)->toDateString());

        $daily = collect(CarbonPeriod::create($from->toDateString(), $to->toDateString()))
            ->map(function (Carbon $date) use ($dailyRows) {
                $row = $dailyRows->get($date->toDateString());
                return [
                    'date' => $date->toDateString(),
                    'activeUsers' => (int) ($row->active_users ?? 0),
                    'opens' => (int) ($row->opens ?? 0),
                    'engagedMinutes' => (int) floor(((int) ($row->engaged_seconds ?? 0)) / 60),
                ];
            })
            ->values();

        $platforms = ['android' => 0, 'ios' => 0, 'pwa' => 0];
        foreach ((clone $installations)->select('platform', DB::raw('COUNT(*) as total'))->groupBy('platform')->get() as $row) {
            if (array_key_exists($row->platform, $platforms)) {
                $platforms[$row->platform] = (int) $row->total;
            }
        }

        return response()->json([
            'range' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'summary' => [
                'observedInstalls' => $observedInstalls,
                'activeUsers' => $activeUsers,
                'inactiveUsers' => $inactiveUsers,
                'neverOpened' => $neverOpened,
                'opens' => $opens,
                'engagedMinutes' => (int) floor($engagedSeconds / 60),
            ],
            'daily' => $daily,
            'platforms' => $platforms,
        ]);
    }
}
