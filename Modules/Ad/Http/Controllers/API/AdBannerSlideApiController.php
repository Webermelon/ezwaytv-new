<?php

namespace Modules\Ad\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Ad\Models\AdBannerSlide;
use App\Http\Responses\ApiResponse;

class AdBannerSlideApiController extends Controller
{
    /**
     * Return public ad banner sliders.
     * Query params: placements (comma separated), limit, status
     */
    public function index(Request $request)
    {
        $placements = $request->query('placements');
        $limit = (int) $request->query('limit', 20);
        $status = $request->query('status', 1);

        $query = AdBannerSlide::query()->where('status', $status)->whereNull('deleted_at')->orderBy('sort_order');

        if (!empty($placements)) {
            $placementArr = array_map('trim', explode(',', $placements));
            $query->where(function($q) use ($placementArr) {
                foreach ($placementArr as $p) {
                    $q->orWhereJsonContains('placements', $p);
                }
            });
        }

        $slides = $query->limit(max(1, $limit))->get()->map(function($s) {
            return [
                'id' => $s->id,
                'title' => $s->title,
                'description' => $s->description,
                'image' => $s->image,
                'link' => $s->link ?? null,
                'placements' => $s->placements,
            ];
        });

        return ApiResponse::success($slides, 'Ad banner sliders retrieved', 200);
    }
}
