<?php

namespace Modules\Video\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Modules\Subscriptions\Models\Subscription;
use Modules\Video\Models\CreatorChannel;
use Modules\Video\Models\Video;

class CreatorChannelController extends Controller
{
    // -----------------------------------------------------------------------
    // Private helpers
    // -----------------------------------------------------------------------

    /**
     * Return the caller's active subscription or abort with 403.
     */
    private function requireActiveSubscription()
    {
        $subscription = Subscription::where('user_id', Auth::id())
            ->where('status', 'active')
            ->where('end_date', '>', now())
            ->first();

        if (!$subscription) {
            abort(response()->json([
                'success' => false,
                'message' => 'You must have an active subscription to use this feature.',
            ], 403));
        }

        return $subscription;
    }

    /**
     * Resolve a creator channel that belongs to the authenticated user or 404.
     */
    private function ownedChannel(int $id): CreatorChannel
    {
        return CreatorChannel::where('id', $id)
            ->where('user_id', Auth::id())
            ->firstOrFail();
    }

    /**
     * Reconstruct full image URLs on a channel for API response.
     */
    private function withImageUrls(CreatorChannel $channel): CreatorChannel
    {
        $channel->poster_url = setBaseUrlWithFileName($channel->poster_url, 'image', 'creator');
        $channel->banner_url = setBaseUrlWithFileName($channel->banner_url, 'image', 'creator');
        return $channel;
    }

    /**
     * Store a directly-uploaded image file to creator/image/ and return the filename.
     */
    private function storeUploadedImage(UploadedFile $file): string
    {
        $baseName   = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $ext        = $file->getClientOriginalExtension();
        $sanitized  = str_replace([' ', '-', '.', '%20'], '_', $baseName);
        $uniqueName = $sanitized . '_' . uniqid() . '.' . $ext;

        $activeDisk = env('ACTIVE_STORAGE', 'local');

        if ($activeDisk === 'local') {
            $dir  = 'public/creator/image';
            $path = $dir . '/' . $uniqueName;

            if (!Storage::disk('local')->exists($dir)) {
                File::makeDirectory(storage_path('app/' . $dir), 0775, true, true);
            }

            Storage::disk('local')->put($path, file_get_contents($file->getRealPath()));

            $fullPath = storage_path('app/' . $path);
            if (file_exists($fullPath)) {
                chmod($fullPath, 0664);
            }
        } else {
            $path = 'creator/image/' . $uniqueName;
            Storage::disk($activeDisk)->put($path, file_get_contents($file->getRealPath()));
        }

        return $uniqueName;
    }

    // -----------------------------------------------------------------------
    // Channel CRUD
    // -----------------------------------------------------------------------

    /**
     * GET api/creator/channels
     * List all channels owned by the authenticated user.
     */
    public function index(Request $request)
    {
        $channels = CreatorChannel::where('user_id', Auth::id())
            ->withCount('videos')
            ->orderByDesc('created_at')
            ->get()
            ->each(fn($c) => $this->withImageUrls($c));

        return response()->json([
            'success' => true,
            'data'    => $channels,
        ]);
    }

    /**
     * GET api/creator/channels/{id}
     */
    public function show(int $id)
    {
        $channel = $this->ownedChannel($id);
        $channel->loadCount('videos');
        $channel->load(['videos:id,name,slug,poster_url,status,creator_channel_id,access,duration,release_date']);
        $this->withImageUrls($channel);

        return response()->json([
            'success' => true,
            'data'    => $channel,
        ]);
    }

    /**
     * POST api/creator/channels
     * Create a new channel (requires active subscription).
     */
    public function store(Request $request)
    {
        $this->requireActiveSubscription();

        $validated = $request->validate([
            'name'        => 'required|string|max:255|unique:creator_channels,name',
            'description' => 'nullable|string',
            'poster_url'  => $request->hasFile('poster_url')
                                 ? 'nullable|file|image|max:10240'
                                 : 'nullable|string|max:2048',
            'banner_url'  => $request->hasFile('banner_url')
                                 ? 'nullable|file|image|max:10240'
                                 : 'nullable|string|max:2048',
        ]);

        $channel = CreatorChannel::create([
            'user_id'     => Auth::id(),
            'name'        => $validated['name'],
            'description' => $validated['description'] ?? null,
            'poster_url'  => $request->hasFile('poster_url')
                                ? $this->storeUploadedImage($request->file('poster_url'))
                                : (!empty($validated['poster_url'])
                                    ? extractFileNameFromUrl($validated['poster_url'], 'creator')
                                    : null),
            'banner_url'  => $request->hasFile('banner_url')
                                ? $this->storeUploadedImage($request->file('banner_url'))
                                : (!empty($validated['banner_url'])
                                    ? extractFileNameFromUrl($validated['banner_url'], 'creator')
                                    : null),
            'status'      => 1,
        ]);

        $this->withImageUrls($channel);

        return response()->json([
            'success' => true,
            'message' => 'Channel created successfully.',
            'data'    => $channel,
        ], 201);
    }

