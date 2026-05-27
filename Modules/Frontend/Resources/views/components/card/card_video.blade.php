@foreach ($values as $data)
    <div class="slick-item">
        <div class="iq-card card-hover entainment-slick-card ac-video-card"
             data-movie-id="{{ $data['id'] }}"
             data-movie-data="{{ json_encode($data) }}"
             data-preview="{{ (!empty($data['trailer_url']) && ($data['trailer_url_type'] ?? '') === 'Local') ? setBaseUrlWithFileName($data['trailer_url'], 'video', 'video') : '' }}"
             data-is-search="{{ isset($is_search) && $is_search == 1 ? 1 : null }}">

            <div class="block-images position-relative w-100">

                @if (isset($is_search) && $is_search == 1)
                    <a href="{{ route('video-details', ['id' => $data['slug'], 'is_search' => request()->has('search') ? 1 : null, 'autoplay' => 1]) }}"
                        class="position-absolute top-0 bottom-0 start-0 end-0 w-100 h-100"></a>
                @else
                    <a href="{{ route('video-details', ['id' => $data['slug'], 'autoplay' => 1]) }}"
                        class="position-absolute top-0 bottom-0 start-0 end-0 w-100 h-100"></a>
                @endif

                {{-- Thumbnail --}}
                <div class="image-box w-100 ac-thumb-wrapper" style="overflow:hidden;">
                    <img src="{{ $data['poster_image'] }}" alt="{{ $data['name'] ?? 'video' }}"
                         class="img-fluid object-cover w-100 d-block border-0 ac-thumb-img"
                         loading="lazy" style="transition:opacity .25s;">
                    <video class="ac-preview-video position-absolute top-0 start-0 w-100 h-100"
                           muted playsinline preload="none"
                           style="object-fit:cover;opacity:0;transition:opacity .3s;pointer-events:none;"></video>
                </div>

                {{-- Play button --}}
                <div class="ac-play-btn d-flex align-items-center justify-content-center rounded-circle"
                     style="width:44px;height:44px;background:rgba(255,255,255,0.18);
                            backdrop-filter:blur(6px);-webkit-backdrop-filter:blur(6px);
                            border:1.5px solid rgba(255,255,255,0.45);
                            pointer-events:none;">
                    <i class="ph-fill ph-play" style="font-size:20px;color:#fff;margin-left:2px;"></i>
                </div>

                {{-- Badges (pay-per-view / premium) --}}
                @if (!empty($data['is_pay_per_view']))
                    @if (!empty($data['is_purchased']))
                        <span class="product-rent"><i class="ph ph-film-reel"></i> {{ __('messages.rented') }}</span>
                    @else
                        <span class="product-rent"><i class="ph ph-film-reel"></i> {{ __('messages.rent') }}</span>
                    @endif
                @elseif (!empty($data['show_premium_badge']))
                    <button type="button" class="product-premium border-0" data-bs-toggle="tooltip"
                        data-bs-placement="top" data-bs-title="{{ __('messages.lbl_premium') }}">
                        <i class="ph ph-crown-simple"></i>
                    </button>
                @endif

                {{-- Duration badge --}}
                @if (!empty($data['duration']))
                <div class="position-absolute bottom-0 end-0 m-1" style="pointer-events:none;z-index:2;">
                    <span class="badge bg-dark bg-opacity-75 font-size-11 px-1">{{ formatDuration($data['duration']) }}</span>
                </div>
                @endif

                {{-- Preview loading spinner --}}
                <div class="ac-preview-loader position-absolute top-0 start-0 w-100 h-100
                            d-flex align-items-center justify-content-center"
                     style="opacity:0;transition:opacity .2s;pointer-events:none;">
                    <div class="spinner-border spinner-border-sm text-white" role="status"
                         style="width:18px;height:18px;border-width:2px;"></div>
                </div>

            </div>

            {{-- Title below thumbnail --}}
            <div class="card-description p-2" style="min-height:48px;">
                <h6 class="iq-title text-capitalize line-count-2 mb-0 font-size-14">{{ $data['name'] ?? '--' }}</h6>
            </div>

        </div>
    </div>
@endforeach
