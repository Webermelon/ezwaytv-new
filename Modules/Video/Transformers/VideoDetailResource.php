<?php

namespace Modules\Video\Transformers;

use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Subscriptions\Transformers\PlanResource;
use Modules\Subscriptions\Models\Plan;
use Modules\Video\Models\Video;
use Modules\Video\Transformers\VideoResource;
use Modules\Entertainment\Models\EntertainmentDownload;
use Modules\Subscriptions\Models\Subscription;
use Modules\Entertainment\Models\Entertainment;
use Modules\Entertainment\Transformers\SubtitleResource;
use Modules\Entertainment\Transformers\ClipResource;
use Modules\Video\Transformers\Backend\VideoResourceV3;

class VideoDetailResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray($request): array
    {
        $plans = [];
        $plan = $this->plan ?? null;
        if($plan){
            $plans = Plan::where('level', '<=', $plan->level)->get();
        }

        $userId = $request->input('user_id') ?? auth()->id();
        $currentUser = auth()->user();

        if (! $currentUser && $userId) {
            $currentUser = \App\Models\User::with('subscriptionPackage:id,user_id,level,name')
                ->where('id', $userId)
                ->first();
        } elseif ($currentUser && ! $currentUser->relationLoaded('subscriptionPackage')) {
            $currentUser->load('subscriptionPackage:id,user_id,level,name');
        }

        $requiredPlanLevel = optional($plan)->level ?? 0;
        $currentPlanLevel = optional(optional($currentUser)->subscriptionPackage)->level ?? 0;
        $isPurchased = Entertainment::isPurchased($this->id, 'video', $userId);
        $hasContentAccess = $this->access === 'free'
            || ($this->access === 'paid' && $currentPlanLevel >= $requiredPlanLevel)
            || ($this->access === 'pay-per-view' && $isPurchased);
        $isPremiumLocked = $this->access === 'paid' && ! $hasContentAccess;



      $more_items = Video::where('status', 1)
    ->when(request()->has('is_restricted'), function ($query) {
        $query->where('is_restricted', request()->is_restricted);
    })
    ->when(getCurrentProfileSession('is_child_profile') && getCurrentProfileSession('is_child_profile') != 0, function ($query) {
        $query->where('is_restricted', 0);
    })
    ->where('id', '!=', $this->id)  // exclude current video by ID here
    ->take(6)
    ->get();

        $downloadMappings = $this->entertainmentDownloadMappings ? $this->entertainmentDownloadMappings->toArray() : [];

        if ($this->download_status == 1) {

            if($this->download_type != null &&  $this->download_url !=null){

            $downloadData = [
                'type' => $this->download_type,
                'url' => $this->download_url,
                'quality' => 'default',
            ];
            $downloadMappings[] = $downloadData;
         }
        }
        $download = EntertainmentDownload::where('entertainment_id', $this->entertainment_id)->where('user_id',  $this->user_id)->where('entertainment_type', 'episode')->where('is_download', 1)->first();
        $header = request()->headers->all();
        $device_type = !empty($header['device-type'])? $header['device-type'][0] : []; //for tv
        $getDeviceTypeData = Subscription::checkPlanSupportDevice($request->user_id,$device_type);
        $deviceTypeResponse = json_decode($getDeviceTypeData->getContent(), true); // Decode to associative array
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'trailer_url_type' => $this->trailer_url_type,
            'trailer_url' => $this->trailer_url_type=='Local' ? setBaseUrlWithFileName($this->trailer_url,'video','video') : $this->trailer_url,
            'access' => $this->access,
            'plan_id' => $this->plan_id,
            'plan_level' => $this->plan->level ?? 0,
            'required_plan_level' => $requiredPlanLevel,
            'required_plan_name' => optional($plan)->name,
            'current_plan_level' => $currentPlanLevel,
            'has_content_access' => $hasContentAccess,
            'is_premium' => $isPremiumLocked,
            'show_premium_badge' => $isPremiumLocked,
            'imdb_rating' => $this->IMDb_rating,
            'content_rating' => $this->content_rating,
            'watched_time' => optional($this->continue_watch)->watched_time ?? null,
            'is_watch_list' => $this->is_watch_list ?? false,
            'is_likes' => $this->is_likes ?? false,
            'duration' => $this->duration,
            'release_date' => $this->release_date,
            'is_restricted' => $this->is_restricted,
            'short_desc' => $this->short_desc,
            'description' => strip_tags($this->description),
            'enable_quality' => $this->enable_quality,
            'video_upload_type' => $this->video_upload_type,
            'video_url_input' => $this->video_upload_type=='Local' ? setBaseUrlWithFileName($this->video_url_input,'video','video') : $this->video_url_input,
            'download_status' => $this->download_status,
            'enable_download_quality' => $this->enable_download_quality,
            'subtitle_info' => $this->enable_subtitle == 1 ? SubtitleResource::collection($this->subtitles) : null,
            'download_url' => $this->download_url,
            'download_type' => $this->download_type,
            'poster_image' => setBaseUrlWithFileName($this->poster_url,'image','video'),
            'video_links' => $this->VideoStreamContentMappings ?? null,

            'plans' => PlanResource::collection($plans),
            'more_items' => VideoResourceV3::collection($more_items),
            'status' => $this->status,
            'is_likes' => $this->is_likes ?? false,
            'like_count' => $this->entertainmentLike()->where('is_like', 1)->where('type', 'video')->count(),
            'view_count' => $this->entertainmentView()->count(),
            'is_download' => $this->is_download ?? false,
            'download_quality' => $downloadMappings,
            'download_id' => !empty($download) ? $download->id: null,
            'is_device_supported' => $deviceTypeResponse['isDeviceSupported'],
            'poster_tv_image' => setBaseUrlWithFileName($this->poster_tv_url,'image','video'),
            'price' => (float)$this->price,
            'discounted_price' => round((float)$this->price - ($this->price * ($this->discount / 100)), 2),
            'purchase_type' => $this->purchase_type,
            'access_duration' => $this->access_duration,
            'discount'=> (float)$this->discount,
            'available_for' => $this->available_for,
            'is_purchased' => $isPurchased,
            'intro_starts_at' => $this->start_time ?? null,
            'intro_ends_at' => $this->end_time ?? null,
            'is_clips_enabled' => $this->enable_clips,
            'bunny_video_url' => $this->bunny_video_url,
            'clips' => ClipResource::collection(($this->clips ?? collect())->where('content_type', 'video')->values()),
            'categories' => ($this->relationLoaded('categories') ? $this->categories : collect())->map(fn($c) => ['id' => $c->id, 'name' => $c->name, 'slug' => $c->slug])->values()->toArray(),
            'author_channels' => $this->resolveAuthorChannels(),
        ];
    }

    /**
     * Resolve author channels from pivot (preferred) or creator_channel_id FK.
     * Always returns a plain array for safe caching/serialization.
     */
    private function resolveAuthorChannels(): array
    {
        $channels = collect();

        if ($this->relationLoaded('authorChannels') && $this->authorChannels->isNotEmpty()) {
            $channels = $this->authorChannels;
        } elseif ($this->relationLoaded('creatorChannel') && $this->creatorChannel) {
            $channels = collect([$this->creatorChannel]);
        }

        return $channels->map(fn($ch) => [
            'id'       => $ch->id,
            'name'     => $ch->name,
            'username' => $ch->username,
            'avatar'   => $ch->avatar ?: null,
            'banner'   => $ch->banner ?: null,
        ])->values()->toArray();
    }
}
