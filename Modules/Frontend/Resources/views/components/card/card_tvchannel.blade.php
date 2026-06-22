<div class="col">
    @php
        $channelName = $value['name'] ?? data_get($value, 'details.name', '');
        $channelSlug = $value['slug'] ?? data_get($value, 'details.slug', '');
        $stats = $value['stats'] ?? data_get($value, 'details.stats', []);
        $showViews = (bool) data_get($stats, 'show_views_frontend', false);
        $totalViews = (int) data_get($stats, 'total_views', 0);
        $formatStat = function ($number) {
            $number = (int) $number;
            if ($number >= 1000000) {
                return rtrim(rtrim(number_format($number / 1000000, 1), '0'), '.') . 'M';
            }
            if ($number >= 1000) {
                return rtrim(rtrim(number_format($number / 1000, 1), '0'), '.') . 'K';
            }
            return (string) $number;
        };
    @endphp
    <a href="{{ route('livetv-details', ['id' => $channelSlug]) }}"
        class="livetv-card d-block position-relative">
        <div class="image-box w-100 position-relative">
            <img src="{{ $value['poster_image'] }}" alt="{{ $channelName }}"
                class="livetv-img object-cover img-fluid w-100 rounded">
            @if (!empty($value['show_premium_badge']))
                <button type="button" class="product-premium border-0" data-bs-toggle="tooltip"
                    data-bs-placement="top" data-bs-title="{{ __('messages.lbl_premium') }}">
                    <i class="ph ph-crown-simple"></i>
                </button>
            @endif

            <span class="live-card-badge">
                <span
                    class="live-badge fw-semibold text-uppercase">{{ __('frontend.live') }}</span>
            </span>
        </div>
    </a>
    <div class="livetv-title mt-2 text-center">
        <a href="{{ route('livetv-details', ['id' => $channelSlug]) }}" class="d-block text-truncate text-decoration-none text-reset">
            <h6 class="mb-0">{{ $channelName }}</h6>
        </a>
        @if ($showViews)
            <div class="small text-muted mt-1">
                <i class="ph ph-eye"></i> {{ $formatStat($totalViews) }} views
            </div>
        @endif
    </div>
</div>
