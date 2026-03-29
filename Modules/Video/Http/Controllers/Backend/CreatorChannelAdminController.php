<?php

namespace Modules\Video\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Video\Models\CreatorChannel;
use Modules\Video\Models\Video;
use App\Models\User;

class CreatorChannelAdminController extends Controller
{
    /**
     * GET /app/creator-channels
     * List all creator channels for admin.
     */
    public function index(Request $request)
    {
        $module_title  = 'Creator Channels';
        $module_name   = 'creator-channels';
        $module_icon   = 'fa-solid fa-tv';
        $module_action = 'List';

        $channels = CreatorChannel::withTrashed()
            ->with(['owner:id,first_name,last_name,email'])
            ->withCount('videos')
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        return view('video::backend.creator-channel.index', compact(
            'module_title', 'module_name', 'module_icon', 'module_action', 'channels'
        ));
    }

    /**
     * GET /app/creator-channels/{id}
     * Show single channel with its videos.
     */
    public function show(int $id)
    {
        $channel = CreatorChannel::withTrashed()
            ->with(['owner:id,first_name,last_name,email'])
            ->withCount('videos')
            ->findOrFail($id);

        $channel->poster_url = setBaseUrlWithFileName($channel->poster_url, 'image', 'creator');
        $channel->banner_url = setBaseUrlWithFileName($channel->banner_url, 'image', 'creator');

        $videos = Video::where('creator_channel_id', $id)
            ->select(['id', 'name', 'slug', 'status', 'access', 'duration', 'release_date', 'poster_url'])
            ->orderByDesc('created_at')
            ->paginate(20);

        $module_title  = 'Creator Channel: ' . $channel->name;
        $module_name   = 'creator-channels';
        $module_icon   = 'fa-solid fa-tv';
        $module_action = 'Show';

        return view('video::backend.creator-channel.show', compact(
            'module_title', 'module_name', 'module_icon', 'module_action', 'channel', 'videos'
        ));
    }

    /**
     * POST /app/creator-channels/{id}/toggle-status
     * Toggle active/inactive status.
     */
    public function toggleStatus(int $id)
    {
        $channel = CreatorChannel::findOrFail($id);
        $channel->update(['status' => ! $channel->status]);

        return back()->with('success', 'Channel status updated.');
    }

    /**
     * DELETE /app/creator-channels/{id}
     * Soft-delete a channel.
     */
    public function destroy(int $id)
    {
        $channel = CreatorChannel::findOrFail($id);
        $channel->delete();

        return back()->with('success', 'Channel deleted.');
    }

    /**
     * POST /app/creator-channels/{id}/restore
     * Restore a soft-deleted channel.
     */
    public function restore(int $id)
    {
        $channel = CreatorChannel::withTrashed()->findOrFail($id);
        $channel->restore();

        return back()->with('success', 'Channel restored.');
    }
}
