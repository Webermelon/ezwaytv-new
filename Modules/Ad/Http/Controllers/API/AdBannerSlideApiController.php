<?php

namespace Modules\Ad\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
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
                $q->whereJsonContains('placements', 'all');
                foreach ($placementArr as $p) {
                    if ($p !== '') {
                        $q->orWhereJsonContains('placements', $p);
                    }
                }
            });
        }

        $slides = $query->limit(max(1, $limit))->get()->map(function($s) {
            return [
                'id' => $s->id,
                'title' => $s->title,
                'description' => $s->description,
                'image' => $s->image,
                'image_proxy_url' => $s->image_proxy_url,
                'link' => $s->link_url ?? null,
                'link_url' => $s->link_url ?? null,
                'placements' => $s->placements,
            ];
        });

        return ApiResponse::success($slides, 'Ad banner sliders retrieved', 200);
    }

    public function image(AdBannerSlide $slide)
    {
        abort_unless((bool) $slide->status && is_null($slide->deleted_at), 404);

        $imageUrl = (string) $slide->image;
        $path = function_exists('mediaStoragePathFromUrl')
            ? mediaStoragePathFromUrl($imageUrl)
            : ltrim((string) parse_url($imageUrl, PHP_URL_PATH), '/');
        $path = ltrim($path, '/');
        abort_if($path === '', 404);

        $activeDisk = config('filesystems.active') === 'dg-ocean' ? 'dg-ocean' : 'local';
        $bucket = (string) config("filesystems.disks.{$activeDisk}.bucket");
        $pathCandidates = [$path];

        if ($bucket !== '' && str_starts_with($path, $bucket . '/')) {
            $pathCandidates[] = substr($path, strlen($bucket) + 1);
        }

        $host = (string) parse_url($imageUrl, PHP_URL_HOST);
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

        if (filter_var($imageUrl, FILTER_VALIDATE_URL) !== false) {
            try {
                $response = Http::timeout(12)->get($imageUrl);
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
}
