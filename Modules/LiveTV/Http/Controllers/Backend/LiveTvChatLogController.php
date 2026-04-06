<?php

namespace Modules\LiveTV\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\LiveTV\Models\LiveTvChannel;
use Modules\LiveTV\Models\LiveTvChatMessage;

class LiveTvChatLogController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->input('search', ''));
        $channelId = $request->input('channel_id');

        $logs = LiveTvChatMessage::query()
            ->with(['channel' => function ($query) {
                $query->withTrashed();
            }])
            ->when($channelId, function ($query) use ($channelId) {
                $query->where('live_tv_channel_id', $channelId);
            })
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($nested) use ($search) {
                    $nested->where('guest_name', 'like', '%' . $search . '%')
                        ->orWhere('message', 'like', '%' . $search . '%')
                        ->orWhereHas('channel', function ($channelQuery) use ($search) {
                            $channelQuery->withTrashed()->where('name', 'like', '%' . $search . '%');
                        });
                });
            })
            ->latest('id')
            ->paginate((int) setting('data_table_limit', 20))
            ->withQueryString();

        $channels = LiveTvChannel::withTrashed()->orderBy('name')->get(['id', 'name']);

        $module_title = 'settings.title';

        return view('setting::backend.setting.section-pages.live-chat-logs', compact('logs', 'channels', 'module_title'));
    }

    public function destroy(int $id): RedirectResponse
    {
        $log = LiveTvChatMessage::findOrFail($id);
        $log->delete();

        return redirect()
            ->route('backend.settings.live-chat-logs.index', request()->query())
            ->with('success', 'Live chat log deleted successfully.');
    }
}