<?php

namespace Modules\Frontend\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\AuthorChannel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Modules\Entertainment\Models\Entertainment;
use Modules\Episode\Models\Episode;
use Modules\LiveTV\Models\LiveTvChannel;
use Modules\Video\Models\Video;

class ReactMetaController extends Controller
{
    public function shell()
    {
        return $this->render();
    }

    public function onDemandIndex()
    {
        return $this->render([
            'meta_title' => 'On Demand Channels',
            'short_description' => 'Watch on demand channels on eZWay TV.',
            'seo_image' => $this->onDemandIndexImage(),
            'canonical_url' => url('/on-demand'),
        ]);
    }

    public function onDemandShow(string $username)
    {
        $channel = AuthorChannel::query()
            ->where('username', $username)
            ->where('is_active', 1)
            ->first();

        if (! $channel) {
            return $this->render();
        }

        $description = $this->description($channel->description)
            ?: "Watch {$channel->name} on demand on eZWay TV.";

        return $this->render([
            'meta_title' => "{$channel->name} | On Demand",
            'short_description' => $description,
            'seo_image' => $this->authorChannelImage($channel),
            'canonical_url' => url('/on-demand/' . $channel->username),
        ]);
    }

    public function liveTvIndex()
    {
        return $this->render([
            'meta_title' => 'Live TV',
            'short_description' => 'Watch live TV channels on eZWay TV.',
            'canonical_url' => url('/livetv'),
        ]);
    }

    public function liveTvShow(string $path)
    {
        $channel = LiveTvChannel::query()
            ->where('status', 1)
            ->where(function ($query) use ($path) {
                $query->where('slug', $path);

                if (ctype_digit($path)) {
                    $query->orWhere('id', (int) $path);
                }
            })
            ->first();

        if (! $channel) {
            return $this->render();
        }

        return $this->render([
            'meta_title' => $channel->name,
            'short_description' => $this->description($channel->description),
            'seo_image' => $this->typedImage($channel, 'livetv'),
            'canonical_url' => url('/livetv/' . ($channel->slug ?: $channel->id)),
        ]);
    }

    public function videoDetails(string $id)
    {
        $video = $this->findBySlugOrId(Video::query(), $id);

        return $this->renderContent($video, 'video', '/video-details/');
    }

    public function movieDetails(string $id)
    {
        $movie = $this->findBySlugOrId(
            Entertainment::query()->where('type', 'movie'),
            $id
        );

        return $this->renderContent($movie, 'movie', '/movie-details/');
    }

    public function tvshowDetails(string $id)
    {
        $tvshow = $this->findBySlugOrId(
            Entertainment::query()->where('type', 'tvshow'),
            $id
        );

        return $this->renderContent($tvshow, 'tvshow', '/tvshow-details/');
    }

    public function episodeDetails(string $id)
    {
        $episode = $this->findBySlugOrId(Episode::query(), $id);

        return $this->renderContent($episode, 'episode', '/episode-details/');
    }

    private function renderContent(?Model $content, string $pageType, string $pathPrefix)
    {
        if (! $content) {
            return $this->render();
        }

        $slugOrId = $content->slug ?: $content->id;

        return $this->render([
            'meta_title' => $content->meta_title ?: $content->name,
            'meta_keywords' => $content->meta_keywords ?? null,
            'short_description' => $this->description(
                $content->meta_description
                    ?? $content->short_description
                    ?? $content->short_desc
                    ?? $content->description
                    ?? null
            ),
            'seo_image' => $this->typedImage($content, $pageType),
            'google_site_verification' => $content->google_site_verification ?? null,
            'canonical_url' => $content->canonical_url ?: url($pathPrefix . $slugOrId),
        ]);
    }

    private function render(array $meta = [])
    {
        return view('react-modernization', [
            'entertainment' => (object) $meta,
        ]);
    }

    private function findBySlugOrId($query, string $id): ?Model
    {
        return $query
            ->where('status', 1)
            ->where(function ($inner) use ($id) {
                $inner->where('slug', $id);

                if (ctype_digit($id)) {
                    $inner->orWhere('id', (int) $id);
                }
            })
            ->first();
    }

