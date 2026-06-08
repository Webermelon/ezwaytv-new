@extends('frontend::layouts.master')

@section('title')
    {{ $channel->name }}
@endsection

@section('content')
@php
    $bannerUrl = $channel->banner ? setBaseUrlWithFileNameV2($channel->banner) : null;
    $avatarUrl = $channel->avatar ? setBaseUrlWithFileNameV2($channel->avatar) : null;
    $channelPosterUrl = $bannerUrl ?: ($avatarUrl ?: asset('default-image/Default-Image.jpg'));
    $videoCount = $channel->videos->count();
@endphp

{{-- Banner --}}
<div class="yt-banner-outer">
    <div class="yt-channel-container">
        <div class="yt-banner">
            @if($bannerUrl)
                <img src="{{ $bannerUrl }}" alt="{{ $channel->name }}" class="yt-banner-img">
            @else
                <div class="yt-banner-placeholder"></div>
            @endif
        </div>
    </div>
</div>

{{-- Channel Header --}}
<div class="yt-channel-header">
    <div class="yt-channel-container">
        <div class="yt-channel-row">

            {{-- Avatar --}}
            <div class="yt-avatar-wrap">
                @if($avatarUrl)
                    <img src="{{ $avatarUrl }}" alt="{{ $channel->name }}" class="yt-avatar">
                @else
                    <div class="yt-avatar yt-avatar-fallback">
                        <i class="ph ph-user"></i>
                    </div>
                @endif
            </div>

            {{-- Info --}}
            <div class="yt-channel-info">
                <h1 class="yt-channel-name">{{ $channel->name }}</h1>
                <div class="yt-channel-meta">
                    @if($channel->username)
                    <span>&#64;{{ $channel->username }}</span>
                    <span class="yt-dot">·</span>
                    @endif
                    <span><strong>{{ $videoCount }}</strong> {{ Str::plural('video', $videoCount) }}</span>
                    @if($channel->user)
                    <!-- <span class="yt-dot">·</span> -->
                    <!-- <span>{{ $channel->user->first_name }} {{ $channel->user->last_name }}</span> -->
                    @endif
                </div>
                @if($channel->description)
                <div class="yt-desc-wrap">
                    <span class="yt-desc ac-desc-clamped">{{ $channel->description }}</span>
                    <button class="yt-more-btn ac-see-more-btn" type="button">more</button>
                </div>
                @endif
            </div>

        </div>
    </div>
</div>

{{-- Tab bar --}}
<div class="yt-tabs">
    <div class="yt-channel-container">
        <div class="yt-tab active">Videos</div>
    </div>
</div>

{{-- Videos Section --}}
<div class="yt-content">
    <div class="yt-channel-container">

        @if($channel->videos->isEmpty())
            <div class="ac-empty-state">
                <i class="ph ph-film-slate"></i>
                <h4>No videos yet</h4>
                <p>This channel hasn't published any videos.</p>
            </div>
        @else

        <div class="row g-3 g-md-4 row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 row-cols-xl-5">
            @foreach($channel->videos as $video)
            @php
                $thumb = $video->thumbnail_url ?: $video->poster_url;
                $thumbUrl = $thumb ? setBaseUrlWithFileNameV2($thumb) : $channelPosterUrl;
                $previewUrl = null;
                if (!empty($video->trailer_url) && $video->trailer_url_type === 'Local') {
                    $previewUrl = setBaseUrlWithFileName($video->trailer_url, 'video', 'video');
                } elseif ($video->video_upload_type === 'Local' && !empty($video->video_url_input)) {
                    $previewUrl = setBaseUrlWithFileName($video->video_url_input, 'video', 'video');
                }
            @endphp
            <div class="col">
                <div class="ac-video-card" data-preview="{{ $previewUrl ?? '' }}">
                    <a href="{{ route('video-details', ['id' => $video->slug, 'autoplay' => 1, 'ondemand_channel' => $channel->id]) }}"
                       class="ac-video-link"
                       aria-label="{{ $video->name }}">

                        <div class="ac-video-thumb">
                            <div class="ac-thumb-inner">
                                <img src="{{ $thumbUrl }}" alt="{{ $video->name }}" class="ac-thumb-img" loading="lazy" onerror="this.onerror=null;this.src='{{ asset('default-image/Default-Image.jpg') }}';">
                                <video class="ac-preview-video" muted playsinline preload="none"></video>
                                <div class="ac-video-overlay"></div>
                                <div class="ac-play-button">
                                    <div class="ac-play-button-inner">
                                        <i class="ph-fill ph-play"></i>
                                    </div>
                                    <div class="ac-play-ripple"></div>
                                </div>
                                @if($video->duration)
                                <div class="ac-duration-badge">{{ formatDuration($video->duration) }}</div>
                                @endif
                                <div class="ac-preview-loader">
                                    <div class="spinner-border spinner-border-sm" role="status">
                                        <span class="visually-hidden">Loading...</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="ac-video-info">
                            <h6 class="ac-video-title">{{ $video->name }}</h6>
                            @if($video->views_count ?? false)
                            <span class="ac-video-views">
                                <i class="ph ph-eye me-1"></i>{{ number_format($video->views_count) }} views
                            </span>
                            @endif
                        </div>
                    </a>
                </div>
            </div>
            @endforeach
        </div>

        @endif
    </div>
