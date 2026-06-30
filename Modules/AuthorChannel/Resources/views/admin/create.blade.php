@extends('backend.layouts.app')

@section('title')
    Create On Demand Channel
@endsection

@section('content')
<x-back-button-component route="backend.author_channels.index" />

<form action="{{ route('backend.author_channels.store') }}" method="POST">
    @csrf
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="card-title mb-0">Create On Demand Channel</h4>
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
                           value="{{ old('name') }}" required>
                    @error('name')<span class="text-danger">{{ $message }}</span>@enderror
                </div>

                {{-- Username --}}
                <div class="col-md-6">
                    <label class="form-label">Username <span class="text-muted">(URL slug, auto-generated if blank)</span></label>
                    <div class="input-group">
                        <span class="input-group-text text-muted">/on-demand/</span>
                        <input type="text" name="username" id="channelUsername" class="form-control @error('username') is-invalid @enderror"
                               value="{{ old('username') }}" placeholder="e.g. ezway-vod" pattern="[a-z0-9\-]+">
                    </div>
                    @error('username')<span class="text-danger">{{ $message }}</span>@enderror
                </div>

                {{-- Linked User --}}
                <div class="col-md-6">
                    <label class="form-label">Linked User <span class="text-muted">(optional)</span></label>
                    <select name="user_id" class="form-control select2">
                        <option value="">-- No User --</option>
                        @foreach(\App\Models\User::orderBy('first_name')->get() as $u)
                            <option value="{{ $u->id }}" {{ old('user_id') == $u->id ? 'selected' : '' }}>
                                {{ $u->first_name }} {{ $u->last_name }} ({{ $u->email }})
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Description --}}
                <div class="col-12">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="4">{{ old('description') }}</textarea>
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
                            @if(old('avatar'))
                                <img src="{{ old('avatar') }}" class="img-fluid mb-2"
                                     style="max-width:100px;max-height:100px;">
                            @endif
                        </div>
                        <input type="hidden" name="avatar" id="file_url_avatar" value="{{ old('avatar') }}">
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
                            @if(old('banner'))
                                <img src="{{ old('banner') }}" class="img-fluid mb-2"
                                     style="max-width:100px;max-height:100px;">
                            @endif
                        </div>
                        <input type="hidden" name="banner" id="file_url_banner" value="{{ old('banner') }}">
                    </div>
                </div>

                {{-- Active --}}
                <div class="col-md-3">
                    <label class="form-label">Active</label>
                    <select name="is_active" class="form-control">
                        <option value="1" {{ old('is_active','1') == '1' ? 'selected' : '' }}>Yes</option>
                        <option value="0" {{ old('is_active') == '0' ? 'selected' : '' }}>No</option>
                    </select>
                </div>

                {{-- Access --}}
                <div class="col-md-3">
                    <label class="form-label">Access <span class="text-danger">*</span></label>
                    <div class="d-flex gap-4 pt-2">
                        <label class="form-check">
                            <input class="form-check-input" type="radio" name="access" value="free"
                                onchange="window.syncAuthorChannelPlanSelection && window.syncAuthorChannelPlanSelection()"
                                {{ old('access', 'free') === 'free' ? 'checked' : '' }}>
                            <span class="form-check-label">Free</span>
                        </label>
                        <label class="form-check">
                            <input class="form-check-input" type="radio" name="access" value="paid"
                                onchange="window.syncAuthorChannelPlanSelection && window.syncAuthorChannelPlanSelection()"
                                {{ old('access') === 'paid' ? 'checked' : '' }}>
                            <span class="form-check-label">Paid</span>
                        </label>
                    </div>
                    @error('access')<span class="text-danger">{{ $message }}</span>@enderror
                </div>

                <div class="col-md-6 {{ old('access', 'free') === 'paid' ? '' : 'd-none' }}" id="planSelection" data-plan-selection>
                    <label class="form-label">Subscription Plan <span class="text-danger">*</span></label>
                    <select name="plan_id" id="plan_id" class="form-control select2" data-plan-select>
                        <option value="">-- Select Plan --</option>
                        @foreach($plans as $plan)
                            <option value="{{ $plan->id }}" {{ old('plan_id') == $plan->id ? 'selected' : '' }}>
                                {{ $plan->name }}
                            </option>
                        @endforeach
                    </select>
                    <small class="text-muted d-block mt-1">Required when Access is Paid.</small>
                    @error('plan_id')<span class="text-danger">{{ $message }}</span>@enderror
                </div>

                <div class="col-12 mt-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="ph ph-floppy-disk"></i> Create On Demand Channel
                    </button>
                    <a href="{{ route('backend.author_channels.index') }}" class="btn btn-secondary ms-2">Cancel</a>
                </div>

            </div>
        </div>
    </div>
</form>

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

// Auto-generate username slug from channel name (only if username is still empty)
const nameInput = document.getElementById('channelName');
const usernameInput = document.getElementById('channelUsername');
if (nameInput && usernameInput) {
    nameInput.addEventListener('input', function () {
        if (!usernameInput.dataset.manuallyEdited) {
            usernameInput.value = nameInput.value
                .toLowerCase()
                .replace(/[^a-z0-9]+/g, '-')
                .replace(/^-+|-+$/g, '');
        }
    });
    usernameInput.addEventListener('input', function () {
        usernameInput.dataset.manuallyEdited = '1';
    });
}

(function () {
    const accessInputs = document.querySelectorAll('input[name="access"]');
    const planSelection = document.getElementById('planSelection');
    const planSelect = document.getElementById('plan_id');

    function syncPlanSelection() {
        const selectedAccess = document.querySelector('input[name="access"]:checked')?.value || 'free';
        const isPaid = selectedAccess === 'paid';

        if (planSelection) {
            planSelection.classList.toggle('d-none', !isPaid);
        }

        if (!planSelect) return;

        planSelect.disabled = !isPaid;
        planSelect.required = isPaid;

        if (window.jQuery && jQuery.fn.select2) {
            jQuery(planSelect).prop('disabled', !isPaid);
        }

        if (!isPaid) {
            planSelect.value = '';
            if (window.jQuery && jQuery.fn.select2) {
                jQuery(planSelect).val('').trigger('change.select2');
            }
        }
    }

    window.syncAuthorChannelPlanSelection = syncPlanSelection;
    accessInputs.forEach((input) => input.addEventListener('change', syncPlanSelection));
    syncPlanSelection();
})();
</script>
@endsection
