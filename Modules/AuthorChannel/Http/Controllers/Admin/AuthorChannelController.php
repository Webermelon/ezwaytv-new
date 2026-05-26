<?php

namespace Modules\AuthorChannel\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\AuthorChannel;
use Modules\Video\Models\Video;

class AuthorChannelController extends Controller
{
    public function index()
    {
        $channels = AuthorChannel::paginate(20);
        return view('authorchannel::admin.index', compact('channels'));
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
        return redirect()->route('backend.author_channels.index')->with('success','Channel created');
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
        return redirect()->route('backend.author_channels.index')->with('success','Channel updated');
    }

    public function destroy($id)
    {
        $channel = AuthorChannel::findOrFail($id);
        $channel->delete();
        return redirect()->route('backend.author_channels.index')->with('success','Channel deleted');
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
            ->with('success', 'Video assigned to channel.');
    }

    /**
     * Remove a video assignment from this channel.
     */
    public function unassignVideo($id, $videoId)
    {
        $channel = AuthorChannel::findOrFail($id);
        $channel->videos()->detach((int) $videoId);

        return redirect()->route('backend.author_channels.edit', $id)
            ->with('success', 'Video removed from channel.');
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
