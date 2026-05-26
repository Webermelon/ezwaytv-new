@extends('frontend::layouts.master')

@section('title')
    {{ $channel->name }}
@endsection

@section('content')
@php
    $bannerUrl = $channel->banner ? setBaseUrlWithFileNameV2($channel->banner) : null;
    $avatarUrl = $channel->avatar ? setBaseUrlWithFileNameV2($channel->avatar) : null;
    $videoCount = $channel->videos->count();
@endphp

{{-- Hero Banner --}}
<div class="ac-hero-banner position-relative">
    <div class="ac-hero-bg">
        @if($bannerUrl)
            <img src="{{ $bannerUrl }}" alt="{{ $channel->name }}" class="ac-banner-img">
        @else
            <div class="ac-banner-gradient"></div>
        @endif
    </div>
    
    {{-- Gradient Overlays --}}
    <div class="ac-hero-overlay"></div>
    <div class="ac-hero-bottom-gradient"></div>
    
    {{-- Channel Identity --}}
    <div class="container-fluid px-4 px-md-5 ac-hero-content">
        <div class="d-flex align-items-end gap-4 flex-wrap">
            
            {{-- Avatar with Glow Effect --}}
            <div class="ac-avatar-wrapper">
                <div class="ac-avatar-glow"></div>
                @if($avatarUrl)
                    <img src="{{ $avatarUrl }}" alt="{{ $channel->name }}" class="ac-avatar">
                @else
                    <div class="ac-avatar-placeholder">
                        <i class="ph ph-user"></i>
                    </div>
                @endif
            </div>

            {{-- Channel Info --}}
            <div class="ac-channel-info pb-3 flex-grow-1">
                <h1 class="ac-channel-name">{{ $channel->name }}</h1>
                <div class="ac-channel-meta">
                    <span class="ac-meta-item">
                        <i class="ph ph-film-reel me-1"></i>
                        <strong>{{ $videoCount }}</strong> {{ Str::plural('video', $videoCount) }}
                    </span>
                    @if($channel->user)
                    <span class="ac-meta-separator">•</span>
                    <span class="ac-meta-item">
                        <i class="ph ph-user-circle me-1"></i>
                        {{ $channel->user->first_name }} {{ $channel->user->last_name }}
                    </span>
                    @endif
                </div>
                @if($channel->description)
                <p class="ac-channel-description">{{ $channel->description }}</p>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- Videos Section --}}
<div class="container-fluid px-4 px-md-5 py-5 ac-videos-section">

    @if($channel->videos->isEmpty())
        <div class="ac-empty-state">
            <i class="ph ph-film-slate"></i>
            <h4>No videos yet</h4>
            <p>This channel hasn't published any videos.</p>
        </div>
    @else

    <div class="ac-section-header">
        <h3 class="ac-section-title">
            <i class="ph-fill ph-play-circle me-2"></i>
            Videos
        </h3>
        <span class="ac-video-count">{{ $videoCount }} {{ Str::plural('video', $videoCount) }}</span>
    </div>

    <div class="row g-3 g-md-4 row-cols-2 row-cols-sm-3 row-cols-md-4 row-cols-lg-5 row-cols-xl-6">
        @foreach($channel->videos as $video)
        @php
            $thumb = $video->thumbnail_url ?: $video->poster_url;
            $thumbUrl = $thumb ? setBaseUrlWithFileNameV2($thumb) : asset('img/no-image.jpg');

            // Resolve preview URL (trailer takes priority, fall back to main video — Local only)
            $previewUrl = null;
            if (!empty($video->trailer_url) && $video->trailer_url_type === 'Local') {
                $previewUrl = setBaseUrlWithFileName($video->trailer_url, 'video', 'video');
            } elseif ($video->video_upload_type === 'Local' && !empty($video->video_url_input)) {
                $previewUrl = setBaseUrlWithFileName($video->video_url_input, 'video', 'video');
            }
        @endphp
        <div class="col">
            <div class="ac-video-card" data-preview="{{ $previewUrl ?? '' }}">
                <a href="{{ route('video-details', ['id' => $video->slug, 'autoplay' => 1]) }}" 
                   class="ac-video-link"
                   aria-label="{{ $video->name }}">
                    
                    {{-- Thumbnail Container --}}
                    <div class="ac-video-thumb">
                        <div class="ac-thumb-inner">
                            {{-- Static Thumbnail --}}
                            <img src="{{ $thumbUrl }}"
                                 alt="{{ $video->name }}"
                                 class="ac-thumb-img"
                                 loading="lazy">
                            
                            {{-- Video Preview (hidden until hover) --}}
                            <video class="ac-preview-video"
                                   muted playsinline preload="none"></video>
                            
                            {{-- Gradient Overlay --}}
                            <div class="ac-video-overlay"></div>
                            
                            {{-- Play Button --}}
                            <div class="ac-play-button">
                                <div class="ac-play-button-inner">
                                    <i class="ph-fill ph-play"></i>
                                </div>
                                <div class="ac-play-ripple"></div>
                            </div>
                            
                            {{-- Duration Badge --}}
                            @if($video->duration)
                            <div class="ac-duration-badge">
                                {{ formatDuration($video->duration) }}
                            </div>
                            @endif
                            
                            {{-- Loading Spinner --}}
                            <div class="ac-preview-loader">
                                <div class="spinner-border spinner-border-sm" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    {{-- Video Title --}}
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

