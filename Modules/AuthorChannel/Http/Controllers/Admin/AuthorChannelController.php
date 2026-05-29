<?php

namespace Modules\AuthorChannel\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Traits\ModuleTrait;
use Illuminate\Http\Request;
use App\Models\AuthorChannel;
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
            ->rawColumns(['check', 'image', 'status', 'action'])
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
        return view('authorchannel::admin.create');
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
        ]);
        if (empty($data['username'])) {
            $data['username'] = AuthorChannel::generateUsername($data['name']);
        }
        AuthorChannel::create($data);
        return redirect()->route('backend.author_channels.index')->with('success','On Demand Channel created');
    }

    public function edit($id)
    {
        $channel = AuthorChannel::with('videos')->findOrFail($id);

        $assignedIds = $channel->videos->pluck('id')->toArray();
        $availableVideos = Video::whereNotIn('id', $assignedIds)
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->select('id', 'name')
            ->get();

        return view('authorchannel::admin.edit', compact('channel', 'availableVideos'));
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
        ]);
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
        if (!$channel->videos()->where('video_id', $videoId)->exists()) {
            $channel->videos()->attach($videoId);
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

        return redirect()->route('backend.author_channels.edit', $id)
            ->with('success', 'Video removed from On Demand Channel.');
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

        $videos = $query->orderBy('name')->limit(50)->get()->map(function ($v) {
            return [
                'id'   => $v->id,
                'text' => $v->name,
            ];
        });

        return response()->json(['results' => $videos]);
    }
}
