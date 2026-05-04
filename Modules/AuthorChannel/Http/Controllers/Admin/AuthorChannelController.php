<?php

namespace Modules\AuthorChannel\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\AuthorChannel;

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
            'description' => 'nullable|string',
            'user_id'     => 'nullable|integer|exists:users,id',
            'avatar'      => 'nullable|string|max:500',
            'banner'      => 'nullable|string|max:500',
            'is_active'   => 'nullable|boolean',
        ]);
        AuthorChannel::create($data);
        return redirect()->route('backend.author_channels.index')->with('success','Channel created');
    }

    public function edit($id)
    {
        $channel = AuthorChannel::with('videos')->findOrFail($id);
        return view('authorchannel::admin.edit', compact('channel'));
    }

    public function update(Request $request, $id)
    {
        $channel = AuthorChannel::findOrFail($id);
        $data = $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
            'user_id'     => 'nullable|integer|exists:users,id',
            'avatar'      => 'nullable|string|max:500',
            'banner'      => 'nullable|string|max:500',
            'is_active'   => 'nullable|boolean',
        ]);
        $channel->update($data);
        return redirect()->route('backend.author_channels.index')->with('success','Channel updated');
    }

    public function destroy($id)
    {
        $channel = AuthorChannel::findOrFail($id);
        $channel->delete();
        return redirect()->route('backend.author_channels.index')->with('success','Channel deleted');
    }
}