@push('after-styles')
<style>
/* ============================
   HERO BANNER SECTION
   ============================ */
.ac-hero-banner {
    position: relative;
    height: 400px;
    overflow: hidden;
}

.ac-hero-bg {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
}

.ac-banner-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    object-position: center;
}

.ac-banner-gradient {
    width: 100%;
    height: 100%;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 50%, #f093fb 100%);
}

.ac-hero-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(180deg, rgba(0,0,0,0.3) 0%, rgba(0,0,0,0.7) 100%);
}

.ac-hero-bottom-gradient {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 60%;
    background: linear-gradient(to top, rgba(13, 17, 23, 1) 0%, rgba(13, 17, 23, 0.8) 30%, transparent 100%);
}

.ac-hero-content {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    z-index: 2;
    padding-bottom: 2rem;
}

/* Avatar */
.ac-avatar-wrapper {
    position: relative;
    width: 120px;
    height: 120px;
    flex-shrink: 0;
}

.ac-avatar,
.ac-avatar-placeholder {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    object-fit: cover;
    border: 5px solid rgba(13, 17, 23, 0.8);
    box-shadow: 0 8px 30px rgba(0, 0, 0, 0.6);
    position: relative;
    z-index: 2;
}

.ac-avatar-placeholder {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 50px;
    color: #fff;
}

.ac-avatar-glow {
    position: absolute;
    top: -8px;
    left: -8px;
    right: -8px;
    bottom: -8px;
    border-radius: 50%;
    background: linear-gradient(135deg, #667eea, #764ba2, #f093fb);
    opacity: 0.6;
    filter: blur(20px);
    animation: glow-pulse 3s ease-in-out infinite;
    z-index: 1;
}

@keyframes glow-pulse {
    0%, 100% { opacity: 0.6; transform: scale(1); }
    50% { opacity: 0.8; transform: scale(1.05); }
}

/* Channel Info */
.ac-channel-info {
    max-width: 900px;
}

.ac-channel-name {
    font-size: 2.5rem;
    font-weight: 700;
    margin-bottom: 0.75rem;
    color: #fff;
    text-shadow: 0 2px 10px rgba(0, 0, 0, 0.5);
}

.ac-channel-meta {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    flex-wrap: wrap;
    margin-bottom: 1rem;
    font-size: 0.95rem;
}

.ac-meta-item {
    color: rgba(255, 255, 255, 0.85);
    display: flex;
    align-items: center;
}

.ac-meta-item strong {
    color: #fff;
}

.ac-meta-item i {
    font-size: 1.1rem;
    opacity: 0.8;
}

.ac-meta-separator {
    color: rgba(255, 255, 255, 0.4);
}

.ac-channel-description {
    color: rgba(255, 255, 255, 0.8);
    margin-top: 1rem;
    margin-bottom: 0;
    line-height: 1.6;
    font-size: 1rem;
}

/* ============================
   VIDEOS SECTION
   ============================ */
.ac-videos-section {
    background: #0d1117;
    min-height: 400px;
}

.ac-section-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 2rem;
    padding-bottom: 1rem;
    border-bottom: 2px solid rgba(255, 255, 255, 0.08);
}

.ac-section-title {
    font-size: 1.75rem;
    font-weight: 700;
    margin: 0;
    color: #fff;
    display: flex;
    align-items: center;
}

