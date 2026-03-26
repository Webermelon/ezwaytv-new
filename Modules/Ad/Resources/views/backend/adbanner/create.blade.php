@extends('backend.layouts.app')

@section('title')
    {{ __('messages.new') }} {{ __('sidebar.ad_banner_slides') }}
@endsection

@section('content')
    <x-back-button-component route="backend.adbannersides.index" />

    {{ html()->form('POST', route('backend.adbannersides.store'))->attribute('id', 'form-submit')->class('requires-validation')->attribute('novalidate', 'novalidate')->open() }}
    @csrf

    <div class="card">
        <div class="card-body">
            <div class="row gy-3">

                {{-- Banner Image --}}
                <div class="col-md-6 col-lg-4">
                    <div class="position-relative">
                        <label class="form-label">{{ __('messages.lbl_banner_image') }} <span class="text-danger">*</span></label>
                        <div class="input-group btn-file-upload">
                            <button type="button"
                                class="input-group-text form-control"
                                style="height:13.6rem"
                                data-bs-toggle="modal"
                                data-bs-target="#exampleModal"
                                data-image-container="selectedAdBannerImageContainer"
                                data-hidden-input="image">
                                <i class="ph ph-image"></i> {{ __('messages.lbl_choose_image') }}
                            </button>
                        </div>
                        <div class="uploaded-image" id="selectedAdBannerImageContainer">
                            @if(old('image'))
                                <img src="{{ old('image') }}" class="img-fluid avatar-150">
                            @endif
                        </div>
                        {{ html()->text('image')->id('image')->class('form-control mt-2' . ($errors->has('image') ? ' is-invalid' : ''))->value(old('image', ''))->placeholder('https://...or select from media above') }}
                        @error('image')
                            <span class="text-danger small">{{ $message }}</span>
                        @enderror
                        <small class="text-muted">{{ __('messages.lbl_banner_image_hint') }}</small>
                    </div>
                </div>

                {{-- Title --}}
                <div class="col-md-6 col-lg-4">
                    <label class="form-label">{{ __('messages.lbl_title') }}</label>
                    {{ html()->text('title')->class('form-control')->value(old('title'))->placeholder(__('messages.lbl_title')) }}
                </div>

                {{-- Link URL --}}
                <div class="col-md-6 col-lg-4">
                    <label class="form-label">{{ __('messages.lbl_link_url') }}</label>
                    {{ html()->text('link_url')->class('form-control')->value(old('link_url'))->placeholder('https://example.com') }}
                </div>

                {{-- Placements --}}
                <div class="col-md-6 col-lg-4">
                    <label class="form-label">{{ __('messages.lbl_placements') }} <span class="text-danger">*</span></label>
                    <select name="placements[]" class="form-control select2 {{ $errors->has('placements') ? 'is-invalid' : '' }}" multiple style="width:100%">
                        @php $selectedPlacements = old('placements', []); @endphp
                        <option value="all" {{ in_array('all', $selectedPlacements) ? 'selected' : '' }}>{{ __('messages.lbl_all_pages') }}</option>
                        <option value="home" {{ in_array('home', $selectedPlacements) ? 'selected' : '' }}>{{ __('frontend.home') }}</option>
                        <option value="tvshow" {{ in_array('tvshow', $selectedPlacements) ? 'selected' : '' }}>{{ __('frontend.tvshows') }}</option>
                        <option value="video" {{ in_array('video', $selectedPlacements) ? 'selected' : '' }}>{{ __('frontend.video') }}</option>
                        <option value="livetv" {{ in_array('livetv', $selectedPlacements) ? 'selected' : '' }}>{{ __('frontend.live_tv') }}</option>
                    </select>
                    @error('placements')
                        <span class="text-danger small">{{ $message }}</span>
                    @enderror
                    <small class="text-muted">{{ __('messages.lbl_placements_hint') }}</small>
                </div>

                {{-- Sort Order --}}
                <div class="col-md-6 col-lg-2">
                    <label class="form-label">{{ __('messages.lbl_order') }}</label>
                    {{ html()->number('sort_order')->class('form-control')->value(old('sort_order', 0))->attribute('min', 0) }}
                </div>

                {{-- Status --}}
                <div class="col-md-6 col-lg-2">
                    <label class="form-label">{{ __('messages.lbl_status') }}</label>
                    <div class="form-check form-switch mt-2">
                        <input class="form-check-input" type="checkbox" name="status" id="status" value="1"
                            {{ old('status', '1') == '1' ? 'checked' : '' }}>
                        <label class="form-check-label" for="status">{{ __('messages.active') }}</label>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <div class="card mt-3">
        <div class="card-body">
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="ph ph-floppy-disk me-1"></i>{{ __('messages.save') }}
                </button>
                <a href="{{ route('backend.adbannersides.index') }}" class="btn btn-secondary">
                    {{ __('messages.cancel') }}
                </a>
            </div>
        </div>
    </div>

    {{ html()->form()->close() }}

    @include('components.media-modal', ['page_type' => 'ads'])
@endsection

@push('after-scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    var modal = document.getElementById('exampleModal');
    if (!modal) return;
    modal.addEventListener('shown.bs.modal', function() {
        if (typeof FileManager !== 'undefined' && FileManager.navigation && FileManager.navigation.openFolder) {
            setTimeout(function() {
                FileManager.navigation.openFolder('ads/image');
            }, 150);
        }
    });
});
</script>
@endpush
