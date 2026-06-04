<div class="channel-block">
    <div class="d-flex align-items-center justify-content-between my-2 me-2">
        <h5 class="main-title text-capitalize mb-0">{{ $title }}</h5>
        <a href="{{ route('author_channels.index') }}" class="view-all-button text-decoration-none flex-none">
            <span>{{ __('frontend.view_all') }}</span>
            <i class="ph ph-caret-right"></i>
        </a>
    </div>
    <div class="card-style-slider {{ count($channels) <= 6 ? 'slide-data-less' : '' }}">
        <div class="slick-general slick-general-ondemand-section" data-items="5.5" data-items-desktop="5.5"
            data-items-laptop="4.5" data-items-tab="3.5" data-items-mobile-sm="2.5" data-items-mobile="1.5"
            data-speed="1000" data-autoplay="false" data-center="false" data-infinite="false"
            data-navigation="true" data-pagination="false" data-spacing="12">
            @foreach ($channels as $channel)
                <div class="slick-item">
                    <a href="{{ route('author_channels.show', $channel['username']) }}"
                        class="d-block text-decoration-none text-reset">
                        <div class="ondemand-channel-card position-relative overflow-hidden rounded">
                            <img src="{{ $channel['banner_url'] ?? asset('img/no-image.jpg') }}"
                                alt="{{ $channel['name'] }}" class="img-fluid object-cover w-100 rounded"
                                style="aspect-ratio: 16 / 9;">
                        </div>
                        <h6 class="mt-2 mb-0 text-truncate">{{ $channel['name'] }}</h6>
                    </a>
                </div>
            @endforeach
        </div>
    </div>
</div>
