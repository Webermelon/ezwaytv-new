<?php

namespace Modules\Frontend\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Modules\Banner\Models\Banner;
use Modules\Banner\Transformers\Backend\SliderResourceV3;
use Modules\LiveTV\Models\LiveTvCategory;
use Modules\LiveTV\Models\LiveTvChannel;
use Modules\LiveTV\Models\LiveTvChatMessage;
use Modules\LiveTV\Transformers\LiveTvCategoryResource;
use Modules\LiveTV\Transformers\Backend\LiveTvChannelResourceV3;
use Modules\LiveTV\Transformers\LiveTvChannelDetailsResource;

class LiveTvController extends Controller
{
    public function livetvList()
    {
        $channelData = LiveTvChannel::with('TvCategory', 'plan', 'TvChannelStreamContentMappings')
            ->where('status', 1)
            ->orderBy('updated_at', 'desc')
            ->take(6)
            ->get();

        $categoryData = LiveTvCategory::with('tvChannels')
            ->where('status', 1)
            ->orderBy('updated_at', 'desc')
            ->get();

        $user_id = Auth::id();

        $featured_livetv_banners = Banner::where('banner_for', 'livetv')
            ->where('status', 1)
            ->limit(5)
            ->get();

        $featured_livetvs = $featured_livetv_banners->map(function ($banner) use ($user_id) {
            return (new SliderResourceV3($banner, $user_id))->toArray(request());
        })->values()->all();

        $responseData['category_data'] = LiveTvCategoryResource::collection($categoryData)->toArray(request());

        return view('frontend::livetv', compact('responseData', 'featured_livetvs'));
    }

    public function liveTvDetails(string $id)
    {
        $livetvId = $id;
        $userId = Auth::id();

        $livetvGuard = LiveTvChannel::where('slug', $livetvId)->first();
        if (empty($livetvGuard) || (int) ($livetvGuard->status) !== 1 || $livetvGuard->deleted_at !== null) {
            return redirect()->route('user.login');
        }

        $livetv = LiveTvChannel::where('slug', '=', $livetvId)
            ->with('TvCategory', 'plan', 'TvChannelStreamContentMappings')
            ->first();

        $suggestions = LiveTvChannel::where('category_id', $livetv->category_id)
            ->where('slug', '!=', $livetvId)
            ->where('status', 1)
            ->whereNull('deleted_at')
            ->with('TvCategory')
            ->get();

        $suggestions = LiveTvChannelResourceV3::collection($suggestions)->toArray(request());

        $data = (new LiveTvChannelDetailsResource($livetv))->toArray(request());

        if (!empty($livetv->TvChannelStreamContentMappings['server_url'])) {
            $data['server_url'] = Crypt::encryptString($livetv->TvChannelStreamContentMappings['server_url']);
        }

        $chatMessages = [];
        $chatEnabled = ((int) (GetSettingValue('live_tv_chat_enabled') ?? 1) === 1) && !empty($data['enable_live_chat']);

        if ($chatEnabled) {
            $chatMessages = LiveTvChatMessage::query()
                ->where('live_tv_channel_id', $livetv->id)
                ->latest('id')
                ->take(50)
                ->get()
                ->reverse()
                ->values()
                ->map(function (LiveTvChatMessage $message) {
                    return [
                        'id'         => $message->id,
                        'guest_name' => $message->guest_name,
                        'message'    => $message->message,
                        'time'       => optional($message->created_at)->diffForHumans(),
                        'created_at' => optional($message->created_at)->toIso8601String(),
                    ];
                })
                ->all();
        }

        $data['enable_live_chat'] = $chatEnabled;
        $chatGuestName          = null;
        $chatIdentityType       = auth()->check() ? 'user' : 'guest';
        $chatCanChangeName      = !auth()->check();
        $chatIsIdentifiedGuest  = false;

        if (auth()->check()) {
            $chatGuestName = trim((string) auth()->user()->full_name) ?: ('User ' . auth()->id());
        } else {
            $cookiePayload = request()->cookie('livetv_chat_identity');
            $identity      = is_string($cookiePayload) ? json_decode($cookiePayload, true) : null;
            $cookieName    = trim((string) ($identity['display_name'] ?? ''));

            if ($cookieName !== '') {
                $chatGuestName         = $cookieName;
                $chatIsIdentifiedGuest = true;
            } elseif ($chatEnabled) {
                $chatGuestName = 'Anonymous';
            }
        }

        // Create entertainment object for SEO/OG meta tags
        $entertainment = (object) [
            'seo_image' => $data['poster_image'] ?? null,
            'meta_title' => $data['name'] ?? null,
            'short_description' => $data['description'] ?? null,
            'google_site_verification' => null,
            'canonical_url' => route('livetv-details', ['id' => $data['slug']]),
            'meta_keywords' => null,
        ];

        return view('frontend::livetvDetail', compact(
            'data',
            'suggestions',
            'chatMessages',
            'chatGuestName',
            'chatIdentityType',
            'chatCanChangeName',
            'chatIsIdentifiedGuest',
            'entertainment'
        ));
    }

    public function livetvChannelsList(Request $request, $id)
    {
        $category = LiveTvCategory::where('slug', $id)->first()
            ?? LiveTvCategory::find($id);

        $categoryName  = $category ? $category->name : __('frontend.tv_channels');
        $tvcategory_id = $category ? $category->id : $id;

        return view('frontend::tvchannelList', compact('categoryName', 'tvcategory_id'));
    }
}
