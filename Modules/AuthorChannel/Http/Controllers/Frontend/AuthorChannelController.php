<?php

namespace Modules\AuthorChannel\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\AuthorChannel;

class AuthorChannelController extends Controller
{
    public function index()
    {
        $channels = AuthorChannel::where('is_active',1)->paginate(24);
        return view('authorchannel::frontend.list', compact('channels'));
    }

    public function show($id)
    {
        $channel = AuthorChannel::with('videos')->findOrFail($id);
        return view('authorchannel::frontend.profile', compact('channel'));
    }
}
