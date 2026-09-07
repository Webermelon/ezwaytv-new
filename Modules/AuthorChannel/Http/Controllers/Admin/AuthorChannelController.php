<?php

namespace Modules\AuthorChannel\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Traits\ModuleTrait;
use Illuminate\Http\Request;
use App\Models\AuthorChannel;
use App\Models\AuthorChannelPlaylist;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Modules\Subscriptions\Models\Plan;
use Modules\Video\Models\Video;
use Yajra\DataTables\DataTables;

class AuthorChannelController extends Controller
{
    use ModuleTrait;

    public function __construct()
    {
        $this->initializeModuleTrait('On Demand Channels', 'author_channels', 'ph-television');
    }

    public function index()
    {
        return view('authorchannel::admin.index');
    }

    public function index_data(DataTables $datatable, Request $request)
    {
        $query = AuthorChannel::query()->withTrashed()->with('user');

        $filter = $request->filter;
        if (isset($filter['column_status']) && $filter['column_status'] !== '') {
            $query->where('is_active', $filter['column_status']);
        }
        if (!empty($filter['name'])) {
            $query->where('name', 'like', '%' . $filter['name'] . '%');
        }

        return $datatable->eloquent($query)
            ->addColumn('check', function ($data) {
                return '<input type="checkbox" class="form-check-input select-table-row" id="datatable-row-' . $data->id . '" name="datatable_ids[]" value="' . $data->id . '" data-type="authorchannel" onclick="dataTableRowCheck(' . $data->id . ',this)">';
            })
            ->editColumn('image', function ($data) {
                $imageUrl = $data->avatar ? setBaseUrlWithFileNameV2($data->avatar) : asset('images/default-avatar.png');
                return view('components.media-item', ['thumbnail' => $imageUrl, 'name' => $data->name, 'type' => 'authorchannel'])->render();
            })
            ->addColumn('username_col', fn($data) => '@' . $data->username)
            ->addColumn('videos_count', fn($data) => $data->videos()->count())
            ->addColumn('access_col', function ($data) {
                $access = $data->access === 'paid' ? 'Paid' : 'Free';
                $class = $data->access === 'paid' ? 'bg-warning-subtle text-warning' : 'bg-success-subtle text-success';

                return '<span class="badge ' . $class . '">' . $access . '</span>';
            })
            ->editColumn('status', function ($data) {
                $checked  = $data->is_active ? 'checked="checked"' : '';
                $disabled = $data->trashed() ? 'disabled' : '';
                return '
                    <div class="form-check form-switch">
                        <input type="checkbox" data-url="' . route('backend.author_channels.update_status', $data->id) . '"
                               data-token="' . csrf_token() . '" class="switch-status-change form-check-input"
                               id="datatable-row-' . $data->id . '" name="status" value="' . $data->id . '"
                               ' . $checked . ' ' . $disabled . '>
                    </div>
                ';
            })
            ->addColumn('action', function ($data) {
                return view('authorchannel::admin.action', compact('data'))->render();
            })
            ->editColumn('updated_at', fn($data) => $data->updated_at ? $data->updated_at->diffForHumans() : '-')
            ->rawColumns(['check', 'image', 'access_col', 'status', 'action'])
            ->orderColumns(['id'], '-:column $1')
            ->make(true);
    }

    public function bulk_action(Request $request)
    {
        $ids        = explode(',', $request->rowIds);
        $actionType = $request->action_type;

        if ($actionType === 'change-status') {
            AuthorChannel::withoutGlobalScopes()->whereIn('id', $ids)
                ->update(['is_active' => (int) $request->status]);
            return response()->json(['status' => true, 'message' => __('messages.status_updated')]);
        }

        return $this->performBulkAction(AuthorChannel::class, $ids, $actionType, 'On Demand Channel');
    }

    public function update_status(Request $request, $id)
    {
        $channel = AuthorChannel::findOrFail($id);
        $channel->update(['is_active' => $request->status ? 1 : 0]);
        return response()->json(['status' => true, 'message' => __('messages.status_updated')]);
    }