    private function typedImage(Model $model, string $pageType): ?string
    {
        foreach (['seo_image', 'poster_tv_url', 'poster_url', 'thumbnail_url', 'thumb_url'] as $field) {
            $value = $model->{$field} ?? null;

            if (! empty($value)) {
                if ($field === 'seo_image') {
                    return $this->seoImage($value);
                }

                return setBaseUrlWithFileName($value, 'image', $pageType);
            }
        }

        return null;
    }

    private function seoImage(?string $image): ?string
    {
        if (empty($image)) {
            return null;
        }

        if (filter_var($image, FILTER_VALIDATE_URL)) {
            return $image;
        }

        $fileName = basename((string) parse_url($image, PHP_URL_PATH) ?: $image);
        $localPath = 'storage/uploads/seo/' . $fileName;

        if (file_exists(public_path($localPath))) {
            return asset($localPath);
        }

        return setBaseUrlWithFileName($image, 'image', 'video');
    }

    private function v2Image(?string $image): ?string
    {
        return ! empty($image) ? setBaseUrlWithFileNameV2($image) : null;
    }

    private function authorChannelImage(AuthorChannel $channel): string
    {
        foreach ([$channel->banner, $channel->avatar] as $image) {
            if (! empty($image)) {
                return setBaseUrlWithFileNameV2($image);
            }
        }

        $video = $channel->videos()
            ->where('status', 1)
            ->orderByDesc('videos.updated_at')
            ->first(['videos.id', 'videos.thumbnail_url', 'videos.poster_url', 'videos.poster_tv_url']);

        foreach ([$video?->poster_tv_url, $video?->poster_url, $video?->thumbnail_url] as $image) {
            if (! empty($image)) {
                return setBaseUrlWithFileNameV2($image);
            }
        }

        return asset('default-image/Default-Image.jpg');
    }

    private function onDemandIndexImage(): string
    {
        $channel = AuthorChannel::query()
            ->where('is_active', 1)
            ->where(function ($query) {
                $query->whereIn('username', ['ezwaytv', 'ezway-tv', 'ezway'])
                    ->orWhereRaw('LOWER(name) IN (?, ?)', ['ezway tv', 'ezway tv channel'])
                    ->orWhereRaw('LOWER(name) LIKE ?', ['%ezway%tv%']);
            })
            ->orderByRaw("
                CASE
                    WHEN username = 'ezwaytv' THEN 0
                    WHEN username = 'ezway-tv' THEN 1
                    WHEN LOWER(name) = 'ezway tv channel' THEN 2
                    WHEN LOWER(name) = 'ezway tv' THEN 3
                    ELSE 4
                END
            ")
            ->first();

        if (! $channel) {
            $channel = AuthorChannel::query()
                ->where('is_active', 1)
                ->orderByDesc('updated_at')
                ->first();
        }

        return $channel ? $this->authorChannelPosterImage($channel) : asset('default-image/Default-Image.jpg');
    }

    private function authorChannelPosterImage(AuthorChannel $channel): string
    {
        foreach ([$channel->banner, $channel->avatar] as $image) {
            if (! empty($image)) {
                return setBaseUrlWithFileNameV2($image);
            }
        }

        $video = $channel->videos()
            ->where('status', 1)
            ->orderByDesc('videos.updated_at')
            ->first(['videos.id', 'videos.poster_tv_url', 'videos.poster_url', 'videos.thumbnail_url']);

        foreach ([$video?->poster_tv_url, $video?->poster_url, $video?->thumbnail_url] as $image) {
            if (! empty($image)) {
                return setBaseUrlWithFileNameV2($image);
            }
        }

        return asset('default-image/Default-Image.jpg');
    }

    private function description(?string $description): ?string
    {
        if (empty($description)) {
            return null;
        }

        return Str::limit(trim(preg_replace('/\s+/', ' ', strip_tags($description))), 160, '...');
    }
}
