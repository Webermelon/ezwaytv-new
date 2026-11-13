<?php

namespace App\Http\Controllers;

use App\Services\TvContentFeedService;
use Illuminate\Http\Request;

class TvContentFeedController extends Controller
{
    public function index(Request $request, TvContentFeedService $feed)
    {
        $data = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:36'],
            'content_type' => ['nullable', 'in:video,movie,tvshow,episode,livetv'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'author' => ['nullable', 'string', 'max:255'],
            'author_id' => ['nullable', 'integer', 'min:1'],
            'channel_id' => ['nullable', 'integer', 'min:1'],
            'channel_name' => ['nullable', 'string', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'id' => ['nullable', 'integer', 'min:1'],
            'access' => ['nullable', 'in:free,paid,pay-per-view'],
        ]);

        return response()->json($feed->page(
            (int) ($data['page'] ?? 1),
            (int) ($data['per_page'] ?? 12),
            (bool) getCurrentProfileSession('is_child_profile'),
            $data,
        ));
    }
}
