<?php

namespace Modules\AuthorChannel\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\AuthorChannel;
use App\Models\MobileSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Modules\Statistics\Models\ContentBoost;
use Modules\Statistics\Models\StatSetting;

class AuthorChannelAPIController extends Controller
{
    // -------------------------------------------------------------------------
    // PUBLIC — no auth required
    // -------------------------------------------------------------------------

    /**
     * GET /api/v3/ondemand
     * List all active ondemand channels (paginated, searchable).
     */
    public function index(Request $request)
    {
        $perPage = (int) $request->input('per_page', 15);
        $perPage = min($perPage, 100);
        $selectedChannelIds = $this->onDemandMobileSettingChannelIds();

        $cacheKey = 'spa:ondemand:index:' . md5(json_encode([
            'page' => $request->input('page', 1),
            'per_page' => $perPage,
            'search' => $request->input('search'),
            'selected' => $selectedChannelIds,
        ]));

        try {
            $channels = Auth::check() ? $this->buildChannelIndex($request, $perPage, $selectedChannelIds) : Cache::remember($cacheKey, 300, function () use ($request, $perPage, $selectedChannelIds) {
                return $this->buildChannelIndex($request, $perPage, $selectedChannelIds);
            });
        } catch (\Throwable $e) {
            $channels = $this->buildChannelIndex($request, $perPage, $selectedChannelIds);
        }

        return ApiResponse::success($channels, 'Author channel list');
    }

