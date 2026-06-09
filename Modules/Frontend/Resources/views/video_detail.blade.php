@extends('frontend::layouts.master', ['entertainment' => $entertainment])

@section('title')
    {{ $data['data']['name'] ?? '' }}
@endsection

@section('content')

    @php
        $data = $data['data'];
        $ondemandChannelId = (int) request()->query('ondemand_channel', 0);
        $ondemandChannel = null;

        if ($ondemandChannelId > 0 && !empty($data['author_channels'])) {
            $ondemandChannel = collect($data['author_channels'])->firstWhere('id', $ondemandChannelId);
        }

        $statsContentType = $ondemandChannel ? 'ondemand_video' : 'video';
        $moreItems = [];

        if (!empty($data['more_items'])) {
            $moreItems = method_exists($data['more_items'], 'toArray')
                ? $data['more_items']->toArray(request())
                : (array) $data['more_items'];
        }

        $reactVideo = $data;
        $reactVideo['more_items'] = $moreItems;
        $reactVideo['ondemand_channel_context'] = $data['ondemand_channel_context'] ?? null;

        $payPerViewUrl = null;
        if (($data['access'] ?? null) === 'pay-per-view' && !\Modules\Entertainment\Models\Entertainment::isPurchased($data['id'], 'video')) {
            $payPerViewUrl = route('pay-per-view.paymentform', ['id' => $data['id'], 'type' => 'video']);
        }

        $reactPayload = [
            'video' => $reactVideo,
            'statsContentType' => $statsContentType,
            'statsChannelId' => $ondemandChannel['id'] ?? null,
            'continueWatch' => (bool) $continue_watch,
            'currentUrl' => request()->fullUrl(),
            'legacyVideosUrl' => route('videos'),
            'payPerViewUrl' => $payPerViewUrl,
        ];
    @endphp

    <script>
        window.__EZWAY_VIDEO_DETAIL__ = @json($reactPayload);
    </script>

    <section class="ez-react-video-player-shell">
        <div id="thumbnail-section">
            @if ($continue_watch === true)
                @include('frontend::components.section.thumbnail', [
                    'data' => $data['video_url_input'],
                    'type' => $data['video_upload_type'],
                    'thumbnail_image' => $data['poster_image'],
                    'watched_time' => $data['watched_time'],
                    'subtitle_info' => $data['subtitle_info'],
                    'dataAccess' => $data['access'],
                    'plan_id' => $data['plan_id'] ?? null,
                    'content_type' => 'video',
                    'content_id' => $data['id'],
                    'stat_content_type' => $statsContentType,
                    'stat_channel_id' => $ondemandChannel['id'] ?? null,
                    'is_trailer' => false,
                    'video_type' => $data['video_upload_type'],
                    'content_video_type' => 'video',
                ])
            @else
                @include('frontend::components.section.thumbnail', [
                    'data' => $data['trailer_url'],
                    'type' => $data['trailer_url_type'],
                    'thumbnail_image' => $data['poster_image'],
                    'watched_time' => 0,
                    'subtitle_info' => $data['subtitle_info'],
                    'dataAccess' => $data['access'],
                    'plan_id' => $data['plan_id'] ?? null,
                    'content_type' => 'video',
                    'content_id' => $data['id'],
                    'stat_content_type' => $statsContentType,
                    'stat_channel_id' => $ondemandChannel['id'] ?? null,
                    'is_trailer' => true,
                    'video_type' => $data['video_upload_type'],
                    'content_video_type' => 'trailer',
                ])
            @endif
        </div>
    </section>

    <div id="react-modernization-root"></div>
    @vite('resources/react/main.tsx')

    @if($data['is_clips_enabled'])
        @include('frontend::components.section.clips_trailers', ['clips' => $data['clips'] ?? []])
    @endif

    <div class="container-fluid">
        <div class="overflow-hidden">
            @include('frontend::components.section.custom_ad_banner', [
                'placement' => 'video_detail',
                'content_id' => $data['id'] ?? '',
                'content_type' => $data['type'] ?? '',
                'category_id' => $data['category_id'] ?? '',
            ])
        </div>
    </div>

    <div class="modal fade" id="DeviceSupport" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content position-relative">
                <div class="modal-body user-login-card m-0 p-4 position-relative">
                    <button type="button" class="btn btn-primary custom-close-btn rounded-2" data-bs-dismiss="modal">
                        <i class="ph ph-x text-white fw-bold align-middle"></i>
                    </button>

                    <div class="modal-body">
                        {{ __('frontend.device_not_support') }}
                    </div>

                    <div class="d-flex align-items-center justify-content-center">
                        <a href="{{ Auth::check() ? route('subscriptionPlan') : route('login') }}"
                            class="btn btn-primary mt-5">{{ __('frontend.upgrade') }}</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

@endsection

@push('ezstats-meta')
<script>
    window._ezPageMeta = {
        content_type: @json($statsContentType),
        content_id: {{ (int)($data['id'] ?? 0) }},
        channel_id: {{ (int)($ondemandChannel['id'] ?? 0) ?: 'null' }}
    };
</script>
@endpush
