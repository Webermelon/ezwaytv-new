@extends('backend.layouts.app')

@section('title')
    Edit On Demand Channel
@endsection

@section('content')
<x-back-button-component route="backend.author_channels.index" />

@php
    $avatarValue = old('avatar', $channel->avatar);
    $bannerValue = old('banner', $channel->banner);
    $selectedAccess = old('access', $channel->access ?? 'free');
    $channelPoster = $channel->banner ?: $channel->avatar;
@endphp

<style>
    .ondemand-card { border: 1px solid rgba(255,255,255,.08); border-radius: .75rem; overflow: hidden; }
    .ondemand-card .card-header { padding: 1.1rem 1.25rem; }
    .ondemand-card .card-body { padding: 1.25rem; }
    .ondemand-sidebar { position: sticky; top: 1rem; display: grid; gap: .6rem; padding: .75rem; border: 1px solid rgba(255,255,255,.08); border-radius: .75rem; background: rgba(255,255,255,.025); }
    .ondemand-sidebar .nav-link { display: flex; align-items: center; justify-content: space-between; gap: .75rem; border-radius: .55rem; color: rgba(255,255,255,.72); text-align: left; }
    .ondemand-sidebar .nav-link.active { color: #050b0f; background: var(--bs-primary); }
    .ondemand-preview { position: sticky; top: 1rem; }
    .ondemand-cover { position: relative; min-height: 190px; border-radius: .7rem; overflow: hidden; background: linear-gradient(135deg, rgba(255,193,7,.18), rgba(13,110,253,.12)), #071016; border: 1px solid rgba(255,255,255,.08); }
    .ondemand-cover img { width: 100%; height: 100%; min-height: 190px; object-fit: cover; display: block; }
    .ondemand-cover-empty { min-height: 190px; display: grid; place-items: center; color: rgba(255,255,255,.45); font-weight: 700; }
    .ondemand-avatar { width: 92px; height: 92px; border-radius: 50%; border: 4px solid var(--bs-body-bg); background: #101820; object-fit: cover; box-shadow: 0 10px 30px rgba(0,0,0,.3); }
    .ondemand-avatar-wrap { margin-top: -46px; padding-inline: 1rem; position: relative; z-index: 2; }
    .ondemand-media-actions { display: flex; gap: .5rem; flex-wrap: wrap; margin-top: .65rem; }
    .ondemand-url-pill { border-radius: .55rem; background: rgba(255,255,255,.045); border: 1px solid rgba(255,255,255,.08); padding: .7rem .85rem; min-height: 44px; display: flex; align-items: center; }
    .ondemand-choice { border: 1px solid rgba(255,255,255,.10); border-radius: .65rem; padding: .8rem .95rem; min-width: 120px; cursor: pointer; transition: border-color .15s ease, background .15s ease; }
    .ondemand-choice:has(input:checked) { border-color: var(--bs-primary); background: rgba(var(--bs-primary-rgb), .12); }
    .ondemand-actions { display: flex; gap: .75rem; flex-wrap: wrap; justify-content: flex-end; padding-top: 1rem; border-top: 1px solid rgba(255,255,255,.08); }
    .ondemand-video-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 1rem; }
    .ondemand-video-card { border: 1px solid rgba(255,255,255,.10); border-radius: .7rem; overflow: hidden; background: rgba(255,255,255,.025); }
    .ondemand-video-thumb { aspect-ratio: 16 / 9; width: 100%; object-fit: cover; background: #050b0f; display: block; }
    .ondemand-video-body { padding: .85rem; display: grid; gap: .7rem; }
    .ondemand-video-title { min-height: 2.5rem; line-height: 1.25; }
    .playlist-builder { display: grid; gap: 1.25rem; }
    .playlist-create-shell { display: grid; justify-content: end; gap: .75rem; }
    .playlist-create-toggle { justify-self: end; min-height: 42px; display: inline-flex; align-items: center; gap: .45rem; }
    .playlist-create-panel { display: grid; grid-template-rows: 0fr; opacity: 0; transition: grid-template-rows .22s ease, opacity .18s ease; }
    .playlist-create-panel.is-open { grid-template-rows: 1fr; opacity: 1; }
    .playlist-create-panel-inner { min-height: 0; overflow: hidden; }
    .playlist-create { display: grid; grid-template-columns: minmax(0, 1fr); gap: .9rem; align-items: end; width: min(100%, 760px); min-width: min(760px, calc(100vw - 3rem)); padding: 1rem; border: 1px solid rgba(255,255,255,.08); border-radius: .75rem; background: rgba(255,255,255,.025); }
    .playlist-create-actions { display: flex; justify-content: flex-end; gap: .65rem; padding-top: .25rem; }
    .playlist-create-actions .btn { min-width: 140px; min-height: 42px; display: inline-flex; align-items: center; justify-content: center; gap: .35rem; }
    .playlist-workspace { display: grid; grid-template-columns: 320px minmax(0, 1fr); gap: 1rem; align-items: start; }
    .playlist-list-panel, .playlist-editor-panel { border: 1px solid rgba(255,255,255,.10); border-radius: .75rem; background: rgba(255,255,255,.025); overflow: hidden; }
    .playlist-list-panel { position: sticky; top: 1rem; }
    .playlist-list-header { display: grid; gap: .75rem; padding: 1rem; border-bottom: 1px solid rgba(255,255,255,.08); }
    .playlist-list { display: grid; gap: .45rem; padding: .75rem; max-height: 72vh; overflow: auto; }
    .playlist-list-item { display: grid; grid-template-columns: 74px minmax(0, 1fr) auto; gap: .75rem; align-items: center; width: 100%; border: 1px solid rgba(255,255,255,.08); border-radius: .65rem; background: rgba(0,0,0,.18); color: inherit; padding: .55rem; text-align: left; }
    .playlist-list-item.active { border-color: var(--bs-primary); background: rgba(var(--bs-primary-rgb), .14); }
    .playlist-list-item.is-hidden { display: none; }
    .playlist-list-item img { width: 74px; aspect-ratio: 16 / 9; border-radius: .45rem; object-fit: cover; background: #050b0f; }
    .playlist-panel { display: none; }
    .playlist-panel.active { display: block; }
    .playlist-panel-header { display: grid; grid-template-columns: 160px minmax(0, 1fr); gap: 1rem; padding: 1rem; border-bottom: 1px solid rgba(255,255,255,.08); overflow: hidden; }
    .playlist-thumb { width: 160px; aspect-ratio: 16 / 9; object-fit: cover; border-radius: .55rem; background: #050b0f; border: 1px solid rgba(255,255,255,.08); }
    .playlist-editor-main { min-width: 0; display: grid; gap: .9rem; }
    .playlist-editor-title { display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; }
    .playlist-form-grid { position: relative; display: grid; grid-template-columns: minmax(0, 1fr); gap: .85rem; align-items: end; max-width: 100%; }
    .playlist-form-field { min-width: 0; }
    .playlist-inline-fields { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: .85rem; align-items: end; }
    .playlist-status-field { position: absolute; top: -3.15rem; right: 0; display: grid; gap: .35rem; align-content: end; justify-content: end; min-width: 0; }
    .playlist-status-field .form-label { display: none; }
    .playlist-actions-row { grid-column: 1 / -1; display: flex; justify-content: flex-end; gap: .65rem; padding-top: .75rem; border-top: 1px solid rgba(255,255,255,.08); }
    .playlist-video-list { display: grid; gap: .55rem; min-height: 72px; padding: 1rem; }
    .playlist-video-row { display: grid; grid-template-columns: 26px 76px minmax(0, 1fr) auto; gap: .65rem; align-items: center; border: 1px solid rgba(255,255,255,.08); border-radius: .6rem; background: rgba(0,0,0,.22); padding: .55rem; }
    .playlist-video-row.is-dragging { opacity: .55; border-color: var(--bs-primary); }
    .playlist-drag { cursor: grab; color: var(--bs-primary); display: grid; place-items: center; }
    .playlist-video-row img { width: 76px; aspect-ratio: 16 / 9; border-radius: .4rem; object-fit: cover; background: #050b0f; }
    .playlist-add-bar { display: grid; grid-template-columns: minmax(0, 1fr) 132px; gap: .75rem; align-items: center; padding: 1rem; border-top: 1px solid rgba(255,255,255,.08); background: rgba(0,0,0,.12); }
    .playlist-add-button { min-width: 132px; height: 44px; display: inline-flex; align-items: center; justify-content: center; gap: .35rem; white-space: nowrap; }
    .playlist-save-button { height: 36px; display: inline-flex; align-items: center; gap: .35rem; }
    .playlist-add-bar .select2-container .select2-selection--single { min-height: 44px; display: flex; align-items: center; }
    .playlist-add-bar .select2-container--bootstrap-5 .select2-selection--single .select2-selection__rendered { line-height: 1.2; }
    .playlist-status-toggle { display: inline-flex; align-items: center; gap: .65rem; min-height: 32px; cursor: pointer; }
    .playlist-status-toggle input { width: 0; height: 0; opacity: 0; position: absolute; }
    .playlist-status-toggle span:first-of-type { position: relative; width: 44px; height: 24px; border-radius: 999px; background: rgba(255,255,255,.16); border: 1px solid rgba(255,255,255,.14); transition: background .15s ease, border-color .15s ease; }
    .playlist-status-toggle span:first-of-type::after { content: ''; position: absolute; top: 3px; left: 3px; width: 16px; height: 16px; border-radius: 50%; background: #fff; transition: transform .15s ease; }
    .playlist-status-toggle input:checked + span:first-of-type { background: rgba(25, 135, 84, .85); border-color: rgba(25, 135, 84, .95); }
    .playlist-status-toggle input:checked + span:first-of-type::after { transform: translateX(20px); }
    .playlist-toolbar { display: grid; grid-template-columns: 1fr; gap: .75rem; }
    .ondemand-empty { border: 1px dashed rgba(255,255,255,.18); border-radius: .7rem; padding: 2rem; text-align: center; color: rgba(255,255,255,.55); }
    @media (max-width: 1199.98px) {
        .playlist-workspace { grid-template-columns: 1fr; }
        .playlist-list-panel { position: static; }
    }
    @media (max-width: 991.98px) {
        .ondemand-shell { grid-template-columns: 1fr; }
        .ondemand-sidebar, .ondemand-preview { position: static; }
        .ondemand-sidebar { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        .playlist-create-shell { justify-content: stretch; }
        .playlist-create { min-width: 0; width: 100%; }
    }
    @media (max-width: 575.98px) {
        .ondemand-sidebar, .playlist-toolbar, .playlist-create, .playlist-form-grid, .playlist-add-bar, .playlist-panel-header, .playlist-inline-fields { grid-template-columns: 1fr; }
        .playlist-thumb { width: 100%; }
        .playlist-editor-title { display: grid; }
        .playlist-status-field { position: static; justify-content: start; }
        .playlist-status-field .form-label { display: block; }
        .playlist-create-actions { display: grid; }
        .playlist-create-actions .btn { width: 100%; }
        .playlist-actions-row { justify-content: stretch; }
        .playlist-actions-row .btn { width: 100%; justify-content: center; }
        .playlist-list-item { grid-template-columns: 64px minmax(0, 1fr); }
        .playlist-list-item .badge { justify-self: start; }
    }
</style>

@if ($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
@endif

<div class="ondemand-shell">
    <div class="ondemand-sidebar nav nav-pills" id="ondemandEditTabs" role="tablist" aria-orientation="vertical">
        <button class="nav-link active" data-bs-toggle="pill" data-bs-target="#tab-channel-details" type="button">
            <span><i class="ph ph-television me-2"></i>Details</span>
            <i class="ph ph-caret-right"></i>
        </button>
        <button class="nav-link" data-bs-toggle="pill" data-bs-target="#tab-playlists" type="button">
            <span><i class="ph ph-list-bullets me-2"></i>Playlists</span>
            <span class="badge bg-dark" data-playlist-count data-playlist-count-format="number">{{ $channel->playlists->count() }}</span>
        </button>
        <button class="nav-link" data-bs-toggle="pill" data-bs-target="#tab-assign-videos" type="button">
            <span><i class="ph ph-video me-2"></i>Assign Videos</span>
            <span class="badge bg-dark">{{ $channel->videos->count() }}</span>
        </button>
    </div>

    <div class="tab-content">
        <div class="tab-pane fade show active" id="tab-channel-details">
            <form action="{{ route('backend.author_channels.update', $channel->id) }}" method="POST">
                @csrf
                <div class="card ondemand-card">
                    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-3">
                        <div>
                            <h4 class="card-title mb-1">Edit On Demand Channel</h4>
                            <p class="mb-0 text-muted small">Update the public profile, artwork, and access rules.</p>
                        </div>
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <span class="badge {{ $channel->is_active ? 'bg-success-subtle text-success' : 'bg-secondary' }}">{{ $channel->is_active ? 'Active' : 'Inactive' }}</span>
                            <span class="badge {{ $selectedAccess === 'paid' ? 'bg-warning-subtle text-warning' : 'bg-primary-subtle text-primary' }}">{{ ucfirst($selectedAccess) }}</span>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row g-4">
                            <div class="col-lg-8">
                                <div class="row g-3">
                                    <div class="col-md-7">
                                        <label class="form-label">Channel Name <span class="text-danger">*</span></label>
                                        <input type="text" name="name" id="channelName" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $channel->name) }}" required>
                                        @error('name')<span class="text-danger">{{ $message }}</span>@enderror
                                    </div>

                                    <div class="col-md-5">
                                        <label class="form-label">Linked User <span class="text-muted">(optional)</span></label>
                                        <select name="user_id" class="form-control select2">
                                            <option value="">-- No User --</option>
                                            @foreach(\App\Models\User::orderBy('first_name')->get() as $u)
                                                <option value="{{ $u->id }}" {{ old('user_id', $channel->user_id) == $u->id ? 'selected' : '' }}>{{ $u->first_name }} {{ $u->last_name }} ({{ $u->email }})</option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <div class="col-12">
                                        <label class="form-label">Username <span class="text-muted">(URL slug)</span></label>
                                        <div class="input-group">
                                            <span class="input-group-text text-muted">/on-demand/</span>
                                            <input type="text" name="username" id="channelUsername" class="form-control @error('username') is-invalid @enderror" value="{{ old('username', $channel->username) }}" placeholder="ezway-family" pattern="[a-z0-9\-]+">
                                        </div>
                                        @error('username')<span class="text-danger">{{ $message }}</span>@enderror
                                    </div>

                                    <div class="col-12">
                                        <label class="form-label">Description</label>
                                        <textarea name="description" id="channelDescription" class="form-control" rows="5">{{ old('description', $channel->description) }}</textarea>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label">Active</label>
                                        <select name="is_active" class="form-control">
                                            <option value="1" {{ old('is_active', $channel->is_active) == 1 ? 'selected' : '' }}>Active</option>
                                            <option value="0" {{ old('is_active', $channel->is_active) == 0 ? 'selected' : '' }}>Inactive</option>
                                        </select>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label d-block">Access <span class="text-danger">*</span></label>
                                        <div class="d-flex gap-2 flex-wrap">
                                            <label class="ondemand-choice mb-0">
                                                <input class="form-check-input me-2" type="radio" name="access" value="free" onchange="window.syncAuthorChannelPlanSelection && window.syncAuthorChannelPlanSelection()" {{ $selectedAccess === 'free' ? 'checked' : '' }}>
                                                <span class="fw-semibold">Free</span>
                                            </label>
                                            <label class="ondemand-choice mb-0">
                                                <input class="form-check-input me-2" type="radio" name="access" value="paid" onchange="window.syncAuthorChannelPlanSelection && window.syncAuthorChannelPlanSelection()" {{ $selectedAccess === 'paid' ? 'checked' : '' }}>
                                                <span class="fw-semibold">Paid</span>
                                            </label>
                                        </div>
                                        @error('access')<span class="text-danger">{{ $message }}</span>@enderror
                                    </div>

                                    <div class="col-12 {{ $selectedAccess === 'paid' ? '' : 'd-none' }}" id="planSelection" data-plan-selection>
                                        <label class="form-label">Subscription Plan <span class="text-danger">*</span></label>
                                        <select name="plan_id" id="plan_id" class="form-control select2" data-plan-select>
                                            <option value="">-- Select Plan --</option>
                                            @foreach($plans as $plan)
                                                <option value="{{ $plan->id }}" {{ old('plan_id', $channel->plan_id) == $plan->id ? 'selected' : '' }}>{{ $plan->name }}</option>
                                            @endforeach
                                        </select>
                                        <small class="text-muted d-block mt-1">Only subscribers to this plan can access a paid channel.</small>
                                        @error('plan_id')<span class="text-danger">{{ $message }}</span>@enderror
                                    </div>
                                </div>
                            </div>

                            <div class="col-lg-4">
                                <div class="ondemand-preview">
                                    <label class="form-label">Profile Preview</label>
                                    <div class="ondemand-cover" id="bannerImageContainer">
                                        @if($bannerValue)
                                            <img src="{{ setBaseUrlWithFileNameV2($bannerValue) }}" alt="">
                                        @else
                                            <div class="ondemand-cover-empty"><i class="ph ph-image me-1"></i> Cover image</div>
                                        @endif
                                    </div>
                                    <div class="ondemand-avatar-wrap">
                                        <img src="{{ $avatarValue ? setBaseUrlWithFileNameV2($avatarValue) : asset('images/default-avatar.png') }}" class="ondemand-avatar" id="avatarPreview" alt="" onerror="this.onerror=null;this.src='{{ asset('images/default-avatar.png') }}';">
                                    </div>
                                    <div class="px-3 pt-2">
                                        <h5 class="mb-1" id="previewName">{{ old('name', $channel->name) ?: 'Channel name' }}</h5>
                                        <div class="ondemand-url-pill text-muted small">
                                            <i class="ph ph-link me-2"></i><span>/on-demand/</span><span id="previewUsername">{{ old('username', $channel->username) ?: 'username' }}</span>
                                        </div>
                                        <div class="ondemand-media-actions">
                                            <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#exampleModal" data-image-container="avatarPickerContainer" data-hidden-input="file_url_avatar">
                                                <i class="ph ph-user-circle"></i> Change Avatar
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#exampleModal" data-image-container="bannerImageContainer" data-hidden-input="file_url_banner">
                                                <i class="ph ph-image"></i> Change Cover
                                            </button>
                                        </div>
                                    </div>
                                    <div class="d-none" id="avatarPickerContainer"></div>
                                    <input type="hidden" name="avatar" id="file_url_avatar" value="{{ $avatarValue }}">
                                    <input type="hidden" name="banner" id="file_url_banner" value="{{ $bannerValue }}">
                                </div>
                            </div>
                        </div>

                        <div class="ondemand-actions mt-4">
                            <a href="{{ route('backend.author_channels.index') }}" class="btn btn-secondary">Cancel</a>
                            <button type="submit" class="btn btn-primary">
                                <i class="ph ph-floppy-disk"></i> Update Channel
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <div class="tab-pane fade" id="tab-playlists">
            <div class="card ondemand-card">
                <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-3">
                    <div>
                        <h4 class="card-title mb-1">Playlists</h4>
                        <p class="mb-0 text-muted small">Create playlists, assign videos, and drag videos into the order viewers should see.</p>
                    </div>
                    <span class="badge bg-primary" data-playlist-count data-playlist-count-format="label">{{ $channel->playlists->count() }} playlist(s)</span>
                </div>
                <div class="card-body playlist-builder">
                    <div class="playlist-create-shell">
                        <button type="button" class="btn btn-primary playlist-create-toggle" id="playlistCreateToggle" aria-expanded="false" aria-controls="playlistCreatePanel">
                            <i class="ph ph-plus-circle"></i> Create New Playlist
                        </button>
                        <div class="playlist-create-panel" id="playlistCreatePanel" aria-hidden="true">
                            <div class="playlist-create-panel-inner">
                                <form action="{{ route('backend.author_channels.playlists.store', $channel->id) }}" method="POST" class="playlist-create">
                                    @csrf
                                    <div>
                                        <label class="form-label fw-semibold">Playlist Name</label>
                                        <input type="text" name="name" class="form-control" placeholder="Featured Interviews" required>
                                    </div>
                                    <div>
                                        <label class="form-label fw-semibold">Description</label>
                                        <input type="text" name="description" class="form-control" placeholder="Optional short note">
                                    </div>
                                    <div>
                                        <label class="form-label fw-semibold">Thumbnail</label>
                                        <div class="input-group">
                                            <input type="text" name="thumbnail" id="playlistCreateThumbnail" class="form-control" placeholder="Image URL">
                                            <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#exampleModal" data-image-container="playlistCreateThumbPreview" data-hidden-input="playlistCreateThumbnail">
                                                <i class="ph ph-image"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <div>
                                        <label class="form-label fw-semibold">Status</label>
                                        <input type="hidden" name="is_active" value="0">
                                        <label class="ondemand-choice d-flex align-items-center mb-0">
                                            <input class="form-check-input me-2" type="checkbox" name="is_active" value="1" checked>
                                            <span class="fw-semibold">Active</span>
                                        </label>
                                    </div>
                                    <div class="playlist-create-actions">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="ph ph-list-plus"></i> Create Playlist
                                        </button>
                                        <button type="button" class="btn btn-secondary" id="playlistCreateCancel">
                                            Cancel
                                        </button>
                                    </div>
                                    <div class="d-none" id="playlistCreateThumbPreview"></div>
                                </form>
                            </div>
                        </div>
                    </div>

                    @if($channel->playlists->isNotEmpty())
                        <div class="playlist-workspace">
                            <div class="playlist-list-panel">
                                <div class="playlist-list-header">
                                    <div>
                                        <h6 class="mb-1">Select Playlist</h6>
                                        <p class="mb-0 text-muted small">Choose one playlist to edit.</p>
                                    </div>
                                    <div class="playlist-toolbar">
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="ph ph-magnifying-glass"></i></span>
                                            <input type="search" class="form-control" id="playlistSearch" placeholder="Search playlists">
                                        </div>
                                        <select class="form-control" id="playlistStatusFilter">
                                            <option value="all">All statuses</option>
                                            <option value="active">Active only</option>
                                            <option value="inactive">Inactive only</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="playlist-list" id="playlistList">
                                    @foreach($channel->playlists as $playlist)
                                        @php
                                            $firstPlaylistVideo = $playlist->videos->first();
                                            $playlistThumbValue = $playlist->thumbnail ?: ($firstPlaylistVideo?->thumbnail_url ?: $firstPlaylistVideo?->poster_url);
                                            $playlistThumb = $playlistThumbValue
                                                ? setBaseUrlWithFileNameV2($playlistThumbValue)
                                                : asset('default-image/Default-Image.jpg');
                                        @endphp
                                        <button type="button" class="playlist-list-item {{ $loop->first ? 'active' : '' }}" data-playlist-trigger="{{ $playlist->id }}" data-status="{{ $playlist->is_active ? 'active' : 'inactive' }}" data-search-text="{{ strtolower($playlist->name . ' ' . $playlist->description . ' ' . $playlist->videos->pluck('name')->join(' ')) }}">
                                            <img src="{{ $playlistThumb }}" alt="" onerror="this.onerror=null;this.src='{{ asset('default-image/Default-Image.jpg') }}';">
                                            <span class="min-w-0">
                                                <span class="d-block fw-semibold text-truncate">{{ $playlist->name }}</span>
                                                <span class="d-block text-muted small">{{ $playlist->is_active ? 'Active' : 'Inactive' }}</span>
                                            </span>
                                            <span class="badge bg-primary-subtle text-primary">{{ $playlist->videos->count() }}</span>
                                        </button>
                                    @endforeach
                                </div>
                            </div>

                            <div class="playlist-editor-panel">
                                @foreach($channel->playlists as $playlist)
                                    @php
                                        $playlistVideoIds = $playlist->videos->pluck('id')->map(fn ($videoId) => (int) $videoId)->all();
                                        $playlistAvailableVideos = $channel->videos->reject(fn ($video) => in_array((int) $video->id, $playlistVideoIds, true));
                                        $firstPlaylistVideo = $playlist->videos->first();
                                        $playlistThumbValue = $playlist->thumbnail ?: ($firstPlaylistVideo?->thumbnail_url ?: $firstPlaylistVideo?->poster_url);
                                        $playlistThumb = $playlistThumbValue
                                            ? setBaseUrlWithFileNameV2($playlistThumbValue)
                                            : asset('default-image/Default-Image.jpg');
                                    @endphp
                                    <div class="playlist-panel {{ $loop->first ? 'active' : '' }}" data-playlist-panel="{{ $playlist->id }}">
                                        <div class="playlist-panel-header">
                                            <img src="{{ $playlistThumb }}" class="playlist-thumb" id="playlistThumbPreview{{ $playlist->id }}" alt="" onerror="this.onerror=null;this.src='{{ asset('default-image/Default-Image.jpg') }}';">
                                            <div class="playlist-editor-main">
                                                <div class="playlist-editor-title">
                                                    <div>
                                                        <h5 class="mb-1">{{ $playlist->name }}</h5>
                                                        <div class="text-muted small">{{ $playlist->videos->count() }} video(s)</div>
                                                    </div>
                                                </div>
                                                <form action="{{ route('backend.author_channels.playlists.update', [$channel->id, $playlist->id]) }}" method="POST" class="playlist-form-grid">
                                                    @csrf
                                                    <div class="playlist-form-field playlist-name-field">
                                                        <label class="form-label small fw-semibold">Name</label>
                                                        <input type="text" name="name" class="form-control form-control-sm" value="{{ $playlist->name }}" required>
                                                    </div>
                                                    <div class="playlist-inline-fields">
                                                        <div class="playlist-form-field">
                                                            <label class="form-label small fw-semibold">Thumbnail</label>
                                                            <div class="input-group input-group-sm">
                                                                <input type="text" name="thumbnail" id="playlistThumbnail{{ $playlist->id }}" class="form-control" value="{{ $playlist->thumbnail }}">
                                                                <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#exampleModal" data-image-container="playlistThumbPreview{{ $playlist->id }}" data-hidden-input="playlistThumbnail{{ $playlist->id }}">
                                                                    <i class="ph ph-image"></i>
                                                                </button>
                                                            </div>
                                                        </div>
                                                        <div class="playlist-status-field">
                                                            <label class="form-label small fw-semibold">Status</label>
                                                            <input type="hidden" name="is_active" value="0">
                                                            <label class="playlist-status-toggle mb-0">
                                                                <input type="checkbox" name="is_active" value="1" {{ $playlist->is_active ? 'checked' : '' }}>
                                                                <span></span>
                                                                <span class="fw-semibold">{{ $playlist->is_active ? 'Active' : 'Inactive' }}</span>
                                                            </label>
                                                        </div>
                                                    </div>
                                                    <div class="playlist-form-field playlist-description-field">
                                                        <label class="form-label small fw-semibold">Description</label>
                                                        <input type="text" name="description" class="form-control form-control-sm" value="{{ $playlist->description }}" placeholder="Optional note">
                                                    </div>
                                                    <div class="playlist-actions-row">
                                                        <button type="submit" class="btn btn-sm btn-primary playlist-save-button">
                                                            <i class="ph ph-floppy-disk"></i> Save Playlist
                                                        </button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>

                                        <form action="{{ route('backend.author_channels.playlists.videos.add', [$channel->id, $playlist->id]) }}" method="POST" class="playlist-add-bar" data-playlist-add-form data-playlist-id="{{ $playlist->id }}">
                                            @csrf
                                            <select name="video_id" class="form-control playlist-video-select" required @disabled($playlistAvailableVideos->isEmpty())>
                                                <option value="">Search and add a video</option>
                                                @foreach($playlistAvailableVideos as $video)
                                                    <option value="{{ $video->id }}">{{ $video->name }}</option>
                                                @endforeach
                                            </select>
                                            <button type="submit" class="btn btn-success playlist-add-button" @disabled($playlistAvailableVideos->isEmpty())>
                                                <i class="ph ph-plus-circle"></i> Add Video
                                            </button>
                                        </form>

                                        <div class="playlist-video-list" data-sortable-playlist data-reorder-url="{{ route('backend.author_channels.playlists.videos.reorder', [$channel->id, $playlist->id]) }}">
                                            @forelse($playlist->videos as $video)
                                                @php
                                                    $playlistVideoThumb = ($video->thumbnail_url ?: $video->poster_url)
                                                        ? setBaseUrlWithFileNameV2($video->thumbnail_url ?: $video->poster_url)
                                                        : asset('default-image/Default-Image.jpg');
                                                @endphp
                                                <div class="playlist-video-row" draggable="true" data-video-id="{{ $video->id }}">
                                                    <span class="playlist-drag"><i class="ph ph-dots-six-vertical"></i></span>
                                                    <img src="{{ $playlistVideoThumb }}" alt="" onerror="this.onerror=null;this.src='{{ asset('default-image/Default-Image.jpg') }}';">
                                                    <div class="min-w-0">
                                                        <div class="fw-semibold text-truncate">{{ $video->name }}</div>
                                                        <div class="text-muted small">Drag to reorder</div>
                                                    </div>
                                                    <form action="{{ route('backend.author_channels.playlists.videos.remove', [$channel->id, $playlist->id, $video->id]) }}" method="POST" onsubmit="return confirm('Remove this video from the playlist?')">
                                                        @csrf
                                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                                            <i class="ph ph-x"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            @empty
                                                <div class="ondemand-empty py-4">
                                                    No videos in this playlist yet.
                                                </div>
                                            @endforelse
                                        </div>

                                        <div class="p-3 pt-0 d-flex justify-content-end">
                                            <form action="{{ route('backend.author_channels.playlists.destroy', [$channel->id, $playlist->id]) }}" method="POST" data-playlist-delete-form data-playlist-id="{{ $playlist->id }}" data-playlist-name="{{ e($playlist->name) }}">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                                    <i class="ph ph-trash"></i> Delete Playlist
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @else
                        <div class="ondemand-empty">
                            <i class="ph ph-list-bullets d-block mb-2" style="font-size: 2rem;"></i>
                            No playlists yet. Create one from assigned videos.
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="tab-pane fade" id="tab-assign-videos">
            <div class="card ondemand-card">
                <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-3">
                    <div>
                        <h4 class="card-title mb-1">Assign Videos</h4>
                        <p class="mb-0 text-muted small">Attach videos to this On-Demand channel before placing them in playlists.</p>
                    </div>
                    <span class="badge bg-primary" id="assignedVideoCount">{{ $channel->videos->count() }} video(s)</span>
                </div>
                <div class="card-body">
                    <div class="row g-3 align-items-end">
                        <div class="col-lg-8">
                            <form action="{{ route('backend.author_channels.videos.assign', $channel->id) }}" method="POST" class="row g-2 align-items-end" data-assign-video-form>
                                @csrf
                                <div class="col-md-8">
                                    <label class="form-label fw-semibold">Assign Existing Video</label>
                                    <select name="video_id" id="assignVideoSelect" class="form-control" style="width:100%" required>
                                        <option value="">-- Select a video --</option>
                                        @foreach($availableVideos as $av)
                                            <option value="{{ $av->id }}">{{ $av->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <button type="submit" class="btn btn-success w-100">
                                        <i class="ph ph-plus-circle"></i> Assign
                                    </button>
                                </div>
                            </form>
                        </div>
                        <div class="col-lg-4">
                            <a href="{{ route('backend.videos.create') }}?author_channel_id={{ $channel->id }}&return_channel={{ $channel->id }}" class="btn btn-outline-primary w-100">
                                <i class="ph ph-upload-simple"></i> Upload New Video
                            </a>
                        </div>
                    </div>

                    <div class="input-group my-4">
                        <span class="input-group-text"><i class="ph ph-magnifying-glass"></i></span>
                        <input type="search" class="form-control" id="assignedVideoSearch" placeholder="Search assigned videos">
                    </div>

                    <div class="ondemand-video-grid" id="assignedVideoGrid">
                        @forelse($channel->videos as $v)
                            @php
                                $thumb = ($v->thumbnail_url ?: $v->poster_url)
                                    ? setBaseUrlWithFileNameV2($v->thumbnail_url ?: $v->poster_url)
                                    : ($channelPoster ? setBaseUrlWithFileNameV2($channelPoster) : asset('default-image/Default-Image.jpg'));
                            @endphp
                            <div class="ondemand-video-card" data-assigned-video-card data-video-id="{{ $v->id }}" data-search-text="{{ strtolower($v->name) }}">
                                <img src="{{ $thumb }}" class="ondemand-video-thumb" alt="" onerror="this.onerror=null;this.src='{{ asset('default-image/Default-Image.jpg') }}';">
                                <div class="ondemand-video-body">
                                    <div>
                                        <div class="ondemand-video-title fw-semibold">{{ Str::limit($v->name, 70) }}</div>
                                        @if($v->duration)
                                            <span class="badge bg-secondary-subtle text-secondary mt-2">{{ $v->duration }}</span>
                                        @endif
                                    </div>
                                    <div class="d-flex gap-2 flex-wrap">
                                        <a href="{{ route('backend.videos.edit', $v->id) }}" class="btn btn-sm btn-outline-primary" target="_blank">
                                            <i class="ph ph-pencil-simple"></i> Edit
                                        </a>
                                        <form action="{{ route('backend.author_channels.videos.unassign', [$channel->id, $v->id]) }}" method="POST" onsubmit="return confirm('Remove this video from the channel?')">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                                <i class="ph ph-x"></i> Remove
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="ondemand-empty">
                                <i class="ph ph-video-camera d-block mb-2" style="font-size: 2rem;"></i>
                                No videos assigned yet. Assign an existing video or upload a new one.
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@include('components.media-modal', ['page_type' => 'author-channel'])

<script>
document.getElementById('exampleModal')?.addEventListener('show.bs.modal', function () {
    setTimeout(function () {
        if (typeof FileManager !== 'undefined' && FileManager.navigation && !FileManager.state.currentFolder) {
            FileManager.navigation.openFolder('author-channel');
        }
    }, 300);
});

if (window.jQuery) {
    jQuery(function () {
        var $sel = jQuery('#assignVideoSelect');
        if ($sel.length && typeof jQuery.fn.select2 !== 'undefined') {
            $sel.select2({
                theme: 'bootstrap-5',
                placeholder: '-- Select a video --',
                allowClear: true,
                width: '100%'
            });
        }
        var $playlistVideos = jQuery('.playlist-video-select');
        if ($playlistVideos.length && typeof jQuery.fn.select2 !== 'undefined') {
            $playlistVideos.select2({
                theme: 'bootstrap-5',
                placeholder: 'Search and add a video',
                allowClear: true,
                width: '100%'
            });
        }
    });
}

const nameInput = document.getElementById('channelName');
const usernameInput = document.getElementById('channelUsername');
const previewName = document.getElementById('previewName');
const previewUsername = document.getElementById('previewUsername');
const avatarInput = document.getElementById('file_url_avatar');
const bannerInput = document.getElementById('file_url_banner');
const avatarPreview = document.getElementById('avatarPreview');
const bannerContainer = document.getElementById('bannerImageContainer');

function slugifyChannel(value) {
    return value.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
}

function syncPreview() {
    if (previewName) previewName.textContent = nameInput?.value || 'Channel name';
    if (previewUsername) previewUsername.textContent = usernameInput?.value || slugifyChannel(nameInput?.value || '') || 'username';
}

function syncMediaPreview() {
    if (avatarInput?.value && avatarPreview) avatarPreview.src = avatarInput.value;
    if (bannerContainer) {
        bannerContainer.innerHTML = bannerInput?.value
            ? `<img src="${bannerInput.value}" alt="">`
            : '<div class="ondemand-cover-empty"><i class="ph ph-image me-1"></i> Cover image</div>';
    }
}

function confirmPlaylistDelete(name) {
    const typed = window.prompt(`Type DELEET to delete playlist "${name}". Videos will stay assigned to the channel.`);
    return typed === 'DELEET';
}

if (nameInput && usernameInput) {
    usernameInput.dataset.manuallyEdited = '1';
    nameInput.addEventListener('input', syncPreview);
    usernameInput.addEventListener('input', syncPreview);
}
avatarInput?.addEventListener('change', () => setTimeout(syncMediaPreview, 0));
bannerInput?.addEventListener('change', () => setTimeout(syncMediaPreview, 0));
syncPreview();

(function () {
    const accessInputs = document.querySelectorAll('input[name="access"]');
    const planSelection = document.getElementById('planSelection');
    const planSelect = document.getElementById('plan_id');

    function syncPlanSelection() {
        const isPaid = (document.querySelector('input[name="access"]:checked')?.value || 'free') === 'paid';
        planSelection?.classList.toggle('d-none', !isPaid);
        if (!planSelect) return;
        planSelect.disabled = !isPaid;
        planSelect.required = isPaid;
        if (window.jQuery && jQuery.fn.select2) jQuery(planSelect).prop('disabled', !isPaid);
        if (!isPaid) {
            planSelect.value = '';
            if (window.jQuery && jQuery.fn.select2) jQuery(planSelect).val('').trigger('change.select2');
        }
    }

    window.syncAuthorChannelPlanSelection = syncPlanSelection;
    accessInputs.forEach((input) => input.addEventListener('change', syncPlanSelection));
    syncPlanSelection();
})();

(function () {
    const token = document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}';
    const playlistCreateToggle = document.getElementById('playlistCreateToggle');
    const playlistCreateCancel = document.getElementById('playlistCreateCancel');
    const playlistCreatePanel = document.getElementById('playlistCreatePanel');
    const playlistCreateName = playlistCreatePanel?.querySelector('input[name="name"]');

    function setPlaylistCreateOpen(isOpen) {
        if (!playlistCreatePanel || !playlistCreateToggle) return;
        playlistCreatePanel.classList.toggle('is-open', isOpen);
        playlistCreatePanel.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
        playlistCreateToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        playlistCreateToggle.classList.toggle('d-none', isOpen);
        if (isOpen) setTimeout(() => playlistCreateName?.focus(), 220);
    }

    playlistCreateToggle?.addEventListener('click', () => setPlaylistCreateOpen(true));
    playlistCreateCancel?.addEventListener('click', () => setPlaylistCreateOpen(false));

    const stateKey = 'author-channel-edit-state-{{ $channel->id }}';

    function activePlaylistId() {
        return document.querySelector('[data-playlist-trigger].active')?.dataset.playlistTrigger || null;
    }

    function saveEditState(tabTarget = null, playlistId = null) {
        sessionStorage.setItem(stateKey, JSON.stringify({
            tab: tabTarget || document.querySelector('#ondemandEditTabs .nav-link.active')?.dataset.bsTarget || '#tab-channel-details',
            playlist: playlistId || activePlaylistId(),
        }));
    }

    function escapeHtml(value) {
        const div = document.createElement('div');
        div.textContent = value || '';
        return div.innerHTML;
    }

    function createPlaylistVideoRow(video) {
        const row = document.createElement('div');
        row.className = 'playlist-video-row';
        row.draggable = true;
        row.dataset.videoId = video.id;
        row.innerHTML = `
            <span class="playlist-drag"><i class="ph ph-dots-six-vertical"></i></span>
            <img src="${escapeHtml(video.thumbnail)}" alt="" onerror="this.onerror=null;this.src='{{ asset('default-image/Default-Image.jpg') }}';">
            <div class="min-w-0">
                <div class="fw-semibold text-truncate">${escapeHtml(video.name)}</div>
                <div class="text-muted small">Drag to reorder</div>
            </div>
            <form action="${escapeHtml(video.remove_url)}" method="POST" onsubmit="return confirm('Remove this video from the playlist?')">
                <input type="hidden" name="_token" value="${escapeHtml(token)}">
                <button type="submit" class="btn btn-sm btn-outline-danger">
                    <i class="ph ph-x"></i>
                </button>
            </form>
        `;
        return row;
    }

    function createAssignedVideoCard(video) {
        const card = document.createElement('div');
        card.className = 'ondemand-video-card';
        card.dataset.assignedVideoCard = '';
        card.dataset.searchText = (video.name || '').toLowerCase();
        card.innerHTML = `
            <img src="${escapeHtml(video.thumbnail)}" class="ondemand-video-thumb" alt="" onerror="this.onerror=null;this.src='{{ asset('default-image/Default-Image.jpg') }}';">
            <div class="ondemand-video-body">
                <div>
                    <div class="ondemand-video-title fw-semibold">${escapeHtml(video.name)}</div>
                    ${video.duration ? `<span class="badge bg-secondary-subtle text-secondary mt-2">${escapeHtml(video.duration)}</span>` : ''}
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <a href="${escapeHtml(video.edit_url)}" class="btn btn-sm btn-outline-primary" target="_blank">
                        <i class="ph ph-pencil-simple"></i> Edit
                    </a>
                    <form action="${escapeHtml(video.unassign_url)}" method="POST" onsubmit="return confirm('Remove this video from the channel?')">
                        <input type="hidden" name="_token" value="${escapeHtml(token)}">
                        <button type="submit" class="btn btn-sm btn-outline-danger">
                            <i class="ph ph-x"></i> Remove
                        </button>
                    </form>
                </div>
            </div>
        `;
        return card;
    }

    function createEmptyPlaylistMessage() {
        const empty = document.createElement('div');
        empty.className = 'ondemand-empty';
        empty.innerHTML = `
            <i class="ph ph-list-bullets d-block mb-2" style="font-size: 2rem;"></i>
            No playlists yet. Create one from assigned videos.
        `;
        return empty;
    }

    document.querySelectorAll('[data-sortable-playlist]').forEach((list) => {
        let dragging = null;

        function rows() {
            return Array.from(list.querySelectorAll('[data-video-id]'));
        }

        function saveOrder() {
            const videoIds = rows().map((row) => row.dataset.videoId).filter(Boolean);
            fetch(list.dataset.reorderUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': token,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ video_ids: videoIds }),
            }).catch(() => undefined);
        }

        list.addEventListener('dragstart', (event) => {
            const row = event.target.closest('[data-video-id]');
            if (!row) return;
            dragging = row;
            row.classList.add('is-dragging');
            event.dataTransfer.effectAllowed = 'move';
        });

        list.addEventListener('dragover', (event) => {
            event.preventDefault();
            if (!dragging) return;
            const siblings = rows().filter((row) => row !== dragging);
            const next = siblings.find((row) => event.clientY <= row.getBoundingClientRect().top + row.offsetHeight / 2);
            list.insertBefore(dragging, next || null);
        });

        list.addEventListener('dragend', () => {
            if (!dragging) return;
            dragging.classList.remove('is-dragging');
            dragging = null;
            saveOrder();
        });
    });

    const playlistSearch = document.getElementById('playlistSearch');
    const playlistStatus = document.getElementById('playlistStatusFilter');
    const playlistTriggers = Array.from(document.querySelectorAll('[data-playlist-trigger]'));
    const playlistPanels = Array.from(document.querySelectorAll('[data-playlist-panel]'));
    const savedState = (() => {
        try {
            return JSON.parse(sessionStorage.getItem(stateKey) || '{}');
        } catch (error) {
            return {};
        }
    })();

    if (savedState.tab && window.bootstrap) {
        const tabButton = document.querySelector(`[data-bs-target="${savedState.tab}"]`);
        if (tabButton) bootstrap.Tab.getOrCreateInstance(tabButton).show();
    }

    function activatePlaylist(id) {
        playlistTriggers.forEach((trigger) => {
            trigger.classList.toggle('active', trigger.dataset.playlistTrigger === String(id));
        });
        playlistPanels.forEach((panel) => {
            panel.classList.toggle('active', panel.dataset.playlistPanel === String(id));
        });
    }

    function selectNextPlaylistAfterDelete(deletedId) {
        const remainingTrigger = Array.from(document.querySelectorAll('[data-playlist-trigger]'))
            .find((trigger) => trigger.dataset.playlistTrigger !== String(deletedId) && !trigger.classList.contains('is-hidden'));

        if (remainingTrigger) {
            activatePlaylist(remainingTrigger.dataset.playlistTrigger);
            saveEditState('#tab-playlists', remainingTrigger.dataset.playlistTrigger);
            return;
        }

        sessionStorage.setItem(stateKey, JSON.stringify({ tab: '#tab-playlists', playlist: null }));
    }

    playlistTriggers.forEach((trigger) => {
        trigger.addEventListener('click', () => {
            activatePlaylist(trigger.dataset.playlistTrigger);
            saveEditState('#tab-playlists', trigger.dataset.playlistTrigger);
        });
    });

    if (savedState.playlist) activatePlaylist(savedState.playlist);

    document.querySelectorAll('#ondemandEditTabs .nav-link').forEach((tab) => {
        tab.addEventListener('shown.bs.tab', () => saveEditState(tab.dataset.bsTarget, activePlaylistId()));
    });

    document.querySelectorAll('form').forEach((form) => {
        form.addEventListener('submit', () => saveEditState(
            form.closest('#tab-playlists') ? '#tab-playlists' : (form.closest('#tab-assign-videos') ? '#tab-assign-videos' : null),
            form.closest('[data-playlist-panel]')?.dataset.playlistPanel || activePlaylistId()
        ));
    });

    document.querySelectorAll('[data-playlist-delete-form]').forEach((form) => {
        form.addEventListener('submit', async (event) => {
            event.preventDefault();

            const playlistId = form.dataset.playlistId;
            const playlistName = form.dataset.playlistName || 'this playlist';
            if (!confirmPlaylistDelete(playlistName)) return;

            const button = form.querySelector('button[type="submit"]');
            if (button) {
                button.disabled = true;
                button.dataset.originalText = button.innerHTML;
                button.innerHTML = '<i class="ph ph-circle-notch"></i> Deleting';
            }

            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': token,
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: new FormData(form),
                });
                const data = await response.json();

                if (!response.ok || !data.success) {
                    throw new Error(data.message || 'Unable to delete playlist.');
                }

                const trigger = document.querySelector(`[data-playlist-trigger="${playlistId}"]`);
                const panel = document.querySelector(`[data-playlist-panel="${playlistId}"]`);
                trigger?.remove();
                panel?.remove();
                selectNextPlaylistAfterDelete(playlistId);

                document.querySelectorAll('[data-playlist-count]').forEach((badge) => {
                    if (data.playlist_count === undefined) return;
                    badge.textContent = badge.dataset.playlistCountFormat === 'number'
                        ? String(data.playlist_count)
                        : `${data.playlist_count} playlist(s)`;
                });

                if (data.playlist_count === 0) {
                    const workspace = document.querySelector('.playlist-workspace');
                    workspace?.replaceWith(createEmptyPlaylistMessage());
                }
            } catch (error) {
                alert(error.message || 'Unable to delete playlist.');
                if (button) {
                    button.disabled = false;
                    button.innerHTML = button.dataset.originalText || '<i class="ph ph-trash"></i> Delete Playlist';
                }
            }
        });
    });

    document.querySelectorAll('[data-playlist-add-form]').forEach((form) => {
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            const button = form.querySelector('button[type="submit"]');
            const select = form.querySelector('select[name="video_id"]');
            const playlistId = form.dataset.playlistId;
            const list = form.closest('[data-playlist-panel]')?.querySelector('[data-sortable-playlist]');
            const selectedOption = select?.selectedOptions?.[0];

            if (!select?.value || !list || !button) return;

            saveEditState('#tab-playlists', playlistId);
            button.disabled = true;
            button.dataset.originalText = button.innerHTML;
            button.innerHTML = '<i class="ph ph-circle-notch"></i> Adding';

            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': token,
                        'Accept': 'application/json',
                    },
                    body: new FormData(form),
                });
                const data = await response.json();

                if (!response.ok || !data.success) {
                    throw new Error(data.message || 'Unable to add video.');
                }

                if (data.added && data.video && !list.querySelector(`[data-video-id="${data.video.id}"]`)) {
                    list.querySelector('.ondemand-empty')?.remove();
                    list.appendChild(createPlaylistVideoRow(data.video));
                }

                const countBadge = document.querySelector(`[data-playlist-trigger="${playlistId}"] .badge`);
                if (countBadge && data.playlist_count !== undefined) countBadge.textContent = data.playlist_count;

                if (selectedOption) selectedOption.remove();
                if (window.jQuery && jQuery.fn.select2) {
                    jQuery(select).val('').trigger('change');
                } else {
                    select.value = '';
                }
            } catch (error) {
                alert(error.message || 'Unable to add video.');
            } finally {
                button.disabled = false;
                button.innerHTML = button.dataset.originalText || '<i class="ph ph-plus-circle"></i> Add Video';
            }
        });
    });

    document.querySelectorAll('[data-assign-video-form]').forEach((form) => {
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            const button = form.querySelector('button[type="submit"]');
            const select = form.querySelector('select[name="video_id"]');
            const selectedOption = select?.selectedOptions?.[0];
            const grid = document.getElementById('assignedVideoGrid');
            const countBadge = document.getElementById('assignedVideoCount');

            if (!select?.value || !button || !grid) return;

            saveEditState('#tab-assign-videos', activePlaylistId());
            button.disabled = true;
            button.dataset.originalText = button.innerHTML;
            button.innerHTML = '<i class="ph ph-circle-notch"></i> Assigning';

            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': token,
                        'Accept': 'application/json',
                    },
                    body: new FormData(form),
                });
                const data = await response.json();

                if (!response.ok || !data.success) {
                    throw new Error(data.message || 'Unable to assign video.');
                }

                if (data.added && data.video && !grid.querySelector(`[data-assigned-video-card][data-video-id="${data.video.id}"]`)) {
                    grid.querySelector('.ondemand-empty')?.remove();
                    const card = createAssignedVideoCard(data.video);
                    card.dataset.videoId = data.video.id;
                    grid.prepend(card);
                }

                if (countBadge && data.assigned_count !== undefined) {
                    countBadge.textContent = `${data.assigned_count} video(s)`;
                }

                if (selectedOption) selectedOption.remove();
                document.querySelectorAll('.playlist-video-select').forEach((playlistSelect) => {
                    if (!data.video || playlistSelect.querySelector(`option[value="${data.video.id}"]`)) return;
                    playlistSelect.append(new Option(data.video.name, data.video.id));
                });

                if (window.jQuery && jQuery.fn.select2) {
                    jQuery(select).val('').trigger('change');
                    jQuery('.playlist-video-select').trigger('change.select2');
                } else {
                    select.value = '';
                }
            } catch (error) {
                alert(error.message || 'Unable to assign video.');
            } finally {
                button.disabled = false;
                button.innerHTML = button.dataset.originalText || '<i class="ph ph-plus-circle"></i> Assign';
            }
        });
    });

    function filterPlaylists() {
        const term = (playlistSearch?.value || '').trim().toLowerCase();
        const status = playlistStatus?.value || 'all';
        let firstVisibleId = null;

        playlistTriggers.forEach((trigger) => {
            const matchesTerm = !term || (trigger.dataset.searchText || '').includes(term);
            const matchesStatus = status === 'all' || trigger.dataset.status === status;
            const isVisible = matchesTerm && matchesStatus;
            trigger.classList.toggle('is-hidden', !isVisible);
            if (isVisible && firstVisibleId === null) firstVisibleId = trigger.dataset.playlistTrigger;
        });

        if (firstVisibleId !== null) {
            activatePlaylist(firstVisibleId);
            return;
        }

        playlistPanels.forEach((panel) => panel.classList.remove('active'));
    }

    playlistSearch?.addEventListener('input', filterPlaylists);
    playlistStatus?.addEventListener('change', filterPlaylists);

    document.querySelectorAll('.playlist-status-toggle input').forEach((input) => {
        input.addEventListener('change', () => {
            const label = input.closest('.playlist-status-toggle')?.querySelector('span:last-child');
            if (label) label.textContent = input.checked ? 'Active' : 'Inactive';
        });
    });

    const assignedSearch = document.getElementById('assignedVideoSearch');
    assignedSearch?.addEventListener('input', () => {
        const term = assignedSearch.value.trim().toLowerCase();
        document.querySelectorAll('[data-assigned-video-card]').forEach((card) => {
            const matches = !term || (card.dataset.searchText || '').includes(term);
            card.classList.toggle('d-none', !matches);
        });
    });
})();
</script>
@endsection