    public function restore($id)
    {
        $channel = AuthorChannel::withTrashed()->findOrFail($id);
        $channel->restore();
        return response()->json(['message' => 'On Demand Channel restored.', 'status' => true], 200);
    }

    public function forceDelete($id)
    {
        $channel = AuthorChannel::withTrashed()->findOrFail($id);
        $channel->forceDelete();
        return response()->json(['message' => 'On Demand Channel permanently deleted.', 'status' => true], 200);
    }

    public function create()
    {
        $plans = Plan::where('status', 1)->orderBy('level')->orderBy('name')->get();

        return view('authorchannel::admin.create', compact('plans'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'        => 'required|string|max:255',
            'username'    => 'nullable|string|max:100|alpha_dash|unique:author_channels,username',
            'description' => 'nullable|string',
            'user_id'     => 'nullable|integer|exists:users,id',
            'avatar'      => 'nullable|string|max:500',
            'banner'      => 'nullable|string|max:500',
            'is_active'   => 'nullable|boolean',
            'access'      => ['required', Rule::in(['free', 'paid'])],
            'plan_id'     => 'nullable|required_if:access,paid|integer|exists:plan,id',
        ]);
        if (($data['access'] ?? 'free') === 'free') {
            $data['plan_id'] = null;
        }
        if (empty($data['username'])) {
            $data['username'] = AuthorChannel::generateUsername($data['name']);
        }
        AuthorChannel::create($data);
        return redirect()->route('backend.author_channels.index')->with('success','On Demand Channel created');
    }

    public function edit($id)
    {
        $channel = AuthorChannel::with([
            'videos',
            'playlists.videos:id,name,thumbnail_url,poster_url,duration',
            'plan',
        ])->findOrFail($id);
        $plans = Plan::where('status', 1)->orderBy('level')->orderBy('name')->get();

        $assignedIds = $channel->videos->pluck('id')->toArray();
        $availableVideos = Video::whereNotIn('id', $assignedIds)
            ->whereNull('deleted_at')
            ->orderByDesc('updated_at')
            ->orderByDesc('created_at')
            ->select('id', 'name')
            ->get();

        return view('authorchannel::admin.edit', compact('channel', 'availableVideos', 'plans'));
    }

    public function update(Request $request, $id)
    {
        $channel = AuthorChannel::findOrFail($id);
        $data = $request->validate([
            'name'        => 'required|string|max:255',
            'username'    => 'nullable|string|max:100|alpha_dash|unique:author_channels,username,' . $id,
            'description' => 'nullable|string',
            'user_id'     => 'nullable|integer|exists:users,id',
            'avatar'      => 'nullable|string|max:500',
            'banner'      => 'nullable|string|max:500',
            'is_active'   => 'nullable|boolean',
            'access'      => ['required', Rule::in(['free', 'paid'])],
            'plan_id'     => 'nullable|required_if:access,paid|integer|exists:plan,id',
        ]);
        if (($data['access'] ?? 'free') === 'free') {
            $data['plan_id'] = null;
        }
        if (empty($data['username'])) {
            $data['username'] = AuthorChannel::generateUsername($data['name'], $id);
        }
        $channel->update($data);
        return redirect()->route('backend.author_channels.index')->with('success','On Demand Channel updated');
    }

    public function destroy($id)
    {
        $channel = AuthorChannel::findOrFail($id);
        $channel->delete();
        return response()->json(['status' => true, 'message' => 'On Demand Channel deleted.']);
    }

    // Legacy - kept for compatibility
    public function toggleStatus($id)
    {
        $channel = AuthorChannel::findOrFail($id);
        $channel->update(['is_active' => !$channel->is_active]);
        return redirect()->route('backend.author_channels.index')->with('success','On Demand Channel status updated');
    }

