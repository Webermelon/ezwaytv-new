<?php

namespace Modules\LiveTV\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\Request;
use Modules\LiveTV\Models\LiveTvCategory;
use Modules\LiveTV\Transformers\LiveTvCategoryResource;
use Modules\LiveTV\Models\LiveTvChannel;
use Modules\LiveTV\Transformers\LiveTvCategoryResourceV2;
use Modules\LiveTV\Transformers\LiveTvCategoryResourceV3;
use Modules\LiveTV\Transformers\LiveTvChannelResource;
use Modules\LiveTV\Transformers\LiveTvChannelResourceV3;
use Modules\LiveTV\Transformers\LiveTvChannelDetailsResource;
use Modules\LiveTV\Transformers\LiveTvChannelDetailsResourceV3;
use Modules\Subscriptions\Models\Subscription;
use Modules\Frontend\Models\PayPerView;
use App\Models\MobileSetting;
use Modules\Banner\Models\Banner;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class LiveTVsController extends Controller
{
    public function liveTvCategoryList(Request $request){

        $perPage = $request->input('per_page', 10);
        $category_list = LiveTvCategory::query();

        if ($request->has('search')) {
            $searchTerm = $request->search;
            $category_list->where(function ($query) use ($searchTerm) {
                $query->where('name', 'like', "%{$searchTerm}%");
            });
        }

        $category_list =$category_list->where('status',1);

        $category = $category_list->orderBy('updated_at', 'desc');
        $category = $category->paginate($perPage);

        $responseData = LiveTvCategoryResource::collection($category);

        return ApiResponse::success($responseData, __('livetv.livetv_category_list'), 200);
    }

    public function liveTvDashboard(Request $request){

        $channelData = LiveTvChannel::with('TvCategory','plan','TvChannelStreamContentMappings')->where('status',1)->featuredFirst()->orderBy('updated_at', 'desc')->take(6)->get();
        $categoryData = LiveTvCategory::with('tvChannels')->where('status',1)->orderBy('updated_at', 'desc')->get();

        $responseData['slider'] = LiveTvChannelResource::collection($channelData);
        $responseData['category_data'] = LiveTvCategoryResource::collection($categoryData);

        return ApiResponse::success($responseData, __('livetv.livetv_dashboard'), 200);
    }

    public function liveTvDetails(Request $request){

        $channelData = LiveTvChannel::where('id', $request->channel_id)->with('TvCategory','plan','TvChannelStreamContentMappings')->first();
        if ($channelData) {
            $this->attachLiveTvStats(collect([$channelData]));
        }

        $responseData = new LiveTvChannelDetailsResource($channelData);

        return ApiResponse::success($responseData, __('livetv.livetv_details'), 200);
    }


    public function liveTvDetailsV3(Request $request){

        $device_type = getDeviceType($request);

        $channelId = $request->channel_id ?? $request->id;
        $userId = $request->user_id ?? auth()->id();

        $cacheVersion = Cache::get('livetv_dashboard_cache_version', 0);
        $cacheKey = 'livetv_details_v4_schedule_status_'. md5(json_encode([
            'channel_id' => $channelId,
            'user_id' => $userId,
            'device_type' => $device_type,
            'cache_version' => $cacheVersion,
        ]));

        $cachedResult = cacheApiResponse($cacheKey, 300, function () use ($request, $channelId, $userId, $device_type) {
           $channelData = LiveTvChannel::query()
               ->with('TvCategory','plan','TvChannelStreamContentMappings')
               ->where(function ($query) use ($channelId) {
                   if (is_numeric($channelId)) {
                       $query->where('id', (int) $channelId);
                   }

                   $query->orWhere('slug', $channelId);
               })
               ->first();

           if (! $channelData) {
               return null;
           }

           $channelData['video_qualities'] = $channelData ? [[
                    'url_type' => $channelData['TvChannelStreamContentMappings']['stream_type'],
                    'url'      => $channelData['TvChannelStreamContentMappings']['stream_type'] == 'Embedded' ? $channelData['TvChannelStreamContentMappings']['embedded'] : $channelData['TvChannelStreamContentMappings']['server_url'],
                ]] : [];
            if ($userId) {
                $getDeviceTypeData = Subscription::checkPlanSupportDevice($userId, $device_type);
                $deviceTypeResponse = json_decode($getDeviceTypeData->getContent(), true);
                $userLevel = Subscription::select('plan_id')->where(['user_id' => $userId, 'status' => 'active'])->latest()->first();
                $userPlanId = $userLevel->plan_id ?? 0;
                $channelData = setContentAccess($channelData, $userId, $userPlanId);

            } else {
                $deviceTypeResponse = ['isDeviceSupported' => false];
                $userPlanId = 0;
                $channelData = setContentAccess($channelData, null, $userPlanId);
            }

            $channelData['isDeviceSupported'] = $deviceTypeResponse['isDeviceSupported'] == true ? 1 : 0;

            $channelData['poster_image'] =  $device_type == 'tv' ? $channelData->poster_tv_url : $channelData->poster_url ?? null;
            $this->attachLiveTvStats(collect([$channelData]));
            // Get more items and apply setContentAccess to each
            $moreItems = LiveTvChannel::where('category_id', $channelData->category_id)->where('deleted_at', null)->where('status',1)->featuredFirst()->get()->except($channelData->id);

            // Apply setContentAccess to each item in moreItems
            $moreItems = $moreItems->map(function ($item) use ($userId, $userPlanId, $deviceTypeResponse, $device_type) {
                $itemData = [
                    'id' => $item->id,
                    'access' => $item->access,
                    'plan_id' => $item->plan_id,
                ];
                $itemData = setContentAccess($itemData, $userId, $userPlanId);

                // Add the processed data to the item
                $item->has_content_access = $itemData['has_content_access'];
                $item->required_plan_level = $itemData['required_plan_level'];
                $item->isDeviceSupported = $deviceTypeResponse['isDeviceSupported'] == true ? 1 : 0;
                $item->poster_image = $device_type == 'tv' ? setBaseUrlWithFileName($item->poster_tv_url, 'image', 'livetv') : setBaseUrlWithFileName($item->poster_url, 'image', 'livetv') ?? null;

                return $item;
            });
            $this->attachLiveTvStats($moreItems->values());

            $channelData['moreItems'] = $moreItems;
            $responseData = new LiveTvChannelDetailsResourceV3($channelData);
            return $responseData;
        });

        if (! $cachedResult['data']) {
            return ApiResponse::error(__('livetv.livetv_not_found'), 404);
        }

        return ApiResponse::success($cachedResult['data'], __('livetv.livetv_details'), 200);
    }

    public function channelList(Request $request){
        $userPlanLevel = (int) (auth()->user()?->subscriptionPackage?->level ?? 0);

        // Base query
        $channelData = LiveTvChannel::with('TvCategory','plan','TvChannelStreamContentMappings')->where('status',1);

        // Allow sorting by combined real + boosted views or alphabetic
        if ($request->input('sort') === 'views') {
            $channelData = $this->applyLiveTvViewsSort($channelData)
            ->featuredFirst()
            ->orderByDesc('total_views');
        } elseif ($request->input('sort') === 'alpha') {
            $channelData = $channelData->featuredFirst()->orderBy('name', 'asc');
        } else {
            $channelData = $channelData->featuredFirst()->orderBy('updated_at', 'desc');
        }
        if(!empty($request->category_id)){
            $channelData = $channelData->where('category_id',$request->category_id);
        }
        if ($request->has('is_ajax') && $request->is_ajax == 1) {
            $perPage = $request->input('per_page', 12);
            $channel =$channelData->paginate($perPage);
            $html = '';
            $channel->each(function($channelItem) use ($userPlanLevel) {
                $isPaid       = $channelItem->access == 'paid';
                $planLevel = $channelItem->plan->level ?? 0;
                $showPremiumBadge = $isPaid && ($userPlanLevel < $planLevel);
                $channelItem['show_premium_badge'] =  $showPremiumBadge;
            });
            $this->attachLiveTvStats($channel->getCollection());
            $channelList = LiveTvChannelResource::collection($channel);

            foreach ($channelList->toArray($request) as $index => $value) {
                $html .= view('frontend::components.card.card_tvchannel', [
                    'value' => $value,
                ])->render();
            }
            $hasMore =  $channel->hasMorePages();

            return ApiResponse::success(
                null,
                __('movie.search_list'),
                200,
                ['html' => $html, 'hasMore' => $hasMore]
            );
        }else{
            $channelData=  $channelData->get();
            $this->attachLiveTvStats($channelData);
            $responseData['channel'] = LiveTvChannelResource::collection($channelData);
            return ApiResponse::success($responseData, __('livetv.channel_list'), 200);
        }

    }

    public function channelListV3(Request $request){
        
        $userId = !empty($request->user_id) ? $request->user_id : null;
        $profile_id = getCurrentProfile($userId, $request);
        $device_type = getDeviceType($request);
        $perPage = $request->input('per_page', 10);
        $cacheVersion = Cache::get('livetv_dashboard_cache_version', 0);
        $cacheKey = 'channel_list_v3_featured_order_'. md5(json_encode([
            'user_id' => $userId,
            'device_type' => $device_type,
            'profile_id' => $profile_id,
            'category_id' => $request->category_id ?? null,
            'is_ajax' => $request->is_ajax ?? 0,
            'sort' => $request->sort ?? null,
            'page' => $request->page ?? 1,
            'per_page' => $perPage,
            'cache_version' => $cacheVersion,
        ]));

        $cachedResult = cacheApiResponse($cacheKey, 300, function () use ($request, $userId, $device_type, $perPage) {
            $getDeviceTypeData = Subscription::checkPlanSupportDevice($userId, $device_type);
            $deviceTypeResponse = json_decode($getDeviceTypeData->getContent(), true);
            $userLevel = Subscription::select('plan_id')->where(['user_id' => $userId, 'status' => 'active'])->latest()->first();
            $userPlanId = $userLevel->plan_id ?? 0;
            $userPlanLevel = $userLevel->plan_level ?? 0;

            $channelData = LiveTvChannel::with('TvCategory','plan','TvChannelStreamContentMappings')->where('status',1)->where('deleted_at',null);
            // support combined real + boosted views and alphabetic sorting
            if ($request->input('sort') === 'views') {
                $channelData = $this->applyLiveTvViewsSort($channelData)
                    ->featuredFirst()
                    ->orderByDesc('total_views');
            } elseif ($request->input('sort') === 'alpha') {
                $channelData = $channelData->featuredFirst()->orderBy('name', 'asc');
            } else {
                $channelData = $channelData->featuredFirst()->orderBy('id', 'desc');
            }
            if(!empty($request->category_id)){
                $channelData = $channelData->where('category_id',$request->category_id);
            }
            if ($request->has('is_ajax') && $request->is_ajax == 1) {
                $channel =$channelData->paginate($perPage);

                // Process channel data to add device support and plan level info
                $channel->getCollection()->transform(function($channelItem) use ($device_type, $deviceTypeResponse, $userPlanId, $userId, $userPlanLevel) {
                    $channelItem->isDeviceSupported = $deviceTypeResponse['isDeviceSupported'] == true ? 1 : 0;
                    $channelItem->poster_image = $device_type == 'tv' ? setBaseUrlWithFileName($channelItem->poster_tv_url, 'image', 'livetv') : setBaseUrlWithFileName($channelItem->poster_url, 'image', 'livetv');
                    $channelItem = setContentAccess($channelItem, $userId, $userPlanId);
                    $channelItem->show_premium_badge = !$channelItem->access == 'free' && $channelItem->access == 'paid' && $channelItem->plan_level > $userPlanLevel;
                    return $channelItem;
                });
                $this->attachLiveTvStats($channel->getCollection());

                $html = '';
                $channelList = LiveTvChannelResourceV3::collection($channel);

                foreach ($channelList->toArray($request) as $index => $value) {
                    $html .= view('frontend::components.card.card_tvchannel', [
                        'value' => $value,
                    ])->render();
                }
                $hasMore =  $channel->hasMorePages();

                return [
                    'html' => $html,
                    'hasMore' => $hasMore,
                    'message' => __('movie.search_list')
                ];
            }else{
                $channelData=  $channelData->paginate($perPage);
                // Process channel data to add device support and plan level info
                $channelData->transform(function($channelItem) use ($device_type, $deviceTypeResponse, $userPlanId, $userId) {
                    $channelItem->isDeviceSupported = $deviceTypeResponse['isDeviceSupported'] == true ? 1 : 0;
                    $channelItem = setContentAccess($channelItem, $userId, $userPlanId);
                    $channelItem->poster_image = $device_type == 'tv' ? setBaseUrlWithFileName($channelItem->poster_tv_url, 'image', 'livetv') : setBaseUrlWithFileName($channelItem->poster_url, 'image', 'livetv');
                    return $channelItem;
                });
                $this->attachLiveTvStats($channelData->getCollection());
                $responseData['channel'] = LiveTvChannelResourceV3::collection($channelData);
                return [
                    'data' => $responseData,
                    'message' => __('livetv.channel_list')
                ];
            }
        });

        if ($request->has('is_ajax') && $request->is_ajax == 1) {
            return ApiResponse::success(
                null,
                $cachedResult['data']['message'],
                200,
                ['html' => $cachedResult['data']['html'], 'hasMore' => $cachedResult['data']['hasMore']]
            );
        } else {
            return ApiResponse::success($cachedResult['data']['data'], $cachedResult['data']['message'], 200);
        }
    }

    public function liveTvDashboardV2(Request $request){

        $channelData =  LiveTvChannel::get_channel();

        $categoryData = LiveTvCategory::where('status',1)->orderBy('updated_at', 'desc')->get();



        $responseData['slider'] = LiveTvChannelResource::collection($channelData);


        $responseData['category_data'] = LiveTvCategoryResourceV2::collection($categoryData);

        return ApiResponse::success($responseData, __('livetv.livetv_dashboard'), 200);
    }

    public function liveTvDashboardV3(Request $request){
        $user_id = !empty($request->user_id) ? $request->user_id : null;

        $device_type = getDeviceType($request);

        $baseCacheKey = 'livetv_dashboard_v3_featured_order_'.md5(json_encode([
            'user_id' => $user_id,
            'device_type' => $device_type
        ]));
        
        // Check cache version to invalidate cache when LiveTV is updated/deleted
        $cacheVersionKey = 'livetv_dashboard_cache_version';
        $cacheVersion = \Illuminate\Support\Facades\Cache::get($cacheVersionKey, 0);
        $cacheKey = $baseCacheKey . '_v' . $cacheVersion;

        $cachedResult = cacheApiResponse($cacheKey, 300, function () use ($request, $user_id, $device_type) {
            // OPTIMIZATION: Initialize purchasedIds for non-user case
            $purchasedIds = [];
            
            // Check if banner is enabled in mobile settings
            $isBanner = MobileSetting::getCacheValueBySlug('banner');
            
            // Get ALL channels for category grouping (not limited to 6)
            $allChannelData = LiveTvChannel::select([
                'id','category_id','name','plan_id','slug','description','status','access','poster_url','poster_tv_url',
            ])
            ->with([
                'plan:id,level',
                'TvCategory:id,name',
                'TvChannelStreamContentMappings:id,tv_channel_id,stream_type,embedded,server_url,server_url1'
            ])
            ->where('status',1)
            ->where('deleted_at',null)
            ->featuredFirst()
            ->orderBy('updated_at', 'desc')
            ->get()
            ->map(function ($item) {
                $item->plan_level = optional($item->plan)->level;
                $item->category = optional($item->TvCategory)->name;
                $item->stream_type = optional($item->TvChannelStreamContentMappings)->stream_type;
                $item->embedded = optional($item->TvChannelStreamContentMappings)->embedded;
                $item->server_url = optional($item->TvChannelStreamContentMappings)->server_url;
                $item->server_url1 = optional($item->TvChannelStreamContentMappings)->server_url1;
                $item->base_url = $item->poster_url;
                return $item;
            });

            // OPTIMIZATION: Select only needed columns for categories
            $categoryData = LiveTvCategory::select('id', 'name', 'file_url', 'status')
                ->where('status',1)
                ->where('deleted_at',null)
                ->orderBy('updated_at', 'desc')
                ->get();

            if ($user_id) {
                // OPTIMIZATION: Cache device support check
                $deviceTypeResponse = cache()->remember(
                    "device_support_{$user_id}_{$device_type}",
                    600,
                    function() use ($user_id, $device_type) {
                        $getDeviceTypeData = Subscription::checkPlanSupportDevice($user_id, $device_type);
                        return json_decode($getDeviceTypeData->getContent(), true);
                    }
                );
                
                $userLevel = Subscription::select('plan_id')->where(['user_id' => $user_id, 'status' => 'active'])->latest()->first();
                $userPlanId = $userLevel->plan_id ?? 0;
                
                 $purchasedItems = PayPerView::where('user_id', $user_id)
                    ->where(function($q) {
                        $q->whereNull('view_expiry_date')
                          ->orWhere('view_expiry_date', '>', now());
                    })
                    ->where(function($q) {
                        $q->whereNull('first_play_date')
                          ->orWhereRaw('DATE_ADD(first_play_date, INTERVAL available_for DAY) > ?', [now()]);
                    })
                    ->select('movie_id', 'type')
                    ->get();
                
                $purchasedIds = [];
                foreach ($purchasedItems as $item) {
                    if (!isset($purchasedIds[$item->type])) {
                        $purchasedIds[$item->type] = [];
                    }
                    $purchasedIds[$item->type][] = $item->movie_id;
                }
            } else {
                $deviceTypeResponse = ['isDeviceSupported' => true];
                $userPlanId = 0;
            }

            // Handle slider based on banner setting
            
            if ($isBanner == 1) {
                $bannerList = Banner::where('banner_for', 'livetv')
                    ->where('status', 1)
                    ->where('deleted_at', null)
                    ->orderBy('id', 'asc')
                    ->limit(5)
                    ->get();

                $bannerChannelIds = $bannerList->pluck('type_id')->filter()->unique()->toArray();
                $bannerChannels = LiveTvChannel::select([
                    'id','category_id','name','plan_id','slug','description','status','access','poster_url','poster_tv_url',
                ])
                ->with([
                    'plan:id,level',
                    'TvCategory:id,name',
                    'TvChannelStreamContentMappings:id,tv_channel_id,stream_type,embedded,server_url,server_url1'
                ])
                ->whereIn('id', $bannerChannelIds)
                ->where('status', 1)
                ->whereNull('deleted_at')
                ->get()
                ->keyBy('id');
              
                // Process banners and extract LiveTV channel data
                $sliderData = [];
                foreach ($bannerList as $banner) {
                    $livetvChannel = $bannerChannels->get($banner->type_id);
                    
                    if ($livetvChannel) {
                        // Map channel data similar to allChannelData
                        $livetvChannel->plan_level = optional($livetvChannel->plan)->level;
                        $livetvChannel->category = optional($livetvChannel->TvCategory)->name;
                        $livetvChannel->stream_type = optional($livetvChannel->TvChannelStreamContentMappings)->stream_type;
                        $livetvChannel->embedded = optional($livetvChannel->TvChannelStreamContentMappings)->embedded;
                        $livetvChannel->server_url = optional($livetvChannel->TvChannelStreamContentMappings)->server_url;
                        $livetvChannel->server_url1 = optional($livetvChannel->TvChannelStreamContentMappings)->server_url1;
                        $livetvChannel->base_url = $livetvChannel->poster_url;
                        
                        $livetvChannel->user_id = $user_id;
                        $livetvChannel->isDeviceSupported = $deviceTypeResponse['isDeviceSupported'] == true ? 1 : 0;
                        $livetvChannel = setContentAccess($livetvChannel, $user_id, $userPlanId, $purchasedIds ?? []);
                        $livetvChannel->poster_image = $device_type == 'tv' 
                            ? setBaseUrlWithFileName($banner->poster_tv_url, 'image', 'banner') 
                            : setBaseUrlWithFileName($banner->poster_url, 'image', 'banner');
                        
                        $sliderData[] = $livetvChannel;
                    }
                }
                $this->attachLiveTvStats(collect($sliderData));

                // schedules methods moved outside closure to class scope
                
                $responseData['slider'] = LiveTvChannelResourceV3::collection(collect($sliderData));
            } else {
                // Banner is disabled, return empty array
                $responseData['slider'] = [];
            }

            // OLD CODE - Commented out (using banners instead)
            // // Get 6 channels for slider
            // $sliderChannelData = LiveTvChannel::get_channel();
            // // Process slider channels (6 channels)
            // $sliderChannelData->each(function ($channel) use ($user_id, $userPlanId, $deviceTypeResponse, $device_type, $purchasedIds) {
            //     $channel->user_id = $user_id;
            //     $channel->isDeviceSupported = $deviceTypeResponse['isDeviceSupported'] == true ? 1 : 0;
            //     // OPTIMIZATION: Pass purchasedIds to setContentAccess
            //     $channel = setContentAccess($channel, $user_id, $userPlanId, $purchasedIds ?? []);
            //     $channel->poster_image =  $device_type == 'tv' ? setBaseUrlWithFileName($channel->poster_tv_url, 'image', 'livetv') : setBaseUrlWithFileName($channel->poster_url , 'image', 'livetv');
            // });
            // $responseData['slider'] = LiveTvChannelResourceV3::collection($sliderChannelData);

            // Process ALL channels for category grouping
            $allChannelData->each(function ($channel) use ($user_id, $userPlanId, $deviceTypeResponse, $device_type, $purchasedIds) {
                $channel->user_id = $user_id;
                $channel->isDeviceSupported = $deviceTypeResponse['isDeviceSupported'] == true ? 1 : 0;
                // OPTIMIZATION: Pass purchasedIds to setContentAccess
                $channel = setContentAccess($channel, $user_id, $userPlanId, $purchasedIds ?? []);
                $channel->poster_image =  $device_type == 'tv' ? setBaseUrlWithFileName($channel->poster_tv_url, 'image', 'livetv') : setBaseUrlWithFileName($channel->poster_url , 'image', 'livetv');
            });
            $this->attachLiveTvStats($allChannelData);

            // Group ALL channels by category for easy access
            $channelsByCategory = $allChannelData->groupBy('category_id');

            $categoryData->each(function ($category) use ($user_id, $userPlanId, $deviceTypeResponse, $device_type, $channelsByCategory, $purchasedIds) {
                    $category->user_id = $user_id;
                    $category->isDeviceSupported = $deviceTypeResponse['isDeviceSupported'] == true ? 1 : 0;
                    // OPTIMIZATION: Pass purchasedIds to setContentAccess
                    $category = setContentAccess($category, $user_id, $userPlanId, $purchasedIds ?? []);
                    $category->posterImage =  setBaseUrlWithFileName($category->file_url ?? null) ;
                    // Get all channels for this category from ALL channels (not just 6)
                    $categoryChannels = $channelsByCategory->get($category->id, collect());
                    // Ensure it's a collection and reset keys
                    $category->processed_channels = $categoryChannels->values();
                });

            $responseData['category_data'] = LiveTvCategoryResourceV3::collection($categoryData);

            return $responseData;
        });

        return ApiResponse::success($cachedResult['data'], __('livetv.livetv_dashboard'), 200);
    }

    /**
     * Get schedules for a channel
     */
    public function channelSchedules(Request $request)
    {
        $channelId = $request->channel_id;
        $channel = LiveTvChannel::find($channelId);
        if (!$channel) {
            return ApiResponse::error(null, __('livetv.channel_not_found'), 404);
        }

        $schedule = Cache::remember("spa:livetv:schedules:{$channelId}", 60, function () use ($channelId) {
            $channel = LiveTvChannel::with('schedules')->find($channelId);

            return $channel->schedules->map(function($s){
                return [
                    'id' => $s->id,
                    'title' => $s->title,
                    'start_at' => optional($s->start_at)->toIso8601String(),
                    'end_at' => optional($s->end_at)->toIso8601String(),
                    'meta' => $s->meta ? json_decode($s->meta, true) : null,
                ];
            });
        });

        return ApiResponse::success($schedule, __('livetv.channel_schedules'), 200);
    }

    private function clearChannelScheduleCache($channelId): void
    {
        if ($channelId) {
            Cache::forget("spa:livetv:schedules:{$channelId}");
        }

        if (function_exists('clearLiveTvDashboardCache')) {
            clearLiveTvDashboardCache();
        }
    }

    public function storeChannelSchedule(Request $request)
    {
        $request->validate([
            'channel_id' => 'required|integer|exists:live_tv_channel,id',
            'title' => 'nullable|string',
            'start_at' => 'required|date',
            'end_at' => 'nullable|date',
        ]);

        // Determine timezone from channel if available, otherwise fall back to app timezone
        $channel = \Modules\LiveTV\Models\LiveTvChannel::find($request->channel_id);
        $tz = $channel && !empty($channel->timezone) ? $channel->timezone : config('app.timezone', 'UTC');
        $start = $request->start_at ? \Carbon\Carbon::parse($request->start_at, $tz)->setTimezone('UTC') : null;
        $end = $request->end_at ? \Carbon\Carbon::parse($request->end_at, $tz)->setTimezone('UTC') : null;

        $schedule = \Modules\LiveTV\Models\ChannelSchedule::create([
            'live_tv_channel_id' => $request->channel_id,
            'title' => $request->title,
            'start_at' => $start,
            'end_at' => $end,
            'meta' => $request->meta ? json_encode($request->meta) : null,
        ]);

        $this->clearChannelScheduleCache($request->channel_id);

        return ApiResponse::success($schedule, __('livetv.schedule_created'), 201);
    }

    public function updateChannelSchedule(Request $request, $id)
    {
        $schedule = \Modules\LiveTV\Models\ChannelSchedule::find($id);
        if (!$schedule) {
            return ApiResponse::error(null, __('livetv.schedule_not_found'), 404);
        }
        $channelId = $schedule->live_tv_channel_id;

        $request->validate([
            'title' => 'nullable|string',
            'start_at' => 'nullable|date',
            'end_at' => 'nullable|date',
        ]);

        // If provided, parse provided datetimes using the channel timezone (if set) and store UTC
        $channel = \Modules\LiveTV\Models\LiveTvChannel::find($schedule->live_tv_channel_id);
        $tz = $channel && !empty($channel->timezone) ? $channel->timezone : config('app.timezone', 'UTC');
        $start = $request->start_at ? \Carbon\Carbon::parse($request->start_at, $tz)->setTimezone('UTC') : ($request->has('start_at') ? null : $schedule->start_at);
        $end = $request->end_at ? \Carbon\Carbon::parse($request->end_at, $tz)->setTimezone('UTC') : ($request->has('end_at') ? null : $schedule->end_at);

        $schedule->update([
            'title' => $request->title ?? $schedule->title,
            'start_at' => $start,
            'end_at' => $end,
            'meta' => $request->meta ? json_encode($request->meta) : $schedule->meta,
        ]);

        $this->clearChannelScheduleCache($channelId);

        return ApiResponse::success($schedule, __('livetv.schedule_updated'), 200);
    }

    public function deleteChannelSchedule($id)
    {
        $schedule = \Modules\LiveTV\Models\ChannelSchedule::find($id);
        if (!$schedule) {
            return ApiResponse::error(null, __('livetv.schedule_not_found'), 404);
        }
        $channelId = $schedule->live_tv_channel_id;
        $schedule->delete();
        $this->clearChannelScheduleCache($channelId);
        return ApiResponse::success(null, __('livetv.schedule_deleted'), 200);
    }

    private function applyLiveTvViewsSort($query)
    {
        $globalMode = $this->validDisplayMode(
            DB::table('stat_settings')->where('key', 'views_display_mode')->value('value') ?? 'combined'
        );

        $realViews = DB::table('stat_page_views')
            ->select('content_id', DB::raw('COUNT(*) as real_views'))
            ->where('content_type', 'livetv')
            ->groupBy('content_id');

        $boostViews = DB::table('stat_content_boosts')
            ->select('content_id', DB::raw('SUM(boost_views) as boost_views'))
            ->where('content_type', 'livetv')
            ->groupBy('content_id');

        return $query
            ->leftJoinSub($realViews, 'livetv_real_views', function ($join) {
                $join->on('live_tv_channel.id', '=', 'livetv_real_views.content_id');
            })
            ->leftJoinSub($boostViews, 'livetv_boost_views', function ($join) {
                $join->on('live_tv_channel.id', '=', 'livetv_boost_views.content_id');
            })
            ->leftJoin('stat_settings as livetv_view_mode', function ($join) {
                $join->on('livetv_view_mode.key', '=', DB::raw("CONCAT('views_display_mode:livetv:', live_tv_channel.id)"));
            })
            ->select(
                'live_tv_channel.*',
                DB::raw($this->displayCountSql($globalMode) . ' as total_views')
            );
    }

    private function attachLiveTvStats(Collection $channels): void
    {
        if ($channels->isEmpty()) {
            return;
        }

        $ids = $channels->pluck('id')->filter()->values();
        if ($ids->isEmpty()) {
            return;
        }

        $realViews = DB::table('stat_page_views')
            ->select('content_id', DB::raw('COUNT(*) as total'))
            ->where('content_type', 'livetv')
            ->whereIn('content_id', $ids)
            ->groupBy('content_id')
            ->pluck('total', 'content_id');

        $realPlays = DB::table('stat_play_events')
            ->select('content_id', DB::raw('COUNT(*) as total'))
            ->where('content_type', 'livetv')
            ->whereIn('content_id', $ids)
            ->groupBy('content_id')
            ->pluck('total', 'content_id');

        $boosts = DB::table('stat_content_boosts')
            ->select(
                'content_id',
                DB::raw('SUM(boost_views) as boost_views'),
                DB::raw('SUM(boost_plays) as boost_plays')
            )
            ->where('content_type', 'livetv')
            ->whereIn('content_id', $ids)
            ->groupBy('content_id')
            ->get()
            ->keyBy('content_id');

        $settings = DB::table('stat_settings')->whereIn('key', array_merge([
            'show_views_frontend',
            'show_plays_frontend',
            'views_display_mode',
            'plays_display_mode',
        ], $ids->flatMap(fn ($id) => [
            'views_display_mode:livetv:' . $id,
            'plays_display_mode:livetv:' . $id,
        ])->all()))->pluck('value', 'key');

        $showViews = $settings['show_views_frontend'] ?? '1';
        $showPlays = $settings['show_plays_frontend'] ?? '1';
        $globalViewsMode = $this->validDisplayMode($settings['views_display_mode'] ?? 'combined');
        $globalPlaysMode = $this->validDisplayMode($settings['plays_display_mode'] ?? 'combined');

        $channels->each(function ($channel) use ($realViews, $realPlays, $boosts, $showViews, $showPlays, $settings, $globalViewsMode, $globalPlaysMode) {
            $boost = $boosts->get($channel->id);
            $realViewCount = (int) ($realViews[$channel->id] ?? 0);
            $realPlayCount = (int) ($realPlays[$channel->id] ?? 0);
            $boostViewCount = (int) ($boost->boost_views ?? 0);
            $boostPlayCount = (int) ($boost->boost_plays ?? 0);
            $viewsMode = $this->validDisplayMode($settings['views_display_mode:livetv:' . $channel->id] ?? $globalViewsMode);
            $playsMode = $this->validDisplayMode($settings['plays_display_mode:livetv:' . $channel->id] ?? $globalPlaysMode);

            $channel->real_views = $realViewCount;
            $channel->boost_views = $boostViewCount;
            $channel->display_views = $this->displayCount($viewsMode, $realViewCount, $boostViewCount);
            $channel->total_views = $channel->display_views;
            $channel->real_plays = $realPlayCount;
            $channel->boost_plays = $boostPlayCount;
            $channel->display_plays = $this->displayCount($playsMode, $realPlayCount, $boostPlayCount);
            $channel->total_plays = $channel->display_plays;
            $channel->views_display_mode = $viewsMode;
            $channel->plays_display_mode = $playsMode;
            $channel->show_views_frontend = $showViews === '1' && $viewsMode !== 'hidden';
            $channel->show_plays_frontend = $showPlays === '1' && $playsMode !== 'hidden';
        });
    }

    private function validDisplayMode(?string $mode): string
    {
        return in_array($mode, ['combined', 'real', 'boosted', 'hidden'], true) ? $mode : 'combined';
    }

    private function displayCount(string $mode, int $real, int $boost): int
    {
        return match ($mode) {
            'real' => $real,
            'boosted' => $boost,
            'hidden' => 0,
            default => $real + $boost,
        };
    }

    private function displayCountSql(string $globalMode): string
    {
        $real = 'COALESCE(livetv_real_views.real_views, 0)';
        $boost = 'COALESCE(livetv_boost_views.boost_views, 0)';
        $globalExpression = match ($globalMode) {
            'real' => $real,
            'boosted' => $boost,
            'hidden' => '0',
            default => "({$real} + {$boost})",
        };

        return "CASE COALESCE(livetv_view_mode.value, '{$globalMode}')
            WHEN 'real' THEN {$real}
            WHEN 'boosted' THEN {$boost}
            WHEN 'hidden' THEN 0
            WHEN 'combined' THEN ({$real} + {$boost})
            ELSE {$globalExpression}
        END";
    }
}
