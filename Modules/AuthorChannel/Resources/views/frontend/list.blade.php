@extends('frontend::layouts.master')

@section('title')
    On Demand
@endsection

@section('content')
<div class="list-page section-spacing-bottom px-0">
    <div class="page-title mb-5">
        <h2 class="m-0 text-center fw-bold" style="font-size: 2.5rem; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;">
            On Demand
        </h2>
        <p class="text-center text-muted mt-2">Discover on demand content</p>
    </div>
    
    <div class="container-fluid">
        <div class="row g-4 row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 row-cols-xl-5">
            @forelse($channels as $channel)
            <div class="col">
                <div class="author-channel-card position-relative h-100">
                    <a href="{{ route('author_channels.show', $channel->username) }}" 
                       class="text-decoration-none d-block h-100">
                        
                        <!-- Banner Image with Gradient Overlay -->
                        <div class="channel-banner position-relative overflow-hidden">
                            <img src="{{ $channel->banner ? setBaseUrlWithFileNameV2($channel->banner) : asset('img/no-image.jpg') }}"
                                 alt="{{ $channel->name }}"
                                 class="w-100 h-100">
                            <div class="banner-overlay"></div>
                            
                            <!-- Video Count Badge -->
                            @if($channel->videos_count ?? 0)
                            <div class="video-count-badge">
                                <i class="fas fa-play-circle me-1"></i>
                                <span>{{ $channel->videos_count }} {{ $channel->videos_count == 1 ? 'video' : 'videos' }}</span>
                            </div>
                            @endif
                        </div>
                        
                        <!-- Channel Info -->
                        <div class="channel-info">
                            <!-- Avatar -->
                            <div class="channel-avatar">
                                <img src="{{ $channel->avatar ? setBaseUrlWithFileNameV2($channel->avatar) : asset('img/user/default-user.jpg') }}"
                                     alt="{{ $channel->name }}">
                                <div class="avatar-ring"></div>
                            </div>
                            
                            <!-- Channel Details -->
                            <div class="channel-details">
                                <h5 class="channel-name">{{ $channel->name }}</h5>
                                @if($channel->description)
                                <p class="channel-description">{{ Str::limit($channel->description, 80) }}</p>
                                @else
                                <p class="channel-description text-muted">No description available</p>
                                @endif
                                
                                <!-- Action Button -->
                                <div class="channel-action">
                                    <span class="view-channel-btn">
                                        View On Demand
                                        <i class="fas fa-arrow-right ms-2"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </a>
                </div>
            </div>
            @empty
            <div class="col-12">
                <div class="empty-state text-center py-5">
                    <i class="fas fa-users fa-4x text-muted mb-3"></i>
                    <h4 class="text-muted">No channels available yet</h4>
                    <p class="text-muted">Check back soon for new content creators!</p>
                </div>
            </div>
            @endforelse
        </div>
        
        @if($channels->hasPages())
        <div class="mt-5 d-flex justify-content-center">
            {{ $channels->links() }}
        </div>
        @endif
    </div>
</div>

<style>
/* Author Channel Card Styles */
.author-channel-card {
    background: #1a1d29;
    border-radius: 20px;
    overflow: hidden;
    transition: all 0.4s cubic-bezier(0.165, 0.84, 0.44, 1);
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
    border: 1px solid rgba(255, 255, 255, 0.05);
}

.author-channel-card:hover {
    transform: translateY(-10px) scale(1.02);
    box-shadow: 0 20px 40px rgba(102, 126, 234, 0.3), 0 0 30px rgba(118, 75, 162, 0.2);
    border-color: rgba(102, 126, 234, 0.3);
}

/* Banner Section */
.channel-banner {
    height: 180px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.channel-banner img {
    object-fit: cover;
    transition: transform 0.6s cubic-bezier(0.165, 0.84, 0.44, 1);
}

.author-channel-card:hover .channel-banner img {
    transform: scale(1.1);
}

.banner-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(180deg, rgba(0,0,0,0.1) 0%, rgba(26,29,41,0.8) 100%);
    opacity: 0.8;
    transition: opacity 0.3s ease;
}