    private function buildChannelIndex(Request $request, int $perPage, array $selectedChannelIds = [])
    {
            $query = AuthorChannel::where('is_active', 1)
                ->with('plan:id,name,level')
                ->withCount('videos');

            if ($search = $request->input('search')) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('username', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%");
                });
            }

            if (count($selectedChannelIds) > 0) {
                $ids = implode(',', $selectedChannelIds);
                $query
                    ->orderByRaw("CASE WHEN id IN ({$ids}) THEN 0 ELSE 1 END")
                    ->orderByRaw("FIELD(id, {$ids})");
            }

            $channels = $query->orderBy('name')->paginate($perPage);

            $channels->getCollection()->transform(fn ($ch) => $this->formatChannel($ch));

            return $channels;
    }

    /**
     * GET /api/v3/ondemand/{username}
     * Channel profile by username (slug).
     */
    public function show($username)
    {
        try {
            $channel = Auth::check() ? $this->buildChannelShow($username) : Cache::remember("spa:ondemand:show:{$username}", 300, function () use ($username) {
                return $this->buildChannelShow($username);
            });
        } catch (\Throwable $e) {
            $channel = $this->buildChannelShow($username);
        }

        if (!$channel) {
            return ApiResponse::error('Channel not found.', 404);
        }

        return ApiResponse::success($channel, 'Author channel details');
    }

    private function buildChannelShow(string $username): ?array
    {
            $channel = AuthorChannel::where('username', $username)
                ->where('is_active', 1)
                ->with([
                    'plan:id,name,level',
                    'playlists' => fn ($query) => $query->where('is_active', 1),
                    'playlists.videos' => fn ($query) => $query
                        ->with('plan:id,name,level')
                        ->whereNull('videos.deleted_at')
                        ->where('videos.status', 1),
                ])
                ->withCount('videos')
                ->first();

            return $channel ? $this->formatChannel($channel, true) : null;
    }

    /**
     * GET /api/v3/ondemand/{username}/videos
     * Paginated video list for a channel.
     */
    public function videos(Request $request, $username)
    {
        $channel = AuthorChannel::where('username', $username)
            ->where('is_active', 1)
            ->with('plan:id,name,level')
            ->first();

        if (!$channel) {
            return ApiResponse::error('Channel not found.', 404);
        }

        $perPage = (int) $request->input('per_page', 15);
        $perPage = min($perPage, 50);

        $cacheKey = 'spa:ondemand:videos:' . md5(json_encode([
            'username' => $username,
            'page' => $request->input('page', 1),
            'per_page' => $perPage,
        ]));

        try {
            $videos = Auth::check() ? $this->buildChannelVideos($channel, $perPage) : Cache::remember($cacheKey, 300, function () use ($channel, $perPage) {
                return $this->buildChannelVideos($channel, $perPage);
            });
        } catch (\Throwable $e) {
            $videos = $this->buildChannelVideos($channel, $perPage);
        }

        return ApiResponse::success($videos, 'Channel videos');
    }

    private function buildChannelVideos(AuthorChannel $channel, int $perPage)
    {
            $videos = $channel->videos()
                ->with('plan:id,name,level')
                ->whereNull('videos.deleted_at')
                ->where('videos.status', 1)
                ->select(
                    'videos.id',
                    'videos.name',
                    'videos.slug',
                    'videos.description',
                    'videos.thumbnail_url',
                    'videos.poster_url',
                    'videos.trailer_url',
                    'videos.trailer_url_type',
                    'videos.video_url_input',
                    'videos.video_upload_type',
                    'videos.access',
                    'videos.plan_id',
                    'videos.duration',
                    'videos.release_date',
                    'videos.status'
                )
                ->orderBy('author_channel_video.created_at', 'desc')
                ->paginate($perPage);

            $videos->getCollection()->transform(fn ($v) => $this->formatVideo($v));

            return $videos;
    }

    // -------------------------------------------------------------------------
    // AUTHENTICATED — requires auth:sanctum
    // -------------------------------------------------------------------------

    /**
     * GET /api/v3/ondemand/my
     * Return the authenticated user's own ondemand channel(s).
     */
    public function myChannels(Request $request)
    {
        $userId   = Auth::id();
        $channels = AuthorChannel::where('user_id', $userId)
            ->with('plan:id,name,level')
            ->withCount('videos')
            ->get()
            ->map(fn ($ch) => $this->formatChannel($ch, true));

        return ApiResponse::success($channels, 'My channels');
    }

    /**
     * POST /api/v3/ondemand
     * Create a new ondemand channel for the authenticated user.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'        => 'required|string|max:255',
            'username'    => 'nullable|string|max:100|alpha_dash|unique:author_channels,username',
            'description' => 'nullable|string|max:2000',
            'avatar'      => 'nullable|string|max:500',
            'banner'      => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Validation failed.', 422, $validator->errors());
        }

        $data              = $validator->validated();
        $data['user_id']   = Auth::id();
        $data['is_active'] = true;

        if (empty($data['username'])) {
            $data['username'] = AuthorChannel::generateUsername($data['name']);
        }

        $channel = AuthorChannel::create($data);

        return ApiResponse::success($this->formatChannel($channel), 'On Demand created successfully.', 201);
    }

    /**
     * PUT /api/v3/ondemand/{id}
     * Update own ondemand channel (user must own it).
     */
    public function update(Request $request, $id)
    {
        $channel = AuthorChannel::findOrFail($id);

        if ($channel->user_id !== Auth::id()) {
            return ApiResponse::error('Unauthorized.', 403);
        }

        $validator = Validator::make($request->all(), [
            'name'        => 'sometimes|required|string|max:255',
            'username'    => 'sometimes|nullable|string|max:100|alpha_dash|unique:author_channels,username,' . $id,
            'description' => 'sometimes|nullable|string|max:2000',
            'avatar'      => 'sometimes|nullable|string|max:500',
            'banner'      => 'sometimes|nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Validation failed.', 422, $validator->errors());
        }

        $channel->update($validator->validated());

        return ApiResponse::success($this->formatChannel($channel), 'On Demand updated successfully.');
    }

    /**
     * DELETE /api/v3/ondemand/{id}
     * Soft-delete own ondemand channel.
     */
    public function destroy($id)
    {
        $channel = AuthorChannel::findOrFail($id);

        if ($channel->user_id !== Auth::id()) {
            return ApiResponse::error('Unauthorized.', 403);
        }

        $channel->delete();

        return ApiResponse::success(null, 'Channel deleted successfully.');
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function onDemandMobileSettingChannelIds(): array
    {
        $setting = MobileSetting::getNameAndValueBySlug('on-demand-channels');

        if (!$setting || empty($setting['value'])) {
            return [];
        }

        $decoded = json_decode($setting['value'], true);

        if (!is_array($decoded)) {
            return [];
        }

        return collect($decoded)
            ->filter(fn ($id) => $id !== null && $id !== '')
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    private function formatChannel(AuthorChannel $channel, bool $includeDescription = false): array
    {
        $access = $channel->access ?: 'free';
        $requiredPlanLevel = (int) optional($channel->plan)->level;
        $currentPlanLevel = $this->currentPlanLevel();
        $hasContentAccess = $access === 'free' || ($access === 'paid' && $currentPlanLevel >= $requiredPlanLevel && $requiredPlanLevel > 0);

        $videosCount = (int) ($channel->videos_count ?? $channel->videos()->count());

        $data = [
            'id'           => $channel->id,
            'name'         => $channel->name,
            'username'     => $channel->username,
            'cover_image_url' => $channel->banner ? setBaseUrlWithFileNameV2($channel->banner) : null,
            'avatar_image_url' => $channel->avatar ? setBaseUrlWithFileNameV2($channel->avatar) : null,
            'videos_count' => $videosCount,
            'video_count'  => $videosCount,
            'total_videos' => $videosCount,
            'is_active'    => (bool) $channel->is_active,
            'profile_url'  => url('/on-demand/' . $channel->username),
            'access'       => $access,
            'plan_id'      => $channel->plan_id,
            'plan_level'   => $requiredPlanLevel,
            'required_plan_level' => $requiredPlanLevel,
            'required_plan_name'  => optional($channel->plan)->name,
            'current_plan_level'  => $currentPlanLevel,
            'has_content_access'  => $hasContentAccess,
            'is_premium'          => $access === 'paid',
            'show_premium_badge'  => $access === 'paid' && ! $hasContentAccess,
            'stats'               => $this->channelStats($channel),
        ];

        if ($includeDescription) {
            $data['description'] = $channel->description;
            $data['playlists'] = $channel->relationLoaded('playlists')
                ? $channel->playlists->map(fn ($playlist) => $this->formatPlaylist($playlist))->values()->all()
                : [];
        }

        return $data;
    }

    private function formatPlaylist($playlist): array
    {
        return [
            'id' => $playlist->id,
            'name' => $playlist->name,
            'description' => $playlist->description,
            'video_count' => $playlist->videos->count(),
            'videos' => $playlist->videos->map(fn ($video) => $this->formatVideo($video))->values()->all(),
        ];
    }

    private function formatVideo($video): array
    {
        $thumb = $video->thumbnail_url ?: $video->poster_url;
        $access = $video->access ?: 'free';
        $requiredPlanLevel = (int) optional($video->plan)->level;
        $currentPlanLevel = $this->currentPlanLevel();
        $hasContentAccess = $access === 'free' || ($access === 'paid' && $currentPlanLevel >= $requiredPlanLevel && $requiredPlanLevel > 0);

        return [
            'id'            => $video->id,
            'name'          => $video->name,
            'slug'          => $video->slug,
            'description'   => $video->description,
            'thumbnail_url' => $thumb ? setBaseUrlWithFileNameV2($thumb) : null,
            'poster_url'    => $video->poster_url ? setBaseUrlWithFileNameV2($video->poster_url) : null,
            'trailer_url'   => ($video->trailer_url && $video->trailer_url_type === 'Local')
                                ? setBaseUrlWithFileName($video->trailer_url, 'video', 'video')
                                : $video->trailer_url,
            'video_url'     => ($video->video_upload_type === 'Local')
                                ? setBaseUrlWithFileName($video->video_url_input, 'video', 'video')
                                : $video->video_url_input,
            'video_type'    => $video->video_upload_type,
            'access'        => $access,
            'plan_id'       => $video->plan_id,
            'plan_level'    => $requiredPlanLevel,
            'required_plan_level' => $requiredPlanLevel,
            'required_plan_name'  => optional($video->plan)->name,
            'current_plan_level'  => $currentPlanLevel,
            'has_content_access'  => $hasContentAccess,
            'is_premium'          => $access === 'paid',
            'show_premium_badge'  => $access === 'paid' && ! $hasContentAccess,
            'duration'      => $video->duration,
            'release_date'  => $video->release_date,
            'language'      => $video->language,
            'status'        => $video->status,
        ];
    }

    private function currentPlanLevel(): int
    {
        $user = Auth::user();

        if (! $user) {
            return 0;
        }

        if (! $user->relationLoaded('subscriptionPackage')) {
            $user->load('subscriptionPackage:id,user_id,level,name,status');
        }

        return (int) optional($user->subscriptionPackage)->level;
    }

    private function channelStats(AuthorChannel $channel): array
    {
        $channelId = (int) $channel->id;
        $videoIds = DB::table('author_channel_video')
            ->where('author_channel_id', $channelId)
            ->pluck('video_id');

        $realViews = DB::table('stat_page_views')
            ->where('content_type', 'video')
            ->whereIn('content_id', $videoIds)
            ->count();
        $realViews += DB::table('stat_page_views')
            ->where('content_type', 'ondemand_video')
            ->where('channel_id', $channelId)
            ->count();
        $boostViews = (int) ContentBoost::whereIn('content_type', ['video', 'ondemand_video'])
            ->whereIn('content_id', $videoIds)
            ->sum('boost_views');

        $viewsMode = $this->displayModeFor('views', 'ondemand_channel', $channelId);
        $showPlayerViews = StatSetting::get("show_player_views:ondemand_channel:{$channelId}", '1') === '1';
        $showViewsFrontend = StatSetting::get('show_views_frontend', '1') === '1'
            && $showPlayerViews
            && $viewsMode !== 'hidden';
        $displayViews = $this->displayCount($viewsMode, (int) $realViews, $boostViews);

        return [
            'real_views' => (int) $realViews,
            'boost_views' => $boostViews,
            'display_views' => $displayViews,
            'total_views' => $displayViews,
            'views_display_mode' => $viewsMode,
            'show_player_views' => $showPlayerViews,
            'show_views_frontend' => $showViewsFrontend,
        ];
    }

    private function displayModeFor(string $metric, string $type, int $id): string
    {
        $allowed = ['combined', 'real', 'boosted', 'hidden'];
        $override = StatSetting::get("{$metric}_display_mode:{$type}:{$id}");
        $mode = in_array($override, $allowed, true)
            ? $override
            : StatSetting::get("{$metric}_display_mode", 'combined');

        return in_array($mode, $allowed, true) ? $mode : 'combined';
    }

    private function displayCount(string $mode, int $real, int $boost): int
    {
        return match ($mode) {
            'real' => $real,
            'boosted' => $boost,
            'hidden' => 0,
            default => $real + $boost,
        };
    }
}