</div>

@push('after-styles')
<style>
/* ============================
   YOUTUBE-STYLE CHANNEL PAGE
   ============================ */

/* Banner */
.yt-banner-outer {
    background: #0d1117;
    padding-top: 16px;
}
.yt-banner {
    width: 100%;
    height: 400px;
    overflow: hidden;
    background: #1a1a2e;
    border-radius: 12px;
}
.yt-banner-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    object-position: center;
    display: block;
}
.yt-banner-placeholder {
    width: 100%;
    height: 100%;
    background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
}

/* Channel Header */
.yt-channel-header {
    background: #0d1117;
    padding: 20px 0 0;
}
.yt-channel-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 0 24px;
}
.yt-channel-row {
    display: flex;
    align-items: center;
    gap: 20px;
}

/* Avatar */
.yt-avatar-wrap {
    flex-shrink: 0;
    margin-top: -55px;
}
.yt-avatar {
    width: 110px;
    height: 110px;
    border-radius: 50%;
    object-fit: cover;
    border: 4px solid #0d1117;
    display: block;
}
.yt-avatar-fallback {
    width: 110px;
    height: 110px;
    border-radius: 50%;
    border: 4px solid #0d1117;
    background: linear-gradient(135deg, #667eea, #764ba2);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2.75rem;
    color: #fff;
}

/* Channel Info */
.yt-channel-info {
    flex: 1;
    min-width: 0;
    padding-top: 4px;
    padding-bottom: 16px;
}
.yt-channel-name {
    font-size: 1.625rem;
    font-weight: 700;
    color: #fff;
    margin: 0 0 6px;
    line-height: 1.25;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.yt-channel-meta {
    font-size: 0.8125rem;
    color: #aaa;
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 4px;
    margin-bottom: 8px;
}
.yt-dot {
    color: #555;
}

/* Description */
.yt-desc-wrap {
    font-size: 0.8125rem;
    color: #aaa;
    line-height: 1.5;
}
.yt-desc {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.yt-desc.ac-desc-expanded {
    display: block;
    -webkit-line-clamp: unset;
    overflow: visible;
}
.yt-more-btn {
    background: none;
    border: none;
    padding: 0;
    cursor: pointer;
    font-size: 0.8125rem;
    font-weight: 600;
    color: #fff;
    display: none;
    vertical-align: baseline;
}
.yt-more-btn:hover {
    color: #aaa;
}

/* Tab Bar */
.yt-tabs {
    background: #0d1117;
    border-bottom: 1px solid #272727;
    margin-bottom: 0;
}
.yt-tabs .yt-channel-container {
    display: flex;
    align-items: center;
    gap: 4px;
}
.yt-tab {
    padding: 12px 16px;
    font-size: 0.875rem;
    font-weight: 500;
    color: #aaa;
    cursor: pointer;
    border-bottom: 2px solid transparent;
    transition: color 0.15s, border-color 0.15s;
    white-space: nowrap;
}
.yt-tab.active {
    color: #fff;
    border-bottom-color: #fff;
}

/* Content */
.yt-content {
    background: #0d1117;
    padding: 24px 0 48px;
    min-height: 300px;
}

/* Empty State */
.ac-empty-state {
    text-align: center;
    padding: 4rem 2rem;
}
.ac-empty-state i {
    font-size: 4rem;
    color: rgba(255,255,255,0.2);
    margin-bottom: 1rem;
    display: block;
}
.ac-empty-state h4 { color: rgba(255,255,255,0.7); margin-bottom: 0.5rem; }
.ac-empty-state p  { color: rgba(255,255,255,0.5); }

/* ============================
   VIDEO CARDS
   ============================ */
.ac-video-card {
    position: relative;
    transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}
.ac-video-card:hover { transform: translateY(-6px); }
.ac-video-link { display: block; text-decoration: none; color: inherit; }

.ac-video-thumb {
    position: relative;
    overflow: hidden;
    border-radius: 10px;
    background: #000;
    box-shadow: 0 4px 16px rgba(0,0,0,0.4);
    transition: box-shadow 0.3s ease;
}
.ac-video-card:hover .ac-video-thumb {
    box-shadow: 0 8px 30px rgba(102,126,234,0.25);
}
.ac-thumb-inner {
    position: relative;
    width: 100%;
    aspect-ratio: 16/9;
    overflow: hidden;
}
.ac-thumb-img, .ac-preview-video {
    position: absolute; top: 0; left: 0;
    width: 100%; height: 100%;
    object-fit: cover;
    transition: all 0.3s ease;
}
.ac-thumb-img { z-index: 1; }
.ac-preview-video { z-index: 2; opacity: 0; pointer-events: none; }
.ac-video-card.ac-playing .ac-thumb-img { opacity: 0; }
.ac-video-card.ac-playing .ac-preview-video { opacity: 1; }
.ac-video-card:hover .ac-thumb-img { transform: scale(1.04); }

.ac-video-overlay {
    position: absolute; top: 0; left: 0; right: 0; bottom: 0;
    background: linear-gradient(180deg, transparent 50%, rgba(0,0,0,0.5) 100%);
    opacity: 0; transition: opacity 0.3s ease; z-index: 3; pointer-events: none;
}
.ac-video-card:hover .ac-video-overlay { opacity: 1; }

.ac-play-button {
    position: absolute; top: 50%; left: 50%;
    transform: translate(-50%, -50%);
    z-index: 4; pointer-events: none;
}
.ac-play-button-inner {
    position: relative;
    width: 52px; height: 52px;
    display: flex; align-items: center; justify-content: center;
    background: rgba(255,255,255,0.18);
    backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px);
    border-radius: 50%; border: 2px solid rgba(255,255,255,0.45);
    transition: all 0.25s ease; z-index: 2;
}
.ac-play-button-inner i { font-size: 22px; color: #fff; margin-left: 3px; }
.ac-play-ripple {
    position: absolute; top: 50%; left: 50%;
    transform: translate(-50%,-50%);
    width: 52px; height: 52px;
    border-radius: 50%; background: rgba(255,255,255,0.25); opacity: 0; z-index: 1;
}
.ac-video-card:hover .ac-play-button-inner {
    transform: scale(1.12);
    background: rgba(255,255,255,0.28);
    border-color: rgba(255,255,255,0.75);
}
.ac-video-card:hover .ac-play-ripple { animation: ripple-effect 1.5s ease-out infinite; }
@keyframes ripple-effect {
    0%   { transform: translate(-50%,-50%) scale(1); opacity: 0.5; }
    100% { transform: translate(-50%,-50%) scale(2); opacity: 0; }
}
.ac-video-card.ac-playing .ac-play-button { opacity: 0; }

.ac-duration-badge {
    position: absolute; bottom: 8px; right: 8px;
    background: rgba(0,0,0,0.85); backdrop-filter: blur(6px);
    padding: 3px 7px; border-radius: 5px;
    font-size: 0.75rem; font-weight: 600; color: #fff;
    z-index: 4; pointer-events: none;
    border: 1px solid rgba(255,255,255,0.12);
}
.ac-preview-loader {
    position: absolute; top: 50%; left: 50%;
    transform: translate(-50%,-50%);
    opacity: 0; transition: opacity 0.2s ease; z-index: 5; pointer-events: none;
}
.ac-preview-loader .spinner-border { width: 22px; height: 22px; border-width: 3px; color: #fff; }
.ac-video-card.ac-loading .ac-preview-loader { opacity: 1; }

.ac-video-info { padding: 10px 2px 0; }
.ac-video-title {
    font-size: 0.9rem; font-weight: 600; color: #f1f1f1;
    margin-bottom: 4px; line-height: 1.4;
    display: -webkit-box; -webkit-line-clamp: 2;
    -webkit-box-orient: vertical; overflow: hidden;
    transition: color 0.2s ease;
}
.ac-video-card:hover .ac-video-title { color: #667eea; }
.ac-video-views {
    font-size: 0.78rem; color: #aaa;
    display: flex; align-items: center;
}
.ac-video-views i { font-size: 0.85rem; }

/* ============================
   RESPONSIVE
   ============================ */
@media (max-width: 992px) {
    .yt-banner { height: 320px; }
    .yt-channel-name { font-size: 1.375rem; }
}
@media (max-width: 768px) {
    .yt-banner { height: 220px; }
    .yt-avatar-wrap { margin-top: -40px; }
    .yt-avatar, .yt-avatar-fallback { width: 80px; height: 80px; }
    .yt-avatar-fallback { font-size: 2rem; }
    .yt-channel-name { font-size: 1.125rem; }
    .yt-channel-container { padding: 0 16px; }
    .yt-channel-row { gap: 14px; }
}
@media (max-width: 480px) {
    .yt-banner { height: 160px; }
    .yt-avatar-wrap { margin-top: -32px; }
    .yt-avatar, .yt-avatar-fallback { width: 64px; height: 64px; }
    .yt-avatar-fallback { font-size: 1.5rem; }
    .yt-channel-name { font-size: 1rem; }
    .yt-channel-meta { font-size: 0.75rem; }
}
</style>
@endpush

@push('ezstats-meta')
<script>
    window._ezPageMeta = {
        content_type: 'ondemand_channel',
        content_id: {{ (int) $channel->id }},
        channel_id: {{ (int) $channel->id }}
    };
</script>
@endpush

@push('after-scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.yt-desc-wrap').forEach(function (wrapper) {
        var desc = wrapper.querySelector('.yt-desc');
        var btn  = wrapper.querySelector('.yt-more-btn');
        if (!desc || !btn) return;

        if (desc.scrollHeight > desc.clientHeight + 2) {
            btn.style.display = 'inline';
        }

        btn.addEventListener('click', function () {
            var expanded = desc.classList.toggle('ac-desc-expanded');
            btn.textContent = expanded ? 'less' : 'more';
        });
    });
});
</script>
@endpush

@endsection
