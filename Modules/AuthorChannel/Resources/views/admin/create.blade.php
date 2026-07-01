@extends('backend.layouts.app')

@section('title')
    Create On Demand Channel
@endsection

@section('content')
<x-back-button-component route="backend.author_channels.index" />

@php
    $avatarValue = old('avatar');
    $bannerValue = old('banner');
    $selectedAccess = old('access', 'free');
@endphp

<style>
    .ondemand-shell { display: grid; gap: 1.5rem; }
    .ondemand-card { border: 1px solid rgba(255,255,255,.08); border-radius: .75rem; overflow: hidden; }
    .ondemand-card .card-header { padding: 1.1rem 1.25rem; }
    .ondemand-card .card-body { padding: 1.25rem; }
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
    @media (max-width: 991.98px) { .ondemand-preview { position: static; } }
</style>

<form action="{{ route('backend.author_channels.store') }}" method="POST">
    @csrf
    <div class="ondemand-shell">
        <div class="card ondemand-card">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div>
                    <h4 class="card-title mb-1">Create On Demand Channel</h4>
                    <p class="mb-0 text-muted small">Set up the public profile, artwork, and access rules.</p>
                </div>
                <span class="badge bg-primary-subtle text-primary">New channel</span>
            </div>
            <div class="card-body">
                @if ($errors->any())
                    <div class="alert alert-danger">
                        <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                    </div>
                @endif

                <div class="row g-4">
                    <div class="col-lg-8">
                        <div class="row g-3">
                            <div class="col-md-7">
                                <label class="form-label">Channel Name <span class="text-danger">*</span></label>
                                <input type="text" name="name" id="channelName" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" required placeholder="e.g. eZWay Family">
                                @error('name')<span class="text-danger">{{ $message }}</span>@enderror
                            </div>

                            <div class="col-md-5">
                                <label class="form-label">Linked User <span class="text-muted">(optional)</span></label>
                                <select name="user_id" class="form-control select2">
                                    <option value="">-- No User --</option>
                                    @foreach(\App\Models\User::orderBy('first_name')->get() as $u)
                                        <option value="{{ $u->id }}" {{ old('user_id') == $u->id ? 'selected' : '' }}>{{ $u->first_name }} {{ $u->last_name }} ({{ $u->email }})</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-12">
                                <label class="form-label">Username <span class="text-muted">(auto-generated if blank)</span></label>
                                <div class="input-group">
                                    <span class="input-group-text text-muted">/on-demand/</span>
                                    <input type="text" name="username" id="channelUsername" class="form-control @error('username') is-invalid @enderror" value="{{ old('username') }}" placeholder="ezway-family" pattern="[a-z0-9\-]+">
                                </div>
                                @error('username')<span class="text-danger">{{ $message }}</span>@enderror
                            </div>

                            <div class="col-12">
                                <label class="form-label">Description</label>
                                <textarea name="description" id="channelDescription" class="form-control" rows="5" placeholder="Short description shown on the channel page.">{{ old('description') }}</textarea>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Active</label>
                                <select name="is_active" class="form-control">
                                    <option value="1" {{ old('is_active','1') == '1' ? 'selected' : '' }}>Active</option>
                                    <option value="0" {{ old('is_active') == '0' ? 'selected' : '' }}>Inactive</option>
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
                                        <option value="{{ $plan->id }}" {{ old('plan_id') == $plan->id ? 'selected' : '' }}>{{ $plan->name }}</option>
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
                                    <img src="{{ $bannerValue }}" alt="">
                                @else
                                    <div class="ondemand-cover-empty"><i class="ph ph-image me-1"></i> Cover image</div>
                                @endif
                            </div>
                            <div class="ondemand-avatar-wrap">
                                <img src="{{ $avatarValue ?: asset('images/default-avatar.png') }}" class="ondemand-avatar" id="avatarPreview" alt="" onerror="this.onerror=null;this.src='{{ asset('images/default-avatar.png') }}';">
                            </div>
                            <div class="px-3 pt-2">
                                <h5 class="mb-1" id="previewName">{{ old('name') ?: 'Channel name' }}</h5>
                                <div class="ondemand-url-pill text-muted small">
                                    <i class="ph ph-link me-2"></i><span>/on-demand/</span><span id="previewUsername">{{ old('username') ?: 'username' }}</span>
                                </div>
                                <div class="ondemand-media-actions">
                                    <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#exampleModal" data-image-container="avatarPickerContainer" data-hidden-input="file_url_avatar">
                                        <i class="ph ph-user-circle"></i> Avatar
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#exampleModal" data-image-container="bannerImageContainer" data-hidden-input="file_url_banner">
                                        <i class="ph ph-image"></i> Cover
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
                        <i class="ph ph-floppy-disk"></i> Create Channel
                    </button>
                </div>
            </div>
        </div>
    </div>
</form>

@include('components.media-modal', ['page_type' => 'author-channel'])

<script>
document.getElementById('exampleModal')?.addEventListener('show.bs.modal', function () {
    setTimeout(function () {
        if (typeof FileManager !== 'undefined' && FileManager.navigation && !FileManager.state.currentFolder) {
            FileManager.navigation.openFolder('author-channel');
        }
    }, 300);
});

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

if (nameInput && usernameInput) {
    nameInput.addEventListener('input', function () {
        if (!usernameInput.dataset.manuallyEdited) usernameInput.value = slugifyChannel(nameInput.value);
        syncPreview();
    });
    usernameInput.addEventListener('input', function () {
        usernameInput.dataset.manuallyEdited = '1';
        syncPreview();
    });
}
avatarInput?.addEventListener('change', () => setTimeout(syncMediaPreview, 0));
bannerInput?.addEventListener('change', () => setTimeout(syncMediaPreview, 0));
syncPreview();
syncMediaPreview();

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
</script>
@endsection