.ac-section-title i {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.ac-video-count {
    color: rgba(255, 255, 255, 0.6);
    font-size: 0.95rem;
    font-weight: 500;
}

/* Empty State */
.ac-empty-state {
    text-align: center;
    padding: 5rem 2rem;
}

.ac-empty-state i {
    font-size: 5rem;
    color: rgba(255, 255, 255, 0.2);
    margin-bottom: 1.5rem;
    display: block;
}

.ac-empty-state h4 {
    color: rgba(255, 255, 255, 0.7);
    margin-bottom: 0.5rem;
}

.ac-empty-state p {
    color: rgba(255, 255, 255, 0.5);
}

/* ============================
   VIDEO CARDS
   ============================ */
.ac-video-card {
    position: relative;
    transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.ac-video-card:hover {
    transform: translateY(-8px);
}

.ac-video-link {
    display: block;
    text-decoration: none;
    color: inherit;
}

/* Thumbnail Container */
.ac-video-thumb {
    position: relative;
    overflow: hidden;
    border-radius: 12px;
    background: #000;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.4);
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
}

.ac-video-card:hover .ac-video-thumb {
    box-shadow: 0 12px 40px rgba(102, 126, 234, 0.3), 0 0 30px rgba(118, 75, 162, 0.2);
}

.ac-thumb-inner {
    position: relative;
    width: 100%;
    aspect-ratio: 16 / 9;
    overflow: hidden;
}

.ac-thumb-img,
.ac-preview-video {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: all 0.3s ease;
}

.ac-thumb-img {
    z-index: 1;
}

.ac-preview-video {
    z-index: 2;
    opacity: 0;
    pointer-events: none;
}

.ac-video-card.ac-playing .ac-thumb-img {
    opacity: 0;
}

.ac-video-card.ac-playing .ac-preview-video {
    opacity: 1;
}

.ac-video-card:hover .ac-thumb-img {
    transform: scale(1.05);
}

/* Video Overlay */
.ac-video-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(180deg, rgba(0,0,0,0) 0%, rgba(0,0,0,0.3) 60%, rgba(0,0,0,0.6) 100%);
    opacity: 0;
    transition: opacity 0.3s ease;
    z-index: 3;
    pointer-events: none;
}

.ac-video-card:hover .ac-video-overlay {
    opacity: 1;
}

/* Play Button */
.ac-play-button {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    z-index: 4;
    pointer-events: none;
}

.ac-play-button-inner {
    position: relative;
    width: 56px;
    height: 56px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(255, 255, 255, 0.2);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    border-radius: 50%;
    border: 2px solid rgba(255, 255, 255, 0.5);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    z-index: 2;
}

.ac-play-button-inner i {
    font-size: 24px;
    color: #fff;
    margin-left: 3px;
}

.ac-play-ripple {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 56px;
    height: 56px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.3);
    opacity: 0;
    z-index: 1;
}

.ac-video-card:hover .ac-play-button-inner {
    transform: scale(1.15);
    background: rgba(255, 255, 255, 0.3);
    border-color: rgba(255, 255, 255, 0.8);
    box-shadow: 0 0 30px rgba(255, 255, 255, 0.3);
}

.ac-video-card:hover .ac-play-ripple {
    animation: ripple-effect 1.5s ease-out infinite;
}

@keyframes ripple-effect {
    0% {
        transform: translate(-50%, -50%) scale(1);
        opacity: 0.6;
    }
    100% {
        transform: translate(-50%, -50%) scale(2);
        opacity: 0;
    }
}

.ac-video-card.ac-playing .ac-play-button {
    opacity: 0;
}

/* Duration Badge */
.ac-duration-badge {
    position: absolute;
    bottom: 10px;
    right: 10px;
    background: rgba(0, 0, 0, 0.85);
    backdrop-filter: blur(10px);
    padding: 4px 8px;
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 600;
    color: #fff;
    z-index: 4;
    pointer-events: none;
    border: 1px solid rgba(255, 255, 255, 0.15);
}

/* Loading Spinner */
.ac-preview-loader {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    opacity: 0;
    transition: opacity 0.2s ease;
    z-index: 5;
    pointer-events: none;
}

.ac-preview-loader .spinner-border {
    width: 24px;
    height: 24px;
    border-width: 3px;
    color: #fff;
}

.ac-video-card.ac-loading .ac-preview-loader {
    opacity: 1;
}

/* Video Info */
.ac-video-info {
    padding: 0.875rem 0.5rem 0;
}

