@foreach ($values as $data)
    <div class="slick-item">
        <div class="iq-card card-hover entainment-slick-card" data-movie-id="{{ $data['id'] }}"
            data-movie-data="{{ json_encode($data) }}"
            data-is-search="{{ isset($is_search) && $is_search == 1 ? 1 : null }}">
            <div class="block-images position-relative w-100">

                @if (isset($is_search) && $is_search == 1)
                    <a href="{{ route('video-details', ['id' => $data['slug'], 'is_search' => request()->has('search') ? 1 : null]) }}"
                        class="position-absolute top-0 bottom-0 start-0 end-0 w-100 h-100">
                    </a>
                @else
                    <a href="{{ route('video-details', ['id' => $data['slug']]) }}"
                        class="position-absolute top-0 bottom-0 start-0 end-0 w-100 h-100">
                    </a>
                @endif

                <div class="image-box w-100">
                    <img src="{{ $data['poster_image'] }}" alt="movie-card"
                        class="img-fluid object-cover w-100 d-block border-0">
                    @if (!empty($data['is_pay_per_view']))
                        @if (!empty($data['is_purchased']))
                            <span class="product-rent">
                                <i class="ph ph-film-reel"></i> {{ __('messages.rented') }}
                            </span>
                        @else
                            <span class="product-rent">
                                <i class="ph ph-film-reel"></i> {{ __('messages.rent') }}
                            </span>
                        @endif
                    @elseif (!empty($data['show_premium_badge']))
                        <button type="button" class="product-premium border-0" data-bs-toggle="tooltip"
                            data-bs-placement="top" data-bs-title="{{ __('messages.lbl_premium') }}">
                            <i class="ph ph-crown-simple"></i>
                        </button>
                    @endif

                </div>

                <div class="card-description with-transition">
                    <div class="position-relative w-100">
                        <ul class="genres-list ps-0 mb-2 d-flex align-items-center gap-5">
                            @foreach (collect($data['genres'] ?? [])->slice(0, 2) as $gener)
                                <li class="small">{{ $gener['name'] ?? ($gener->resource->genre->name ?? '--') }}</li>
                            @endforeach
                        </ul>

                        <h5 class="iq-title text-capitalize line-count-1">{{ $data['name'] ?? '--' }}</h5>

                        <div class="d-flex align-items-center gap-3">
                            @if (!empty($data['duration']))
                                <div class="movie-time d-flex align-items-center gap-1 font-size-14">
                                    <i class="ph ph-clock"></i>
                                    {{ formatDuration($data['duration']) ?? '--' }}
                                </div>
                            @endif
                            @if (!empty($data['language']))
                                <div class="movie-language d-flex align-items-center gap-1 font-size-14">
                                    <i class="ph ph-translate"></i>
                                    <small>{{ ucfirst($data['language']) }}</small>
                                </div>
                            @endif
                        </div>

                        <div class="d-flex align-items-center gap-3 mt-3">
                            <div class="flex-grow-1">
                                <a href="{{ route('video-details', ['id' => $data['slug']]) }}" class="btn btn-primary w-100">
                                    {{ __('frontend.watch_now') }}
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
@endforeach