    /**
     * PUT api/creator/channels/{id}
     */
    public function update(Request $request, int $id)
    {
        $this->requireActiveSubscription();

        $channel = $this->ownedChannel($id);

        $validated = $request->validate([
            'name'        => ['sometimes', 'required', 'string', 'max:255',
                              Rule::unique('creator_channels', 'name')->ignore($channel->id)],
            'description' => 'nullable|string',
            'poster_url'  => $request->hasFile('poster_url')
                                 ? 'nullable|file|image|max:10240'
                                 : 'nullable|string|max:2048',
            'banner_url'  => $request->hasFile('banner_url')
                                 ? 'nullable|file|image|max:10240'
                                 : 'nullable|string|max:2048',
            'status'      => 'sometimes|boolean',
        ]);

        if ($request->hasFile('poster_url')) {
            $validated['poster_url'] = $this->storeUploadedImage($request->file('poster_url'));
        } elseif (isset($validated['poster_url'])) {
            $validated['poster_url'] = !empty($validated['poster_url'])
                ? extractFileNameFromUrl($validated['poster_url'], 'creator')
                : null;
        }

        if ($request->hasFile('banner_url')) {
            $validated['banner_url'] = $this->storeUploadedImage($request->file('banner_url'));
        } elseif (isset($validated['banner_url'])) {
            $validated['banner_url'] = !empty($validated['banner_url'])
                ? extractFileNameFromUrl($validated['banner_url'], 'creator')
                : null;
        }

        $channel->update($validated);

        $fresh = $channel->fresh();
        $this->withImageUrls($fresh);

        return response()->json([
            'success' => true,
            'message' => 'Channel updated successfully.',
            'data'    => $fresh,
        ]);
    }

    /**
     * DELETE api/creator/channels/{id}
     */
    public function destroy(int $id)
    {
        $this->requireActiveSubscription();

        $channel = $this->ownedChannel($id);
        $channel->delete();

        return response()->json([
            'success' => true,
            'message' => 'Channel deleted.',
        ]);
    }

    // -----------------------------------------------------------------------
    // Videos inside a channel
    // -----------------------------------------------------------------------

    /**
     * GET api/creator/channels/{id}/videos
     */
    public function channelVideos(int $id)
    {
        $channel = $this->ownedChannel($id);

        $videos = $channel->videos()
            ->select(['id', 'name', 'slug', 'poster_url', 'status', 'access', 'duration', 'release_date', 'creator_channel_id'])
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $videos,
        ]);
    }

    /**
     * POST api/creator/channels/{id}/videos
     * Assign an existing video (created by this user) to this channel.
     */
    public function addVideo(Request $request, int $id)
    {
        $this->requireActiveSubscription();

        $channel = $this->ownedChannel($id);

        $validated = $request->validate([
            'video_id' => 'required|integer|exists:videos,id',
        ]);

        $video = Video::where('id', $validated['video_id'])
            ->where('created_by', Auth::id())
            ->firstOrFail();

        $video->update(['creator_channel_id' => $channel->id]);

        return response()->json([
            'success' => true,
            'message' => 'Video added to channel.',
            'data'    => $video->only(['id', 'name', 'creator_channel_id']),
        ]);
    }

    /**
     * DELETE api/creator/channels/{channelId}/videos/{videoId}
     * Remove a video from this channel (unlink, not delete).
     */
    public function removeVideo(int $channelId, int $videoId)
    {
        $this->requireActiveSubscription();

        $channel = $this->ownedChannel($channelId);

        $video = Video::where('id', $videoId)
            ->where('creator_channel_id', $channel->id)
            ->where('created_by', Auth::id())
            ->firstOrFail();

        $video->update(['creator_channel_id' => null]);

        return response()->json([
            'success' => true,
            'message' => 'Video removed from channel.',
        ]);
    }

    // -----------------------------------------------------------------------
    // Public read-only endpoints (no auth required)
    // -----------------------------------------------------------------------

    /**
     * GET api/creator/public-channels
     * List all active creator channels (for discovery).
     */
    public function publicChannels(Request $request)
    {
        $channels = CreatorChannel::where('status', 1)
            ->withCount('videos')
            ->orderByDesc('created_at')
            ->paginate(20);

        $channels->getCollection()->each(fn($c) => $this->withImageUrls($c));

        return response()->json([
            'success' => true,
            'data'    => $channels,
        ]);
    }

    /**
     * GET api/creator/public-channels/{slug}
     * Show channel details and its public active videos.
     */
    public function publicChannelDetail(string $slug)
    {
        $channel = CreatorChannel::where('slug', $slug)
            ->where('status', 1)
            ->firstOrFail();

        $videos = $channel->videos()
            ->where('status', 1)
            ->select(['id', 'name', 'slug', 'poster_url', 'thumbnail_url', 'access', 'duration', 'release_date', 'creator_channel_id'])
            ->orderByDesc('created_at')
            ->get();

        $this->withImageUrls($channel);

        return response()->json([
            'success' => true,
            'data'    => array_merge($channel->toArray(), ['videos' => $videos]),
        ]);
    }
}
