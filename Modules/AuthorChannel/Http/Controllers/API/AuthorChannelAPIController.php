<?php

namespace Modules\AuthorChannel\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\AuthorChannel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

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

        $query = AuthorChannel::where('is_active', 1)
            ->withCount('videos');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('username', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $channels = $query->orderBy('name')->paginate($perPage);

        $channels->getCollection()->transform(fn ($ch) => $this->formatChannel($ch));

        return ApiResponse::success($channels, 'Author channel list');
    }

    /**
     * GET /api/v3/ondemand/{username}
     * Channel profile by username (slug).
     */
    public function show($username)
    {
        $channel = AuthorChannel::where('username', $username)
            ->where('is_active', 1)
            ->withCount('videos')
            ->first();

        if (!$channel) {
            return ApiResponse::error('Channel not found.', 404);
        }

        return ApiResponse::success($this->formatChannel($channel, true), 'Author channel details');
    }

    /**
     * GET /api/v3/ondemand/{username}/videos
     * Paginated video list for a channel.
     */
    public function videos(Request $request, $username)
    {
        $channel = AuthorChannel::where('username', $username)
            ->where('is_active', 1)
            ->first();

        if (!$channel) {
            return ApiResponse::error('Channel not found.', 404);
        }

        $perPage = (int) $request->input('per_page', 15);
        $perPage = min($perPage, 50);

        $videos = $channel->videos()
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
                'videos.duration',
                'videos.release_date',
                'videos.status'
            )
            ->orderBy('author_channel_video.created_at', 'desc')
            ->paginate($perPage);

        $videos->getCollection()->transform(fn ($v) => $this->formatVideo($v));

        return ApiResponse::success($videos, 'Channel videos');
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

    private function formatChannel(AuthorChannel $channel, bool $includeDescription = false): array
    {
        $data = [
            'id'           => $channel->id,
            'name'         => $channel->name,
            'username'     => $channel->username,
            'avatar_url'   => $channel->avatar ? setBaseUrlWithFileNameV2($channel->avatar) : null,
            'banner_url'   => $channel->banner ? setBaseUrlWithFileNameV2($channel->banner) : null,
            'videos_count' => $channel->videos_count ?? $channel->videos()->count(),
            'is_active'    => (bool) $channel->is_active,
            'profile_url'  => url('/on-demand/' . $channel->username),
        ];

        if ($includeDescription) {
            $data['description'] = $channel->description;
        }

        return $data;
    }

    private function formatVideo($video): array
    {
        $thumb = $video->thumbnail_url ?: $video->poster_url;

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
            'access'        => $video->access,
            'duration'      => $video->duration,
            'release_date'  => $video->release_date,
            'language'      => $video->language,
            'status'        => $video->status,
        ];
    }
}
