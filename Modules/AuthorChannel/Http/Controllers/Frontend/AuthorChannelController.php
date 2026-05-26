<?php

namespace Modules\AuthorChannel\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\AuthorChannel;

class AuthorChannelController extends Controller
{
    public function index()
    {
        $channels = AuthorChannel::where('is_active',1)
            ->withCount('videos')
            ->orderBy('created_at', 'desc')
            ->paginate(24);
        return view('authorchannel::frontend.list', compact('channels'));
    }

    public function show($username)
    {
        $channel = AuthorChannel::with(['videos', 'user'])->where('username', $username)->firstOrFail();
        return view('authorchannel::frontend.profile', compact('channel'));
    }
}