    /**
     * Assign an existing video to this channel.
     */
    public function assignVideo(Request $request, $id)
    {
        $channel = AuthorChannel::findOrFail($id);

        $request->validate([
            'video_id' => 'required|integer|exists:videos,id',
        ]);

        $videoId = (int) $request->input('video_id');

        // Only attach if not already attached
        $wasAdded = false;
        if (!$channel->videos()->where('video_id', $videoId)->exists()) {
            $channel->videos()->attach($videoId);
            $wasAdded = true;
        }
        $this->clearPublicChannelCache($channel);

        if ($request->expectsJson()) {
            $video = Video::select('id', 'name', 'thumbnail_url', 'poster_url', 'duration')->findOrFail($videoId);
            $thumbValue = $video->thumbnail_url ?: $video->poster_url;

            return response()->json([
                'success' => true,
                'added' => $wasAdded,
                'message' => $wasAdded ? 'Video assigned to On Demand Channel.' : 'Video is already assigned.',
                'assigned_count' => $channel->videos()->count(),
                'video' => [
                    'id' => (int) $video->id,
                    'name' => $video->name,
                    'duration' => $video->duration,
                    'thumbnail' => $thumbValue ? setBaseUrlWithFileNameV2($thumbValue) : asset('default-image/Default-Image.jpg'),
                    'edit_url' => route('backend.videos.edit', $video->id),
                    'unassign_url' => route('backend.author_channels.videos.unassign', [$channel->id, $video->id]),
                ],
            ]);
        }

        return redirect()->route('backend.author_channels.edit', $id)
            ->with('success', 'Video assigned to On Demand Channel.');
    }

    /**
     * Remove a video assignment from this channel.
     */
    public function unassignVideo($id, $videoId)
    {
        $channel = AuthorChannel::findOrFail($id);
        $channel->videos()->detach((int) $videoId);
        AuthorChannelPlaylist::where('author_channel_id', $channel->id)
            ->each(fn ($playlist) => $playlist->videos()->detach((int) $videoId));
        $this->clearPublicChannelCache($channel);

        return redirect()->route('backend.author_channels.edit', $id)
            ->with('success', 'Video removed from On Demand Channel.');
    }

