<?php

namespace App\Http\Controllers\Api\Private;

use App\Http\Controllers\Controller;
use App\Models\AuthorChannel;
use App\Models\AuthorChannelPlaylist;
use App\Services\CoreTvAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Modules\Video\Models\Video;
use Illuminate\Support\Facades\Log;

class CoreOnDemandPublishingController extends Controller
{
    public function __construct(private CoreTvAccessService $access)
    {
    }

    public function channels(Request $request): JsonResponse
    {
        $data = $request->validate([
            'core_user_id' => ['required', 'integer', 'min:1'],
        ]);

        $user = $this->access->syncUser($data);
        $channels = AuthorChannel::query()
            ->where('user_id', $user->id)
            ->withCount('videos')
            ->latest('id')
            ->get()
            ->map(fn (AuthorChannel $channel) => $this->formatChannel($channel));

        return response()->json(['success' => true, 'data' => $channels]);
    }

    public function storeChannel(Request $request): JsonResponse
    {
        $data = $request->validate($this->channelRules(true));
        $user = $this->access->syncUser($data);
        Log::info('Creating On Demand Channel for user_id: '. $user->id . ' with data: '. print_r($data, true));
        $channel_data = [
            'user_id' => $user->id,
            'name' => $data['name'],
            'username' => $data['channel_username'] ?? AuthorChannel::generateUsername($data['name']),
            'description' => $data['description'] ?? null,
            'avatar' => $data['avatar'] ?? null,
            'banner' => $data['banner'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? true),
            'access' => 'free',
            'plan_id' => null,
        ];

        Log::info('Creating On Demand Channel: '. print_r($channel_data, true));

        $channel = AuthorChannel::query()->create($channel_data);

        error_log('Created On Demand Channel: '. print_r($channel->toArray(), true));

        return response()->json(['success' => true, 'data' => $this->formatChannel($channel), 'message' => 'Channel created.'], 201);
    }

    public function updateChannel(Request $request, int $channel): JsonResponse
    {
        $data = $request->validate($this->channelRules(false, $channel));
        $user = $this->access->syncUser($data);
        $model = $this->ownedChannel($channel, $user->id);

        $values = collect($data)->only(['name', 'description', 'avatar', 'banner', 'is_active'])->all();
        if (array_key_exists('channel_username', $data)) {
            $values['username'] = $data['channel_username'];
        }
        $model->fill($values)->save();

        return response()->json(['success' => true, 'data' => $this->formatChannel($model->refresh()), 'message' => 'Channel updated.']);
    }