.author-channel-card:hover .banner-overlay {
    opacity: 0.95;
}

/* Video Count Badge */
.video-count-badge {
    position: absolute;
    top: 15px;
    right: 15px;
    background: rgba(0, 0, 0, 0.7);
    backdrop-filter: blur(10px);
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: 600;
    color: #fff;
    display: flex;
    align-items: center;
    border: 1px solid rgba(255, 255, 255, 0.1);
    transition: all 0.3s ease;
}

.author-channel-card:hover .video-count-badge {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-color: rgba(255, 255, 255, 0.3);
    transform: scale(1.05);
}

/* Channel Info Section */
.channel-info {
    padding: 25px 20px 20px;
    position: relative;
}

/* Avatar */
.channel-avatar {
    position: absolute;
    top: -35px;
    left: 50%;
    transform: translateX(-50%);
    width: 70px;
    height: 70px;
    z-index: 10;
}

.channel-avatar img {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    object-fit: cover;
    border: 4px solid #1a1d29;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
    transition: all 0.3s ease;
}

.author-channel-card:hover .channel-avatar img {
    border-color: #667eea;
    box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
}

.avatar-ring {
    position: absolute;
    top: -3px;
    left: -3px;
    right: -3px;
    bottom: -3px;
    border-radius: 50%;
    background: linear-gradient(135deg, #667eea, #764ba2);
    opacity: 0;
    transition: opacity 0.3s ease;
    animation: rotate 3s linear infinite;
    z-index: -1;
}

.author-channel-card:hover .avatar-ring {
    opacity: 1;
}

@keyframes rotate {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

/* Channel Details */
.channel-details {
    text-align: center;
    margin-top: 40px;
}

.channel-name {
    font-size: 1.1rem;
    font-weight: 700;
    color: #fff;
    margin-bottom: 8px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    transition: color 0.3s ease;
}

.author-channel-card:hover .channel-name {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.channel-description {
    font-size: 0.875rem;
    color: #9ca3af;
    margin-bottom: 15px;
    line-height: 1.5;
    min-height: 42px;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

/* Action Button */
.channel-action {
    margin-top: 15px;
    opacity: 0;
    transform: translateY(10px);
    transition: all 0.3s ease;
}

.author-channel-card:hover .channel-action {
    opacity: 1;
    transform: translateY(0);
}

.view-channel-btn {
    display: inline-flex;
    align-items: center;
    padding: 10px 24px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: #fff;
    font-size: 0.9rem;
    font-weight: 600;
    border-radius: 25px;
    transition: all 0.3s ease;
}

.view-channel-btn:hover {
    transform: scale(1.05);
    box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
}

/* Empty State */
.empty-state {
    background: #1a1d29;
    border-radius: 20px;
    padding: 60px 20px;
    border: 2px dashed rgba(255, 255, 255, 0.1);
}

/* Responsive Adjustments */
@media (max-width: 768px) {
    .page-title h2 {
        font-size: 2rem !important;
    }
    
    .channel-banner {
        height: 150px;
    }
    
    .channel-avatar {
        width: 60px;
        height: 60px;
        top: -30px;
    }
    
    .channel-details {
        margin-top: 35px;
    }
}

@media (max-width: 576px) {
    .row {
        row-gap: 2rem !important;
    }
    
    .channel-name {
        font-size: 1rem;
    }
    
    .channel-description {
        font-size: 0.8rem;
    }
}

/* Light Mode Support */
@media (prefers-color-scheme: light) {
    .author-channel-card {
        background: #fff;
        border-color: rgba(0, 0, 0, 0.1);
    }
    
    .channel-avatar img {
        border-color: #fff;
    }
    
    .channel-name {
        color: #1a1d29;
    }
    
    .channel-description {
        color: #6b7280;
    }
    
    .empty-state {
        background: #f9fafb;
        border-color: rgba(0, 0, 0, 0.1);
    }
}
</style>
@endsection