    public function storePlaylist(Request $request, $id)
    {
        $channel = AuthorChannel::findOrFail($id);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'thumbnail' => 'nullable|string|max:500',
            'is_active' => 'nullable|boolean',
        ]);

        $channel->playlists()->create([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'thumbnail' => $data['thumbnail'] ?? null,
            'sort_order' => (int) $channel->playlists()->max('sort_order') + 1,
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);
        $this->clearPublicChannelCache($channel);

        return redirect()->route('backend.author_channels.edit', $id)
            ->with('success', 'Playlist created.');
    }

    public function updatePlaylist(Request $request, $id, $playlistId)
    {
        $playlist = AuthorChannelPlaylist::where('author_channel_id', $id)->findOrFail($playlistId);
        $channel = $playlist->channel;

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'thumbnail' => 'nullable|string|max:500',
            'is_active' => 'nullable|boolean',
        ]);

        $playlist->update([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'thumbnail' => $data['thumbnail'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? false),
        ]);

        if ($channel) {
            $this->clearPublicChannelCache($channel);
        }

        return redirect()->route('backend.author_channels.edit', $id)
            ->with('success', 'Playlist updated.');
    }

    public function addPlaylistVideo(Request $request, $id, $playlistId)
    {
        $channel = AuthorChannel::with('videos:id')->findOrFail($id);
        $playlist = AuthorChannelPlaylist::where('author_channel_id', $channel->id)->findOrFail($playlistId);
        $assignedVideoIds = $channel->videos->pluck('id')->map(fn ($videoId) => (int) $videoId)->all();

        $data = $request->validate([
            'video_id' => ['required', 'integer', Rule::in($assignedVideoIds)],
        ]);

        $videoId = (int) $data['video_id'];
        $wasAdded = false;
        if (!$playlist->videos()->where('videos.id', $videoId)->exists()) {
            $playlist->videos()->attach($videoId, [
                'sort_order' => $playlist->videos()->count() + 1,
            ]);
            $wasAdded = true;
        }
        $this->clearPublicChannelCache($channel);

        if ($request->expectsJson()) {
            $video = Video::select('id', 'name', 'thumbnail_url', 'poster_url', 'duration')->findOrFail($videoId);
            $thumbValue = $video->thumbnail_url ?: $video->poster_url;

            return response()->json([
                'success' => true,
                'added' => $wasAdded,
                'message' => $wasAdded ? 'Video added to playlist.' : 'Video is already in this playlist.',
                'playlist_id' => (int) $playlist->id,
                'playlist_count' => $playlist->videos()->count(),
                'video' => [
                    'id' => (int) $video->id,
                    'name' => $video->name,
                    'duration' => $video->duration,
                    'thumbnail' => $thumbValue ? setBaseUrlWithFileNameV2($thumbValue) : asset('default-image/Default-Image.jpg'),
                    'remove_url' => route('backend.author_channels.playlists.videos.remove', [$channel->id, $playlist->id, $video->id]),
                ],
            ]);
        }

        return redirect()->route('backend.author_channels.edit', $id)
            ->with('success', 'Video added to playlist.');
    }

    public function removePlaylistVideo($id, $playlistId, $videoId)
    {
        $playlist = AuthorChannelPlaylist::where('author_channel_id', $id)->findOrFail($playlistId);
        $channel = $playlist->channel;
        $playlist->videos()->detach((int) $videoId);
        if ($channel) {
            $this->clearPublicChannelCache($channel);
        }

        return redirect()->route('backend.author_channels.edit', $id)
            ->with('success', 'Video removed from playlist.');
    }

    public function reorderPlaylistVideos(Request $request, $id, $playlistId)
    {
        $playlist = AuthorChannelPlaylist::where('author_channel_id', $id)->findOrFail($playlistId);
        $channel = $playlist->channel;

        $data = $request->validate([
            'video_ids' => 'required|array',
            'video_ids.*' => 'integer',
        ]);

        $attachedIds = $playlist->videos()->pluck('videos.id')->map(fn ($videoId) => (int) $videoId)->all();
        $orderedIds = collect($data['video_ids'])
            ->map(fn ($videoId) => (int) $videoId)
            ->filter(fn ($videoId) => in_array($videoId, $attachedIds, true))
            ->unique()
            ->values();

        foreach ($orderedIds as $index => $videoId) {
            $playlist->videos()->updateExistingPivot($videoId, ['sort_order' => $index + 1]);
        }

        if ($channel) {
            $this->clearPublicChannelCache($channel);
        }

        return response()->json(['status' => true, 'message' => 'Playlist order updated.']);
    }

    public function destroyPlaylist($id, $playlistId)
    {
        $playlist = AuthorChannelPlaylist::where('author_channel_id', $id)->findOrFail($playlistId);
        $channel = $playlist->channel;
        $playlist->videos()->detach();
        $playlist->delete();
        if ($channel) {
            $this->clearPublicChannelCache($channel);
        }

        if (request()->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Playlist deleted.',
                'playlist_id' => (int) $playlistId,
                'playlist_count' => AuthorChannelPlaylist::where('author_channel_id', $id)->count(),
            ]);
        }

        return redirect()->route('backend.author_channels.edit', $id)
            ->with('success', 'Playlist deleted.');
    }

    /**
     * Return all videos not yet assigned to this channel (for select2 AJAX).
     */
    public function availableVideos(Request $request, $id)
    {
        $channel = AuthorChannel::findOrFail($id);
        $assignedIds = $channel->videos()->pluck('video_id')->toArray();

        $query = Video::whereNotIn('id', $assignedIds)
            ->whereNull('deleted_at')
            ->select('id', 'name', 'thumbnail_url', 'poster_url');

        if ($search = $request->input('q')) {
            $query->where('name', 'like', '%' . $search . '%');
        }

        $videos = $query->orderByDesc('updated_at')->orderByDesc('created_at')->limit(50)->get()->map(function ($v) {
            return [
                'id'   => $v->id,
                'text' => $v->name,
            ];
        });

        return response()->json(['results' => $videos]);
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
}
