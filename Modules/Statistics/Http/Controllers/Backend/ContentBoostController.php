<?php

namespace Modules\Statistics\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Statistics\Models\ContentBoost;

class ContentBoostController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'admin']);
        // Extra gate: only 'admin' role (not demo_admin)
        $this->middleware(function ($request, $next) {
            if (!auth()->user()->hasRole('admin')) {
                abort(403);
            }
            return $next($request);
        });
    }

    public function index()
    {
        $module_title  = 'Content Booster';
        $module_name   = 'statistics';
        $module_icon   = 'ph ph-rocket-launch';
        $module_action = 'Booster';

        $boosts = ContentBoost::selectRaw(
                'content_type, content_id, MAX(content_name) as content_name, ' .
                'SUM(boost_plays) as boost_plays, SUM(boost_views) as boost_views, COUNT(*) as entries, MAX(updated_at) as updated_at'
            )
            ->groupBy('content_type', 'content_id')
            ->orderByDesc('updated_at')
            ->get();

        // Load settings so admin booster form can render here
        $settings = \Modules\Statistics\Models\StatSetting::all()->pluck('value', 'key');

        return view('statistics::backend.statistics.booster', compact(
            'module_title', 'module_name', 'module_icon', 'module_action', 'boosts', 'settings'
        ));
    }

    /**
     * AJAX: Search content by name across videos, episodes, live_tv_channel.
     */
    public function search(Request $request)
    {
        $q     = trim($request->input('q', ''));
        $type  = $request->input('type', 'all');
        $limit = 20;

        if (strlen($q) < 2) {
            return response()->json([]);
        }

        $results = collect();

        $searchTypes = $type === 'all'
            ? ['video', 'episode', 'livetv']
            : [$type];

        foreach ($searchTypes as $ctype) {
            [$table, $label] = match ($ctype) {
                'video'   => ['videos', 'Video'],
                'episode' => ['episodes', 'Episode'],
                'livetv'  => ['live_tv_channel', 'Live TV'],
                default   => [null, null],
            };

            if (!$table) continue;

            $rows = DB::table($table)
                ->where('name', 'like', "%{$q}%")
                ->whereNull('deleted_at')
                ->limit($limit)
                ->get(['id', 'name']);

            foreach ($rows as $row) {
                // Get real play count
                $realPlays = DB::table('entertainment_views')
                    ->where('entertainment_id', $row->id)
                    ->whereNull('deleted_at')
                    ->count();
                $realPlays += DB::table('stat_play_events')
                    ->where('content_id', $row->id)
                    ->where('content_type', $ctype)
                    ->count();

                // Get existing boost if any (summed)
                $boostSums = ContentBoost::where('content_type', $ctype)
                    ->where('content_id', $row->id)
                    ->selectRaw('SUM(boost_plays) as total_plays, SUM(boost_views) as total_views, COUNT(*) as entries')
                    ->first();

                $results->push([
                    'content_type'  => $ctype,
                    'content_id'    => $row->id,
                    'name'          => $row->name,
                    'type_label'    => $label,
                    'real_plays'    => $realPlays,
                    'boost_plays'   => (int)($boostSums->total_plays ?? 0),
                    'boost_views'   => (int)($boostSums->total_views ?? 0),
                    'boost_entries' => (int)($boostSums->entries ?? 0),
                ]);
            }
        }

        return response()->json($results->take($limit)->values());
    }

    /**
     * AJAX: Get current real stats + existing boost for a specific content item.
     */
    public function stats(Request $request)
    {
        $type = $request->input('content_type');
        $id   = (int) $request->input('content_id');

        if (!$type || !$id) {
            return response()->json(['error' => 'Missing params'], 422);
        }

        // Real counts
        $realPlays = DB::table('entertainment_views')
            ->where('entertainment_id', $id)
            ->whereNull('deleted_at')
            ->count();
        $realPlays += DB::table('stat_play_events')
            ->where('content_id', $id)
            ->where('content_type', $type)
            ->count();

        $realViews = DB::table('stat_page_views')
            ->where('content_id', $id)
            ->where('content_type', $type)
            ->count();

        // Summed boosts
        $boostSums = ContentBoost::where('content_type', $type)
            ->where('content_id', $id)
            ->selectRaw('SUM(boost_plays) as total_plays, SUM(boost_views) as total_views')
            ->first();

        // Boost history (newest first)
        $history = ContentBoost::where('content_type', $type)
            ->where('content_id', $id)
            ->orderByDesc('created_at')
            ->get(['id', 'boost_plays', 'boost_views', 'note', 'created_at']);

        return response()->json([
            'real_plays'   => $realPlays,
            'real_views'   => $realViews,
            'boost_plays'  => (int)($boostSums->total_plays ?? 0),
            'boost_views'  => (int)($boostSums->total_views ?? 0),
            'history'      => $history,
        ]);
    }

    /**
     * Add a new boost entry (always inserts — stacked/additive).
     */
    public function save(Request $request)
    {
        $validated = $request->validate([
            'content_type' => 'required|string|max:50',
            'content_id'   => 'required|integer|min:1',
            'boost_plays'  => 'required|integer|min:0',
            'boost_views'  => 'required|integer|min:0',
            'note'         => 'nullable|string|max:255',
        ]);

        // Resolve content name for display
        $name = $this->resolveName($validated['content_type'], $validated['content_id']);

        ContentBoost::create([
            'content_type' => $validated['content_type'],
            'content_id'   => $validated['content_id'],
            'content_name' => $name,
            'boost_plays'  => $validated['boost_plays'],
            'boost_views'  => $validated['boost_views'],
            'note'         => $validated['note'] ?? null,
        ]);

        return response()->json(['success' => true, 'name' => $name]);
    }

    /**
     * Remove a content boost.
     */
    public function destroy(int $id)
    {
        ContentBoost::findOrFail($id)->delete();
        return response()->json(['success' => true]);
    }

    private function resolveName(string $type, int $id): string
    {
        $table = match ($type) {
            'video'   => 'videos',
            'episode' => 'episodes',
            'livetv'  => 'live_tv_channel',
            default   => 'videos',
        };

        $row = DB::table($table)->where('id', $id)->first(['name']);
        return $row->name ?? "#{$id}";
    }
}
