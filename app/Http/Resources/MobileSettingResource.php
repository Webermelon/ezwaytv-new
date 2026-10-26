<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Entertainment\Models\Entertainment;
use Modules\Constant\Models\Constant;
use Modules\LiveTV\Models\LiveTvChannel;
use Modules\CastCrew\Models\CastCrew;
use Modules\Genres\Models\Genres;
use Modules\Entertainment\Transformers\MoviesResource;
use Modules\LiveTV\Transformers\LiveTvChannelResource;
use Modules\CastCrew\Transformers\CastCrewListResource;
use Modules\Entertainment\Transformers\TvshowResource;
use Modules\Genres\Transformers\GenresResource;
use Modules\LiveTV\Models\LiveTvCategory;
use Modules\LiveTV\Transformers\LiveTvCategoryResource;
use Modules\Video\Models\Video;
use Modules\Video\Transformers\VideoResource;
use App\Models\AuthorChannel;

class MobileSettingResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request)
    {
        $data = null;
        if($this->value !== Null){
            switch($this->slug){
                case 'banner':
                    $data = [];
                    break;
                case 'top-10':
                    $topMovieIds = json_decode($this->value);
                    $topMovies = $this->sortBySelectedIds(Entertainment::whereIn('id',$topMovieIds)->get(), $topMovieIds);
                    $data = MoviesResource::collection($topMovies);
                    break;
                case 'advertisement':
                    $data = [];
                    break;
                case 'latest-movies':
                    $latestMovieIds = json_decode($this->value);
                    $latestMovies = $this->sortBySelectedIds(Entertainment::whereIn('id',$latestMovieIds)->get(), $latestMovieIds);
                    $data = MoviesResource::collection($latestMovies);
                    break;
                case 'enjoy-in-your-native-tongue':
                    $languageIds = json_decode($this->value);
                    $languages = $this->sortBySelectedIds(Constant::whereIn('id',$languageIds)->get(), $languageIds);
                    $data = $languages;
                    break;
                case 'popular-movies':
                    $popularMovieIds = json_decode($this->value);
                    $popularMovies = $this->sortBySelectedIds(Entertainment::whereIn('id',$popularMovieIds)->get(), $popularMovieIds);
                    $data = MoviesResource::collection($popularMovies);
                    break;
                case 'popular-tvshows':
                    $popularTVshowIds = json_decode($this->value);
                    $popularTVshows = $this->sortBySelectedIds(Entertainment::whereIn('id',$popularTVshowIds)->get(), $popularTVshowIds);
                    $data = TvshowResource::collection($popularTVshows);
                    break;
                // case 'popular-tvcategories':
                //     $popularCategoryIds = json_decode($this->value);
                //     $popularCategories = LiveTvCategory::whereIn('id',$popularCategoryIds)->get();
                //     $data = LiveTvCategoryResource::collection($popularCategories);
                //     break;
                case 'popular-videos':
                    $popularVideoIds = json_decode($this->value);
                    $popularVideos = $this->sortBySelectedIds(Video::whereIn('id',$popularVideoIds)->get(), $popularVideoIds);
                    $data = VideoResource::collection($popularVideos);
                    break;
                case 'top-channels':
                    $channelIds = json_decode($this->value);
                    $channels = $this->sortBySelectedIds(LiveTvChannel::whereIn('id',$channelIds)->get(), $channelIds);
                    $data = LiveTvChannelResource::collection($channels);
                    break;
                case 'your-favorite-personality':
                    $castIds = json_decode($this->value);
                    $casts = $this->sortBySelectedIds(CastCrew::whereIn('id',$castIds)->get(), $castIds);
                    $data = CastCrewListResource::collection($casts);
                    break;
                case '500-free-movies':
                    $movieIds = json_decode($this->value);
                    $movies = $this->sortBySelectedIds(Entertainment::whereIn('id',$movieIds)->get(), $movieIds);
                    $data = MoviesResource::collection($movies);
                    break;
                case 'genre':
                    $genreIds = json_decode($this->value);
                    $genres = $this->sortBySelectedIds(Genres::whereIn('id',$genreIds)->get(), $genreIds);
                    $data = GenresResource::collection($genres);
                    break;
                case 'rate-our-app':
                    $data = [];
                    break;
                default:
                    if ($this->type === 'ondemand') {
                        $channelIds = json_decode($this->value);
                        $channels = $this->sortBySelectedIds(AuthorChannel::whereIn('id', $channelIds)
                            ->where('is_active', 1)
                            ->get(), $channelIds);
                        $data = $channels->map(function ($channel) {
                            return [
                                'id' => $channel->id,
                                'name' => $channel->name,
                                'username' => $channel->username,
                                'description' => $channel->description,
                                'cover_image_url' => $channel->banner ? setBaseUrlWithFileNameV2($channel->banner) : null,
                                'avatar_image_url' => $channel->avatar ? setBaseUrlWithFileNameV2($channel->avatar) : null,
                                'profile_url' => url('/on-demand/' . $channel->username),
                            ];
                        });
                    }
                    break;
            }
        }


        return [
            'name' => $this->name,
            'section_type' => $this->slug,
            'data' => $data
        ];
    }

    private function sortBySelectedIds($collection, $selectedIds)
    {
        if (!is_iterable($selectedIds)) {
            return $collection;
        }

        $selectedOrder = array_flip(array_map('strval', array_values((array) $selectedIds)));

        return $collection
            ->sortBy(fn ($item) => $selectedOrder[(string) $item->id] ?? PHP_INT_MAX)
            ->values();
    }
}
