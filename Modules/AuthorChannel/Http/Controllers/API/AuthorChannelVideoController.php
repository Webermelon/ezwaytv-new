<?php

namespace Modules\AuthorChannel\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\AuthorChannel;
use Modules\Video\Models\Video;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthorChannelVideoController extends Controller
{
    /**
     * List videos assigned to a channel.
     * GET /api/author-channels/{id}/videos
     */
    public function index($id)
    {
        $channel = AuthorChannel::where('is_active', 1)->findOrFail($id);

        $videos = $channel->videos()
            ->whereNull('videos.deleted_at')
            ->select('videos.id', 'videos.name', 'videos.thumbnail_url', 'videos.poster_url', 'videos.type', 'videos.status')
            ->get()
            ->map(function ($v) {
                $img = $v->thumbnail_url ?: $v->poster_url;
                return [
                    'id'            => $v->id,
                    'name'          => $v->name,
                    'thumbnail_url' => $img ? setBaseUrlWithFileNameV2($img) : null,
                    'type'          => $v->type,
                    'status'        => $v->status,
                ];
            });

        return response()->json(['data' => $videos]);
    }

    /**
     * Assign an existing video to a channel.
     * POST /api/author-channels/{id}/videos/assign
     * Body: { "video_id": 123 }
     */
    public function assign(Request $request, $id)
    {
        $channel = AuthorChannel::findOrFail($id);

        // Ensure authenticated user owns this channel (or is admin)
        $user = Auth::user();
        if ($channel->user_id && $channel->user_id !== $user->id && !$user->hasRole('admin')) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $request->validate(['video_id' => 'required|integer|exists:videos,id']);

        $videoId = (int) $request->input('video_id');
        $video   = Video::findOrFail($videoId);

        // Only allow if the video belongs to the user (or admin)
        if ($video->created_by && $video->created_by !== $user->id && !$user->hasRole('admin')) {
            return response()->json(['message' => 'You can only assign your own videos.'], 403);
        }

        if (!$channel->videos()->where('video_id', $videoId)->exists()) {
            $channel->videos()->attach($videoId);
        }

        return response()->json(['message' => 'Video assigned successfully.', 'video_id' => $videoId]);
    }

    /**
     * Remove a video from a channel.
     * DELETE /api/author-channels/{id}/videos/{videoId}
     */
    public function unassign($id, $videoId)
    {
        $channel = AuthorChannel::findOrFail($id);

        $user = Auth::user();
        if ($channel->user_id && $channel->user_id !== $user->id && !$user->hasRole('admin')) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $channel->videos()->detach((int) $videoId);

        return response()->json(['message' => 'Video removed from channel.']);
    }
}