    public function videos(Request $request, int $channel): JsonResponse
    {
        $data = $request->validate([
            'core_user_id' => ['required', 'integer', 'min:1'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $user = $this->access->syncUser($data);
        $model = $this->ownedChannel($channel, $user->id);
        $this->reconcileProcessingVideos($model);

        $videos = $model->videos()
            ->whereNull('videos.deleted_at')
            ->latest('videos.id')
            ->paginate((int) ($data['limit'] ?? 20));
        $videos->getCollection()->transform(fn (Video $video) => $this->formatVideo($video));

        return response()->json(['success' => true, 'data' => $videos]);
    }

    public function storeVideo(Request $request, int $channel): JsonResponse
    {
        $data = $request->validate($this->videoRules(true));
        $user = $this->access->syncUser($data);
        $channelModel = $this->ownedChannel($channel, $user->id);

        $video = Video::query()->create($this->videoPayload($data, $channelModel->id, $user->id));
        $channelModel->videos()->syncWithoutDetaching([$video->id]);

        return response()->json(['success' => true, 'data' => $this->formatVideo($video), 'message' => 'Video saved.'], 201);
    }

    public function updateVideo(Request $request, int $channel, int $video): JsonResponse
    {
        $data = $request->validate($this->videoRules(false));
        $user = $this->access->syncUser($data);
        $channelModel = $this->ownedChannel($channel, $user->id);
        $videoModel = $channelModel->videos()->where('videos.id', $video)->firstOrFail();

        $videoModel->fill($this->videoPayload($data, $channelModel->id, $user->id, false))->save();
        $channelModel->videos()->syncWithoutDetaching([$videoModel->id]);

        return response()->json(['success' => true, 'data' => $this->formatVideo($videoModel->refresh()), 'message' => 'Video updated.']);
    }

    public function deleteVideo(Request $request, int $channel, int $video): JsonResponse
    {
        $data = $request->validate([
            'core_user_id' => ['required', 'integer', 'min:1'],
            'connect_user_id' => ['nullable', 'integer', 'min:1'],
            'email' => ['nullable', 'email'],
            'username' => ['nullable', 'string', 'max:190'],
            'first_name' => ['nullable', 'string', 'max:190'],
            'last_name' => ['nullable', 'string', 'max:190'],
            'avatar' => ['nullable', 'string', 'max:1000'],
            'phone' => ['nullable', 'string', 'max:60'],
        ]);
        $user = $this->access->syncUser($data);
        $channelModel = $this->ownedChannel($channel, $user->id);
        $videoModel = $channelModel->videos()->where('videos.id', $video)->firstOrFail();

        $videoModel->delete();

        return response()->json(['success' => true, 'message' => 'Video deleted.']);
    }

    public function playlists(Request $request, int $channel): JsonResponse
    {
        $data = $request->validate($this->coreUserRules());
        $user = $this->access->syncUser($data);
        $channelModel = $this->ownedChannel($channel, $user->id);

        $playlists = $channelModel->playlists()
            ->with(['videos' => fn ($query) => $query->whereNull('videos.deleted_at')])
            ->get()
            ->map(fn (AuthorChannelPlaylist $playlist) => $this->formatPlaylist($playlist));

        return response()->json(['success' => true, 'data' => $playlists]);
    }

    public function storePlaylist(Request $request, int $channel): JsonResponse
    {
        $data = $request->validate($this->playlistRules(true));
        $user = $this->access->syncUser($data);
        $channelModel = $this->ownedChannel($channel, $user->id);

        $playlist = $channelModel->playlists()->create([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'thumbnail' => $data['thumbnail'] ?? null,
            'sort_order' => (int) $channelModel->playlists()->max('sort_order') + 1,
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);
        $this->clearPublicChannelCache($channelModel);

        return response()->json([
            'success' => true,
            'message' => 'Playlist created.',
            'data' => $this->formatPlaylist($playlist->load('videos')),
        ], 201);
    }

    public function updatePlaylist(Request $request, int $channel, int $playlist): JsonResponse
    {
        $data = $request->validate($this->playlistRules(false));
        $user = $this->access->syncUser($data);
        $channelModel = $this->ownedChannel($channel, $user->id);
        $playlistModel = $this->channelPlaylist($channelModel, $playlist);

        $playlistModel->fill(collect($data)->only(['name', 'description', 'thumbnail', 'is_active'])->all())->save();
        $this->clearPublicChannelCache($channelModel);

        return response()->json([
            'success' => true,
            'message' => 'Playlist updated.',
            'data' => $this->formatPlaylist($playlistModel->refresh()->load('videos')),
        ]);
    }

    public function deletePlaylist(Request $request, int $channel, int $playlist): JsonResponse
    {
        $data = $request->validate($this->coreUserRules());
        $user = $this->access->syncUser($data);
        $channelModel = $this->ownedChannel($channel, $user->id);
        $playlistModel = $this->channelPlaylist($channelModel, $playlist);

        $playlistModel->videos()->detach();
        $playlistModel->delete();
        $this->clearPublicChannelCache($channelModel);

        return response()->json(['success' => true, 'message' => 'Playlist deleted.']);
    }

    public function addPlaylistVideo(Request $request, int $channel, int $playlist): JsonResponse
    {
        $data = $request->validate(array_merge($this->coreUserRules(), [
            'video_id' => ['required', 'integer'],
        ]));
        $user = $this->access->syncUser($data);
        $channelModel = $this->ownedChannel($channel, $user->id);
        $playlistModel = $this->channelPlaylist($channelModel, $playlist);
        $videoId = (int) $data['video_id'];

        $channelVideo = $channelModel->videos()->where('videos.id', $videoId)->whereNull('videos.deleted_at')->firstOrFail();
        $wasAdded = false;
        if (! $playlistModel->videos()->where('videos.id', $videoId)->exists()) {
            $playlistModel->videos()->attach($videoId, ['sort_order' => $playlistModel->videos()->count() + 1]);
            $wasAdded = true;
        }
        $this->clearPublicChannelCache($channelModel);

        return response()->json([
            'success' => true,
            'message' => $wasAdded ? 'Video added to playlist.' : 'Video is already in this playlist.',
            'data' => [
                'added' => $wasAdded,
                'playlist_id' => (int) $playlistModel->id,
                'playlist_count' => $playlistModel->videos()->count(),
                'video' => $this->formatVideo($channelVideo),
            ],
        ], $wasAdded ? 201 : 200);
    }

    public function removePlaylistVideo(Request $request, int $channel, int $playlist, int $video): JsonResponse
    {
        $data = $request->validate($this->coreUserRules());
        $user = $this->access->syncUser($data);
        $channelModel = $this->ownedChannel($channel, $user->id);
        $playlistModel = $this->channelPlaylist($channelModel, $playlist);

        $playlistModel->videos()->detach($video);
        $this->clearPublicChannelCache($channelModel);

        return response()->json(['success' => true, 'message' => 'Video removed from playlist.']);
    }

    public function reorderPlaylistVideos(Request $request, int $channel, int $playlist): JsonResponse
    {
        $data = $request->validate(array_merge($this->coreUserRules(), [
            'video_ids' => ['required', 'array'],
            'video_ids.*' => ['integer'],
        ]));
        $user = $this->access->syncUser($data);
        $channelModel = $this->ownedChannel($channel, $user->id);
        $playlistModel = $this->channelPlaylist($channelModel, $playlist);

        $attachedIds = $playlistModel->videos()->pluck('videos.id')->map(fn ($videoId) => (int) $videoId)->all();
        $orderedIds = collect($data['video_ids'])
            ->map(fn ($videoId) => (int) $videoId)
            ->filter(fn ($videoId) => in_array($videoId, $attachedIds, true))
            ->unique()
            ->values();

        foreach ($orderedIds as $index => $videoId) {
            $playlistModel->videos()->updateExistingPivot($videoId, ['sort_order' => $index + 1]);
        }
        $this->clearPublicChannelCache($channelModel);

        return response()->json([
            'success' => true,
            'message' => 'Playlist order updated.',
            'data' => ['video_ids' => $orderedIds],
        ]);
    }

    public function availableVideos(Request $request, int $channel): JsonResponse
    {
        $data = $request->validate(array_merge($this->coreUserRules(), [
            'q' => ['nullable', 'string', 'max:255'],
        ]));
        $user = $this->access->syncUser($data);
        $channelModel = $this->ownedChannel($channel, $user->id);

        $videos = $channelModel->videos()
            ->whereNull('videos.deleted_at')
            ->when($data['q'] ?? null, fn ($query, $search) => $query->where('videos.name', 'like', '%'.$search.'%'))
            ->orderByDesc('videos.updated_at')
            ->orderByDesc('videos.created_at')
            ->limit(50)
            ->get()
            ->map(fn (Video $video) => collect($this->formatVideo($video))
                ->only(['id', 'title', 'name', 'thumbnail_url', 'poster_url', 'duration', 'status'])
                ->all());

        return response()->json(['success' => true, 'data' => $videos]);
    }

    public function assignVideo(Request $request, int $channel): JsonResponse
    {
        $data = $request->validate(array_merge($this->coreUserRules(), [
            'video_id' => ['required', 'integer', 'exists:videos,id'],
        ]));
        $user = $this->access->syncUser($data);
        $channelModel = $this->ownedChannel($channel, $user->id);
        $videoModel = Video::query()
            ->where('id', (int) $data['video_id'])
            ->whereNull('deleted_at')
            ->where(function ($query) use ($user, $channelModel) {
                $query->where('created_by', $user->id)->orWhere('creator_channel_id', $channelModel->id);
            })
            ->firstOrFail();

        $wasAdded = false;
        if (! $channelModel->videos()->where('video_id', $videoModel->id)->exists()) {
            $channelModel->videos()->attach($videoModel->id);
            $wasAdded = true;
        }
        $this->clearPublicChannelCache($channelModel);

        return response()->json([
            'success' => true,
            'message' => $wasAdded ? 'Video assigned to On Demand Channel.' : 'Video is already assigned.',
            'data' => [
                'added' => $wasAdded,
                'assigned_count' => $channelModel->videos()->count(),
                'video' => $this->formatVideo($videoModel),
            ],
        ], $wasAdded ? 201 : 200);
    }

    public function unassignVideo(Request $request, int $channel, int $video): JsonResponse
    {
        $data = $request->validate($this->coreUserRules());
        $user = $this->access->syncUser($data);
        $channelModel = $this->ownedChannel($channel, $user->id);

        $channelModel->videos()->detach($video);
        AuthorChannelPlaylist::query()
            ->where('author_channel_id', $channelModel->id)
            ->each(fn (AuthorChannelPlaylist $playlist) => $playlist->videos()->detach($video));
        $this->clearPublicChannelCache($channelModel);

        return response()->json(['success' => true, 'message' => 'Video removed from On Demand Channel.']);
    }

    private function channelRules(bool $creating, ?int $channel = null): array
    {
        $unique = 'unique:author_channels,username'.($channel ? ','.$channel : '');

        return [
            'core_user_id' => ['required', 'integer', 'min:1'],
            'connect_user_id' => ['nullable', 'integer', 'min:1'],
            'email' => ['nullable', 'email'],
            'username' => ['nullable', 'string', 'max:190'],
            'channel_username' => [$creating ? 'nullable' : 'sometimes', 'nullable', 'string', 'max:100', 'alpha_dash', $unique],
            'first_name' => ['nullable', 'string', 'max:190'],
            'last_name' => ['nullable', 'string', 'max:190'],
            'avatar' => ['nullable', 'string', 'max:1000'],
            'phone' => ['nullable', 'string', 'max:60'],
            'name' => [$creating ? 'required' : 'sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'banner' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    private function videoRules(bool $creating): array
    {
        return [
            'core_user_id' => ['required', 'integer', 'min:1'],
            'connect_user_id' => ['nullable', 'integer', 'min:1'],
            'email' => ['nullable', 'email'],
            'username' => ['nullable', 'string', 'max:190'],
            'first_name' => ['nullable', 'string', 'max:190'],
            'last_name' => ['nullable', 'string', 'max:190'],
            'avatar' => ['nullable', 'string', 'max:1000'],
            'phone' => ['nullable', 'string', 'max:60'],
            'title' => [$creating ? 'required' : 'sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'thumbnail_url' => [$creating ? 'nullable' : 'sometimes', 'nullable', 'string', 'max:1000'],
            'poster_url' => ['nullable', 'string', 'max:1000'],
            'video_url' => [$creating ? 'nullable' : 'sometimes', 'nullable', 'string', 'max:2000'],
            'duration' => ['nullable', 'string', 'max:60'],
            'status' => [$creating ? 'required' : 'sometimes', 'string', 'in:draft,processing,publish,published,unpublished,failed'],
            'processing_message' => ['nullable', 'string', 'max:5000'],
            'release_date' => ['nullable', 'date'],
        ];
    }

    private function coreUserRules(): array
    {
        return [
            'core_user_id' => ['required', 'integer', 'min:1'],
            'connect_user_id' => ['nullable', 'integer', 'min:1'],
            'email' => ['nullable', 'email'],
            'username' => ['nullable', 'string', 'max:190'],
            'first_name' => ['nullable', 'string', 'max:190'],
            'last_name' => ['nullable', 'string', 'max:190'],
            'avatar' => ['nullable', 'string', 'max:1000'],
            'phone' => ['nullable', 'string', 'max:60'],
        ];
    }

    private function playlistRules(bool $creating): array
    {
        return array_merge($this->coreUserRules(), [
            'name' => [$creating ? 'required' : 'sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'thumbnail' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }

    private function videoPayload(array $data, int $channelId, int $userId, bool $creating = true): array
    {
        $payload = [
            'created_by' => $creating ? $userId : null,
            'updated_by' => $userId,
            'type' => 'video',
            'access' => 'free',
            'plan_id' => null,
            'video_upload_type' => 'URL',
        ];

        if ($creating || array_key_exists('status', $data)) {
            $status = $this->normalizeVideoStatus((string) ($data['status'] ?? 'draft'));
            $payload['status'] = $status === 'published' ? 1 : 0;
            $payload['processing_status'] = $status;
        }

        if (array_key_exists('title', $data)) {
            $payload['name'] = $data['title'];
            $payload['slug'] = Str::slug($data['title']).'-'.Str::lower(Str::random(6));
        }
        if (array_key_exists('description', $data)) {
            $payload['description'] = $data['description'];
            $payload['short_desc'] = Str::limit(strip_tags((string) $data['description']), 180, '');
        }
        if (array_key_exists('thumbnail_url', $data)) {
            $payload['thumbnail_url'] = $data['thumbnail_url'];
        }
        if (array_key_exists('poster_url', $data)) {
            $payload['poster_url'] = $data['poster_url'] ?: ($data['thumbnail_url'] ?? null);
        }
        if (array_key_exists('video_url', $data)) {
            $payload['video_url_input'] = $data['video_url'];
        }
        if (array_key_exists('duration', $data)) {
            $payload['duration'] = $data['duration'];
        }
        if (array_key_exists('processing_message', $data)) {
            $payload['processing_message'] = $data['processing_message'];
        }
        if (array_key_exists('release_date', $data)) {
            $payload['release_date'] = $data['release_date'];
        }

        return array_filter($payload, fn ($value) => $value !== null);
    }

    private function normalizeVideoStatus(string $status): string
    {
        return match ($status) {
            'publish', 'published' => 'published',
            'processing' => 'processing',
            'failed' => 'failed',
            default => 'draft',
        };
    }

    private function ownedChannel(int $channel, int $tvUserId): AuthorChannel
    {
        return AuthorChannel::query()->where('id', $channel)->where('user_id', $tvUserId)->firstOrFail();
    }

    private function channelPlaylist(AuthorChannel $channel, int $playlist): AuthorChannelPlaylist
    {
        return AuthorChannelPlaylist::query()
            ->where('author_channel_id', $channel->id)
            ->where('id', $playlist)
            ->firstOrFail();
    }

    private function clearPublicChannelCache(AuthorChannel $channel): void
    {
        Cache::forget("spa:ondemand:show:{$channel->username}");
        Cache::forget('spa:ondemand:index:' . md5(json_encode([
            'page' => 1,
            'per_page' => 50,
            'search' => null,
        ])));
    }

    private function reconcileProcessingVideos(AuthorChannel $channel): void
    {
        $videoIds = $channel->videos()->pluck('videos.id');
        if ($videoIds->isEmpty()) {
            return;
        }

        Video::query()
            ->whereIn('id', $videoIds)
            ->where('status', 1)
            ->whereNotNull('video_url_input')
            ->where(function ($query) {
                $query->whereNull('processing_status')
                    ->orWhere('processing_status', '!=', 'published');
            })
            ->update(['processing_status' => 'published']);

        Video::query()
            ->whereIn('id', $videoIds)
            ->where('processing_status', 'processing')
            ->whereNotNull('video_url_input')
            ->update(['processing_status' => 'published', 'status' => 1]);

        Video::query()
            ->whereIn('id', $videoIds)
            ->where('processing_status', 'processing')
            ->whereNull('video_url_input')
            ->where('updated_at', '<', now()->subMinutes(30))
            ->update(['processing_status' => 'failed']);
    }

    private function formatChannel(AuthorChannel $channel): array
    {
        return [
            'id' => (int) $channel->id,
            'name' => (string) $channel->name,
            'username' => (string) $channel->username,
            'description' => (string) ($channel->description ?? ''),
            'avatar_url' => $channel->avatar ? setBaseUrlWithFileNameV2($channel->avatar) : null,
            'banner_url' => $channel->banner ? setBaseUrlWithFileNameV2($channel->banner) : null,
            'is_active' => (bool) $channel->is_active,
            'videos_count' => (int) ($channel->videos_count ?? $channel->videos()->count()),
            'profile_url' => url('/on-demand/'.$channel->username),
        ];
    }

    private function formatVideo(Video $video): array
    {
        return [
            'id' => (int) $video->id,
            'title' => (string) $video->name,
            'name' => (string) $video->name,
            'slug' => (string) $video->slug,
            'description' => (string) ($video->description ?? ''),
            'thumbnail_url' => $video->thumbnail_url ? setBaseUrlWithFileNameV2($video->thumbnail_url) : null,
            'poster_url' => $video->poster_url ? setBaseUrlWithFileNameV2($video->poster_url) : null,
            'video_url' => $video->video_url_input,
            'duration' => $video->duration,
            'status' => $this->videoPublishingStatus($video),
            'processing_message' => $video->processing_message,
            'release_date' => $video->release_date,
            'public_url' => $video->slug ? url('/video-details/'.$video->slug) : null,
        ];
    }

    private function formatPlaylist(AuthorChannelPlaylist $playlist): array
    {
        $videos = $playlist->relationLoaded('videos') ? $playlist->videos : $playlist->videos()->get();

        return [
            'id' => (int) $playlist->id,
            'name' => (string) $playlist->name,
            'description' => (string) ($playlist->description ?? ''),
            'thumbnail' => $playlist->thumbnail,
            'thumbnail_url' => $playlist->thumbnail ? setBaseUrlWithFileNameV2($playlist->thumbnail) : null,
            'is_active' => (bool) $playlist->is_active,
            'sort_order' => (int) $playlist->sort_order,
            'video_count' => $videos->count(),
            'videos' => $videos->map(fn (Video $video) => collect($this->formatVideo($video))
                ->only(['id', 'title', 'name', 'thumbnail_url', 'poster_url', 'duration', 'status', 'public_url'])
                ->all())->values(),
        ];
    }

    private function videoPublishingStatus(Video $video): string
    {
        $status = strtolower((string) ($video->processing_status ?? ''));

        if ((int) $video->status === 1 && $video->video_url_input) {
            return 'published';
        }

        if (in_array($status, ['processing', 'failed', 'published'], true)) {
            return $status;
        }

        return 'draft';
    }
}
