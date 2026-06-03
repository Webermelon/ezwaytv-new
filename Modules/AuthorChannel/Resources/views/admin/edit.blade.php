@extends('backend.layouts.app')

@section('title')
    Edit On Demand Channel
@endsection

@section('content')
<x-back-button-component route="backend.author_channels.index" />

<form action="{{ route('backend.author_channels.update', $channel->id) }}" method="POST">
    @csrf
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="card-title mb-0">Edit On Demand Channel</h4>
        </div>
        <div class="card-body">

            @if ($errors->any())
                <div class="alert alert-danger">
                    <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                </div>
            @endif

            <div class="row gy-3">

                {{-- On Demand Channel Name --}}
                <div class="col-md-6">
                    <label class="form-label">On Demand Channel Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" id="channelName" class="form-control @error('name') is-invalid @enderror"
                           value="{{ old('name', $channel->name) }}" required>
                    @error('name')<span class="text-danger">{{ $message }}</span>@enderror
                </div>

                {{-- Username --}}
                <div class="col-md-6">
                    <label class="form-label">Username <span class="text-muted">(URL slug)</span></label>
                    <div class="input-group">
                        <span class="input-group-text text-muted">/on-demand/</span>
                        <input type="text" name="username" id="channelUsername" class="form-control @error('username') is-invalid @enderror"
                               value="{{ old('username', $channel->username) }}" placeholder="e.g. ezway-vod" pattern="[a-z0-9\-]+">
                    </div>
                    @error('username')<span class="text-danger">{{ $message }}</span>@enderror
                </div>

                {{-- Linked User --}}
                <div class="col-md-6">
                    <label class="form-label">Linked User <span class="text-muted">(optional)</span></label>
                    <select name="user_id" class="form-control select2">
                        <option value="">-- No User --</option>
                        @foreach(\App\Models\User::orderBy('first_name')->get() as $u)
                            <option value="{{ $u->id }}" {{ old('user_id', $channel->user_id) == $u->id ? 'selected' : '' }}>
                                {{ $u->first_name }} {{ $u->last_name }} ({{ $u->email }})
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Description --}}
                <div class="col-12">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="4">{{ old('description', $channel->description) }}</textarea>
                </div>

                {{-- Avatar / Profile Photo --}}
                <div class="col-md-6">
                    <div class="position-relative">
                        <label class="form-label">Profile Photo (Avatar)</label>
                        <div class="input-group btn-file-upload">
                            <button type="button" class="input-group-text form-control"
                                data-bs-toggle="modal" data-bs-target="#exampleModal"
                                data-image-container="avatarImageContainer"
                                data-hidden-input="file_url_avatar"
                                style="height:13.8rem">
                                <i class="ph ph-image"></i>&nbsp;Choose Image
                            </button>
                            <input type="text" class="form-control"
                                placeholder="Or paste image URL"
                                data-bs-toggle="modal" data-bs-target="#exampleModal"
                                data-image-container="avatarImageContainer"
                                data-hidden-input="file_url_avatar">
                        </div>
                        <div class="uploaded-image" id="avatarImageContainer">
                            @php $avatarVal = old('avatar', $channel->avatar); @endphp
                            @if($avatarVal)
                                <img src="{{ $avatarVal }}" class="img-fluid mb-2"
                                     style="max-width:100px;max-height:100px;">
                            @endif
                        </div>
                        <input type="hidden" name="avatar" id="file_url_avatar" value="{{ old('avatar', $channel->avatar) }}">
                    </div>
                </div>

                {{-- Banner / Cover Photo --}}
                <div class="col-md-6">
                    <div class="position-relative">
                        <label class="form-label">Cover Photo (Banner)</label>
                        <div class="input-group btn-file-upload">
                            <button type="button" class="input-group-text form-control"
                                data-bs-toggle="modal" data-bs-target="#exampleModal"
                                data-image-container="bannerImageContainer"
                                data-hidden-input="file_url_banner"
                                style="height:13.8rem">
                                <i class="ph ph-image"></i>&nbsp;Choose Image
                            </button>
                            <input type="text" class="form-control"
                                placeholder="Or paste image URL"
                                data-bs-toggle="modal" data-bs-target="#exampleModal"
                                data-image-container="bannerImageContainer"
                                data-hidden-input="file_url_banner">
                        </div>
                        <div class="uploaded-image" id="bannerImageContainer">
                            @php $bannerVal = old('banner', $channel->banner); @endphp
                            @if($bannerVal)
                                <img src="{{ $bannerVal }}" class="img-fluid mb-2"
                                     style="max-width:100px;max-height:100px;">
                            @endif
                        </div>
                        <input type="hidden" name="banner" id="file_url_banner" value="{{ old('banner', $channel->banner) }}">
                    </div>
                </div>

                {{-- Active --}}
                <div class="col-md-3">
                    <label class="form-label">Active</label>
                    <select name="is_active" class="form-control">
                        <option value="1" {{ old('is_active', $channel->is_active) == 1 ? 'selected' : '' }}>Yes</option>
                        <option value="0" {{ old('is_active', $channel->is_active) == 0 ? 'selected' : '' }}>No</option>
                    </select>
                </div>

                <div class="col-12 mt-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="ph ph-floppy-disk"></i> Update On Demand Channel
                    </button>
                    <a href="{{ route('backend.author_channels.index') }}" class="btn btn-secondary ms-2">Cancel</a>
                </div>

            </div>
        </div>
    </div>
