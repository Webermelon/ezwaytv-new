<?php

namespace Modules\Ad\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\Request;
use Modules\Ad\Models\CustomAdsSetting;
use Modules\Ad\Transformers\CustomAdsSettingResource;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class CustomAdsSettingController extends Controller
{
    public function media(CustomAdsSetting $customAd)
    {
        abort_unless((bool) $customAd->status && is_null($customAd->deleted_at), 404);

        $media = request()->query('device') === 'mobile' && $customAd->mobile_media
            ? $customAd->mobile_media
            : $customAd->media;
        $mediaUrl = $customAd->url_type === 'local'
            ? setBaseUrlWithFileName($media, $customAd->type, 'ads')
            : (string) $media;
        $path = function_exists('mediaStoragePathFromUrl')
            ? mediaStoragePathFromUrl($mediaUrl)
            : ltrim((string) parse_url($mediaUrl, PHP_URL_PATH), '/');
        $path = ltrim($path, '/');
        abort_if($path === '', 404);

        $activeDisk = config('filesystems.active') === 'dg-ocean' ? 'dg-ocean' : 'local';
        $bucket = (string) config("filesystems.disks.{$activeDisk}.bucket");
        $pathCandidates = [$path];

        if ($bucket !== '' && str_starts_with($path, $bucket . '/')) {
            $pathCandidates[] = substr($path, strlen($bucket) + 1);
        }

        $host = (string) parse_url($mediaUrl, PHP_URL_HOST);
        if (str_ends_with($host, 'digitaloceanspaces.com') && str_contains($path, '/')) {
            $pathCandidates[] = substr($path, strpos($path, '/') + 1);
        }

        foreach (array_values(array_unique(array_filter($pathCandidates))) as $candidatePath) {
            if ($activeDisk === 'dg-ocean' && Storage::disk('dg-ocean')->exists($candidatePath)) {
                $stream = Storage::disk('dg-ocean')->readStream($candidatePath);
                abort_if($stream === false, 404);

                $this->clearOutputBuffers();

                return response()->stream(function () use ($stream) {
                    fpassthru($stream);
                    fclose($stream);
                }, 200, [
                    'Content-Type' => Storage::disk('dg-ocean')->mimeType($candidatePath) ?: 'image/jpeg',
                    'Cache-Control' => 'public, max-age=86400',
                ]);
            }

            foreach ([public_path($candidatePath), public_path('storage/' . $candidatePath)] as $candidate) {
                if (is_file($candidate)) {
                    $this->clearOutputBuffers();

                    return response()->file($candidate, [
                        'Cache-Control' => 'public, max-age=86400',
                    ]);
                }
            }
        }

        if (filter_var($mediaUrl, FILTER_VALIDATE_URL) !== false) {
            try {
                $response = Http::timeout(12)->get($mediaUrl);
                if ($response->successful()) {
                    $this->clearOutputBuffers();

                    return response($response->body(), 200, [
                        'Content-Type' => $response->header('Content-Type', 'image/jpeg'),
                        'Cache-Control' => 'public, max-age=86400',
                    ]);
                }
            } catch (\Throwable $exception) {
                report($exception);
            }
        }

        abort(404);
    }

    private function clearOutputBuffers(): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
    }

    public function getActiveAds(Request $request)
    {
        try {
            $user = auth('sanctum')->user(); // safe, no exception
            if ($user && !$user->relationLoaded('subscriptionPackage')) {
                $user->load('subscriptionPackage');
            }

            // If user is authenticated, check if Ads are disabled in their subscription plan
            $subscription = $user ? $user->subscriptionPackage : null;
            if ($subscription && isset($subscription['plan_type'])) {
                $planLimitations = json_decode($subscription['plan_type'], true);
                foreach ($planLimitations as $limitation) {
                    if (
                        isset($limitation['limitation_title']) &&
                        strtolower($limitation['limitation_title']) === 'ads' &&
                        isset($limitation['limitation_value']) &&
                        $limitation['limitation_value'] == 0
                    ) {
                        Log::info('CustomAds are disabled for this user', [
                            'user_id' => $user->id,
                            'subscription_plan' => $subscription['plan_type'],
                            'plan_limitations' => $planLimitations
                        ]);
                        // Ads are disabled for this user   
                        return ApiResponse::success([], __('messages.ads_are_disabled_in_your_subscription'), 200)
                            ->header('Cache-Control', 'no-cache, no-store, must-revalidate')
                            ->header('Pragma', 'no-cache')
                            ->header('Expires', '0');
                    }
                }
            }

            $contentId = $request->input('content_id');
            $contentType = $request->input('type'); // video, movie, tvshow, channel
            $contentVideoType = $request->input('video_type');
            $currentDate = Carbon::now()->format('Y-m-d');


            // Skip ads for trailers
            if ($contentVideoType === 'trailer') {
                return ApiResponse::success([], __('messages.custom_ads_list'), 200)
                    ->header('Cache-Control', 'no-cache, no-store, must-revalidate')
                    ->header('Pragma', 'no-cache')
                    ->header('Expires', '0');
            }
            $query = CustomAdsSetting::where('status', 1);

            // Date range filter
            $query->where(function ($q) use ($currentDate) {
                $q->where(function ($dateQuery) use ($currentDate) {
                    $dateQuery->whereNotNull('start_date')
                        ->whereNotNull('end_date')
                        ->where('start_date', '<=', $currentDate)
                        ->where('end_date', '>=', $currentDate);
                })->orWhere(function ($dateQuery) use ($currentDate) {
                    $dateQuery->whereNotNull('start_date')
                        ->whereNull('end_date')
                        ->where('start_date', '<=', $currentDate);
                })->orWhere(function ($dateQuery) use ($currentDate) {
                    $dateQuery->whereNull('start_date')
                        ->whereNotNull('end_date')
                        ->where('end_date', '>=', $currentDate);
                })->orWhere(function ($dateQuery) {
                    $dateQuery->whereNull('start_date')
                        ->whereNull('end_date');
                });
            });


            if ($contentType) {
                $query->where('target_content_type', $contentType);

                if ($contentId) {
                    $query->where(function ($q) use ($contentId) {
                        $q->where('target_categories', 'like', '%[' . $contentId . ',%')
                            ->orWhere('target_categories', 'like', '%,' . $contentId . ',%')
                            ->orWhere('target_categories', 'like', '%,' . $contentId . ']%')
                            ->orWhere('target_categories', 'like', '%[' . $contentId . ']%');
                    });
                }
            }


            $activeAds = $query->orderBy('id', 'asc')->get();

            $filteredAds = $activeAds->map(function ($ad) use ($user) {
            $targetType = strtolower($ad->target_content_type ?? '');

            // Always keep ads with target_content_type 'home_page', null, or empty string
            if (in_array($targetType, ['home_page', ''])) {
                return $ad;
            }

            $targetIds = json_decode($ad->target_categories, true);
            if (!is_array($targetIds) || empty($targetIds)) {
                return $ad;
            }

            $payPerViewType = $targetType === 'tvshow' ? 'episode' : $targetType;

            // If user not logged in, do not filter by purchases
            $purchasedIds = [];
            if ($user) {
                $purchasedIds = DB::table('pay_per_views')
                    ->where('user_id', $user->id)
                    ->where('type', $payPerViewType)
                    ->whereIn('movie_id', $targetIds)
                    ->pluck('movie_id')
                    ->toArray();
            }

            // Check if the current content is purchased - skip this ad if purchased
            $contentId = request()->input('content_id');
            if ($user && $contentId && in_array((int)$contentId, $purchasedIds)) {
                // If the current content is purchased, skip this ad completely
                return null;
            }

            $remainingIds = array_values(array_diff($targetIds, $purchasedIds));
            $ad->target_categories = json_encode($remainingIds);

            return $ad;
        })->filter(function ($ad) {
            // Remove null values (ads for purchased content)
            if ($ad === null) {
                return false;
            }

            $targetType = strtolower($ad->target_content_type ?? '');

            // Always keep ads with target_content_type 'home_page', null, or ''
            if (in_array($targetType, ['home_page', ''])) {
                return true;
            }

            $targets = json_decode($ad->target_categories, true);
            return is_array($targets) && count($targets) > 0;
        })->values();


            Log::info('CustomAds query result', [
                'content_type' => $contentType,
                'content_id' => $contentId,
                'current_date' => $currentDate,
                'ads_found' => $filteredAds->count(),
                'ads' => $filteredAds->toArray()
            ]);

            return response()->json([
                'success' => true,
                'data' => CustomAdsSettingResource::collection($filteredAds),
            ])->header('Cache-Control', 'no-cache, no-store, must-revalidate')
              ->header('Pragma', 'no-cache')
              ->header('Expires', '0');
        } catch (\Exception $e) {

            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500)->header('Cache-Control', 'no-cache, no-store, must-revalidate')
                    ->header('Pragma', 'no-cache')
                    ->header('Expires', '0');
        }
    }
}