.ac-video-title {
    font-size: 0.95rem;
    font-weight: 600;
    color: #fff;
    margin-bottom: 0.375rem;
    line-height: 1.4;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    transition: color 0.2s ease;
}

.ac-video-card:hover .ac-video-title {
    color: #667eea;
}

.ac-video-views {
    font-size: 0.8rem;
    color: rgba(255, 255, 255, 0.5);
    display: flex;
    align-items: center;
}

.ac-video-views i {
    font-size: 0.9rem;
}

/* ============================
   RESPONSIVE
   ============================ */
@media (max-width: 992px) {
    .ac-hero-banner {
        height: 350px;
    }
    
    .ac-channel-name {
        font-size: 2rem;
    }
    
    .ac-avatar-wrapper {
        width: 100px;
        height: 100px;
    }
}

@media (max-width: 768px) {
    .ac-hero-banner {
        height: 300px;
    }
    
    .ac-hero-content {
        padding-bottom: 1.5rem;
    }
    
    .ac-channel-name {
        font-size: 1.75rem;
    }
    
    .ac-avatar-wrapper {
        width: 80px;
        height: 80px;
    }
    
    .ac-avatar-placeholder {
        font-size: 36px;
    }
    
    .ac-section-title {
        font-size: 1.5rem;
    }
    
    .ac-play-button-inner {
        width: 48px;
        height: 48px;
    }
    
    .ac-play-button-inner i {
        font-size: 20px;
    }
}

@media (max-width: 576px) {
    .ac-hero-banner {
        height: 250px;
    }
    
    .ac-channel-name {
        font-size: 1.5rem;
    }
    
    .ac-section-title {
        font-size: 1.25rem;
    }
}
</style>
@endpush

@push('after-scripts')
<script>
(function () {
    'use strict';

    const HOVER_DELAY = 600;        // Reduced delay for faster response
    const PREVIEW_START_SEC = 0;
    const PREVIEW_DURATION = 10;    // Auto-stop after 10 seconds

    document.querySelectorAll('.ac-video-card[data-preview]').forEach(function (card) {
        const previewSrc = card.dataset.preview;
        if (!previewSrc) return;

        const video = card.querySelector('.ac-preview-video');
        const thumb = card.querySelector('.ac-thumb-img');
        const loader = card.querySelector('.ac-preview-loader');
        
        let hoverTimer = null;
        let stopTimer = null;
        let loaded = false;
        let isPlaying = false;

        function startPreview() {
            if (isPlaying) return;
            
            if (!video.src) {
                card.classList.add('ac-loading');
                video.src = previewSrc;
                video.load();
                
                video.addEventListener('canplay', function onCanPlay() {
                    video.removeEventListener('canplay', onCanPlay);
                    card.classList.remove('ac-loading');
                    loaded = true;
                    playPreview();
                }, { once: true });
                
                video.addEventListener('error', function () {
                    card.classList.remove('ac-loading');
                    console.error('Failed to load preview:', previewSrc);
                }, { once: true });
            } else if (loaded) {
                playPreview();
            }
        }

        function playPreview() {
            if (isPlaying) return;
            
            video.currentTime = PREVIEW_START_SEC;
            const playPromise = video.play();
            
            if (playPromise !== undefined) {
                playPromise
                    .then(function() {
                        isPlaying = true;
                        card.classList.add('ac-playing');
                        
                        // Auto-stop after duration
                        stopTimer = setTimeout(function() {
                            stopPreview();
                        }, PREVIEW_DURATION * 1000);
                    })
                    .catch(function(error) {
                        console.error('Preview playback failed:', error);
                        card.classList.remove('ac-loading');
                    });
            }
        }

        function stopPreview() {
            clearTimeout(hoverTimer);
            clearTimeout(stopTimer);
            hoverTimer = null;
            stopTimer = null;
            
            if (!isPlaying) return;
            
            video.pause();
            isPlaying = false;
            card.classList.remove('ac-playing', 'ac-loading');
        }

        card.addEventListener('mouseenter', function () {
            hoverTimer = setTimeout(startPreview, HOVER_DELAY);
        });

        card.addEventListener('mouseleave', function() {
            stopPreview();
        });

        // Cleanup on click (when navigating away)
        card.querySelector('.ac-video-link').addEventListener('click', function() {
            stopPreview();
        });
    });
})();
</script>
@endpush

@endsection
