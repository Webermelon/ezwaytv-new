@php
    $adSlides = \Modules\Ad\Models\AdBannerSlide::getActiveByPlacement($placement ?? 'home');
    $autoplaySpeed = (int) setting('ad_banner_autoplay_speed', 3000);
    $direction     = setting('ad_banner_direction', 'ltr') === 'rtl' ? 'true' : 'false';
@endphp

@if($adSlides->isNotEmpty())
<div class="ad-banner-slider-section">
    <div class="ad-banner-slider-inner">
        <div class="ad-banner-slider"
             data-autoplay="true"
             data-autoplay-speed="3000"
             data-slides="{{ $adSlides->count() }}">
            @foreach($adSlides as $slide)
                <div class="ad-banner-slide">
                    @if($slide->link_url)
                        <a href="{{ $slide->link_url }}" target="_blank" rel="noopener noreferrer">
                    @endif
                        <img src="{{ $slide->image }}"
                             alt="{{ $slide->title ?? __('messages.advertisement') }}"
                             class="ad-banner-img"
                             loading="lazy">
                    @if($slide->link_url)
                        </a>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
</div>

@push('after-styles')
<style>
/* Outer full-width dark background strip — breaks out of any container */
.ad-banner-slider-section {
    width: 100vw;
    position: relative;
    left: 50%;
    transform: translateX(-50%);
    background-color: #000;
    padding: 14px 0;
    overflow: hidden;
}

/* Centered container — max 1100px on desktop, full-width on mobile */
.ad-banner-slider-inner {
    max-width: 1100px;
    margin: 0 auto;
    padding: 0 12px;
}

.ad-banner-slider .slick-slide {
    outline: none;
}

/* Desktop: 400px tall, max 1100px wide */
.ad-banner-img {
    width: 100%;
    height: 400px;
    object-fit: cover;
    border-radius: 6px;
    display: block;
}

/* Mobile / full-width: 200px tall */
@media (max-width: 767px) {
    .ad-banner-img {
        height: 200px;
    }
    .ad-banner-slider-inner {
        padding: 0;
    }
    .ad-banner-img {
        border-radius: 0;
    }
}

.ad-banner-slider .slick-dots {
    bottom: 8px;
}
.ad-banner-slider .slick-dots li button:before {
    color: #fff;
    font-size: 8px;
    opacity: 0.7;
}
.ad-banner-slider .slick-dots li.slick-active button:before {
    opacity: 1;
}
</style>
@endpush

@push('after-scripts')
<script>
(function () {
    var $slider = $('.ad-banner-slider[data-slides]').not('.slick-initialized');
    if ($slider.length) {
        $slider.slick({
            autoplay: true,
            autoplaySpeed: {{ $autoplaySpeed }},
            speed: 600,
            arrows: false,
            dots: $slider.data('slides') > 1,
            infinite: true,
            pauseOnHover: true,
            adaptiveHeight: false,
            rtl: {{ $direction }},
        });
    }
})();
</script>
@endpush
@endif