</form>

{{-- Assigned Videos --}}
<div class="card mt-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Assigned Videos</h5>
        <span class="badge bg-secondary">{{ $channel->videos->count() }} video(s)</span>
    </div>
    <div class="card-body">

        {{-- Assign existing video form --}}
        <form action="{{ route('backend.author_channels.videos.assign', $channel->id) }}" method="POST" class="mb-4">
            @csrf
            <div class="row g-2 align-items-end">
                <div class="col-md-8">
                    <label class="form-label fw-semibold">Assign Existing Video</label>
                    <select name="video_id" id="assignVideoSelect" class="form-control" style="width:100%" required>
                        <option value="">— Select a video —</option>
                        @foreach($availableVideos as $av)
                            <option value="{{ $av->id }}">{{ $av->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-success w-100">
                        <i class="ph ph-plus-circle"></i> Assign Video
                    </button>
                </div>
            </div>
        </form>

        <hr>

        {{-- Upload new video link --}}
        <div class="mb-3">
            <a href="{{ route('backend.videos.create') }}?author_channel_id={{ $channel->id }}&return_channel={{ $channel->id }}" class="btn btn-outline-primary btn-sm">
                <i class="ph ph-upload-simple"></i> Upload New Video
            </a>
            <small class="text-muted ms-2">Uploads will be automatically linked to this On Demand Channel.</small>
        </div>

        {{-- Assigned video grid --}}
        @php
            $channelPoster = $channel->banner ?: $channel->avatar;
        @endphp
        <div class="row gy-3">
            @forelse($channel->videos as $v)
            <div class="col-md-3">
                <div class="card h-100 border">
                    <img src="{{ ($v->thumbnail_url ?: $v->poster_url) ? setBaseUrlWithFileNameV2($v->thumbnail_url ?: $v->poster_url) : ($channelPoster ? setBaseUrlWithFileNameV2($channelPoster) : asset('default-image/Default-Image.jpg')) }}"
                        class="card-img-top" style="height:120px;object-fit:cover;"
                        onerror="this.onerror=null;this.src='{{ asset('default-image/Default-Image.jpg') }}';">
                    <div class="card-body p-2">
                        <p class="mb-2 small fw-semibold">{{ Str::limit($v->name, 45) }}</p>
                        <div class="d-flex gap-1 flex-wrap">
                            <a href="{{ route('backend.videos.edit', $v->id) }}"
                               class="btn btn-sm btn-outline-primary" target="_blank">Edit</a>
                            <form action="{{ route('backend.author_channels.videos.unassign', [$channel->id, $v->id]) }}"
                                  method="POST" onsubmit="return confirm('Remove this video from the channel?')">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-outline-danger">Remove</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            @empty
            <p class="text-muted col-12">No videos assigned yet. Use the form above to assign videos.</p>
            @endforelse
        </div>

    </div>
</div>

@include('components.media-modal', ['page_type' => 'author-channel'])

<script>
document.getElementById('exampleModal')?.addEventListener('show.bs.modal', function () {
    setTimeout(function () {
        if (typeof FileManager !== 'undefined' && FileManager.navigation) {
            if (!FileManager.state.currentFolder) {
                FileManager.navigation.openFolder('author-channel');
            }
        }
    }, 300);
});

// Select2 on the assign-video dropdown (static options, search-enabled)
$(document).ready(function () {
    var $sel = $('#assignVideoSelect');
    if ($sel.length && typeof $.fn.select2 !== 'undefined') {
        $sel.select2({
            theme: 'bootstrap-5',
            placeholder: '— Select a video —',
            allowClear: true,
            width: '100%'
        });
    }
});
// Auto-slug: only update if user manually edits the field
const nameInput = document.getElementById('channelName');
const usernameInput = document.getElementById('channelUsername');
if (nameInput && usernameInput) {
    usernameInput.dataset.manuallyEdited = '1'; // edit page: don't auto-overwrite
}
</script>
@endsection
