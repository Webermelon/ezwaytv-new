<?php

namespace Modules\CastCrew\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;
use  Modules\Entertainment\Models\EntertainmentTalentMapping;

class CastCrew extends BaseModel
{
    use SoftDeletes;

     protected $table = 'cast_crew';

     protected $fillable = ['name', 'type','file_url','tmdb_id','bio','place_of_birth','dob','designation','status'];


     public function entertainmentTalentMappings()
     {
         return $this->hasMany(EntertainmentTalentMapping::class,'talent_id','id');
     }


     protected static function boot()
     {
         parent::boot();

         static::created(function () {
             self::refreshFrontendCacheVersion();
         });

         // Clear dashboard cache when cast/crew status is updated
         static::updated(function ($castcrew) {
             self::refreshFrontendCacheVersion();

             // Clear dashboard cache when status changes (affects Popular Personalities section)
             if ($castcrew->isDirty('status')) {
                 if (function_exists('clearDashboardCache')) {
                     clearDashboardCache();
                 }
                 if (function_exists('clearSearchV3Cache')) {
                     clearSearchV3Cache();
                 }
             }
         });

         static::deleting(function ($castcrew) {
             self::refreshFrontendCacheVersion();

             // Clear dashboard cache when cast/crew is deleted
             if (function_exists('clearDashboardCache')) {
                 clearDashboardCache();
             }

             if ($castcrew->isForceDeleting()) {

                 $castcrew->entertainmentTalentMappings()->forcedelete();

             } else {
                 $castcrew->entertainmentTalentMappings()->delete();
              }

         });

         static::restoring(function ($castcrew) {
             self::refreshFrontendCacheVersion();

             // Clear dashboard cache when cast/crew is restored
             if (function_exists('clearDashboardCache')) {
                 clearDashboardCache();
             }

             $castcrew->entertainmentTalentMappings()->withTrashed()->restore();

         });
     }

     /**
     * Fetch cast/crew by ids and format for frontend cards
     *
     * @param array<int> $castIds
     * @return array<int, array<string, mixed>>
     */
    public static function getFrontendCardsByIds(array $castIds): array
    {
        $casts = self::whereIn('id', $castIds)->where('status', 1)->where('deleted_at', null)->get(['id', 'name', 'type', 'file_url', 'designation']);

        return $casts->map(function (self $value): array {
            return [
                'id' => $value->id,
                'name' => $value->name,
                'type' => $value->type,
                'designation' => $value->designation,
                'profile_image' => setBaseUrlWithFileName($value->file_url, 'image', 'castcrew'),
            ];
        })->all();
    }

    /**
     * Return active personality cards with selected IDs first, then remaining actors.
     *
     * @param array<int|string> $castIds
     * @return array<int, array<string, mixed>>
     */
    public static function getFrontendPersonalityCardsSortedWithAll(array $castIds): array
    {
        $selectedIds = collect($castIds)
            ->filter(fn ($id) => $id !== null && $id !== '')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
        $selectedOrder = array_flip($selectedIds);
        $fallbackOffset = count($selectedOrder);

        $casts = self::query()
            ->where('status', 1)
            ->whereNull('deleted_at')
            ->where(function ($query) use ($selectedIds) {
                $query->where('type', 'actor');

                if (count($selectedIds) > 0) {
                    $query->orWhereIn('id', $selectedIds);
                }
            })
            ->get(['id', 'name', 'type', 'file_url', 'designation']);

        return $casts
            ->sort(function (self $a, self $b) use ($selectedOrder, $fallbackOffset) {
                $aId = (int) $a->id;
                $bId = (int) $b->id;
                $aSelectedOrder = $selectedOrder[$aId] ?? $fallbackOffset;
                $bSelectedOrder = $selectedOrder[$bId] ?? $fallbackOffset;

                if ($aSelectedOrder !== $bSelectedOrder) {
                    return $aSelectedOrder <=> $bSelectedOrder;
                }

                return strcasecmp((string) $a->name, (string) $b->name);
            })
            ->values()
            ->map(function (self $value): array {
                return [
                    'id' => $value->id,
                    'name' => $value->name,
                    'type' => $value->type,
                    'designation' => $value->designation,
                    'profile_image' => setBaseUrlWithFileName($value->file_url, 'image', 'castcrew'),
                ];
            })
            ->all();
    }

    private static function refreshFrontendCacheVersion(): void
    {
        $key = 'spa:castcrew:cache-version';

        if (! Cache::has($key)) {
            Cache::forever($key, 1);
        }

        Cache::increment($key);
    }


}
