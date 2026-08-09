@extends('backend.layouts.app')

@section('title')
    {{ __('messages.edit') }} {{ __('sidebar.ad_banner_slides') }}
@endsection

@section('content')
    <x-back-button-component route="backend.adbannersides.index" />

    {{ html()->form('POST', route('backend.adbannersides.update', $data->id))->attribute('id', 'form-submit')->class('requires-validation')->attribute('novalidate', 'novalidate')->open() }}
    @csrf
    @method('PUT')

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
                            @if(old('image', $data->image))
                                <img src="{{ old('image') ? old('image') : route('backend.adbannersides.preview', ['slide' => $data->id]) }}" class="img-fluid avatar-150">
                            @endif
                        </div>
                        {{ html()->text('image')->id('image')->class('form-control mt-2' . ($errors->has('image') ? ' is-invalid' : ''))->value(old('image', $data->image))->placeholder('https://...or select from media above') }}
                        @error('image')
                            <span class="text-danger small">{{ $message }}</span>
                        @enderror
                        <small class="text-muted">{{ __('messages.lbl_banner_image_hint') }}</small>
                    </div>
                </div>

                {{-- Title --}}
                <div class="col-md-6 col-lg-4">
                    <label class="form-label">{{ __('messages.lbl_title') }}</label>
                    {{ html()->text('title')->class('form-control')->value(old('title', $data->title))->placeholder(__('messages.lbl_title')) }}
                </div>

                {{-- Link URL --}}
                <div class="col-md-6 col-lg-4">
                    <label class="form-label">{{ __('messages.lbl_link_url') }}</label>
                    {{ html()->text('link_url')->class('form-control')->value(old('link_url', $data->link_url))->placeholder('https://example.com') }}
                </div>

                {{-- Placements --}}
                <div class="col-md-6 col-lg-4">
                    <label class="form-label">{{ __('messages.lbl_placements') }} <span class="text-danger">*</span></label>
                    @php $selectedPlacements = old('placements', $data->placements ?? []); @endphp
                    <select name="placements[]" class="form-control select2 {{ $errors->has('placements') ? 'is-invalid' : '' }}" multiple style="width:100%">
                        <option value="all" {{ in_array('all', (array)$selectedPlacements) ? 'selected' : '' }}>{{ __('messages.lbl_all_pages') }}</option>
                        <option value="home" {{ in_array('home', (array)$selectedPlacements) ? 'selected' : '' }}>{{ __('frontend.home') }}</option>
                        <option value="tvshow" {{ in_array('tvshow', (array)$selectedPlacements) ? 'selected' : '' }}>{{ __('frontend.tvshows') }}</option>
                        <option value="video" {{ in_array('video', (array)$selectedPlacements) ? 'selected' : '' }}>{{ __('frontend.video') }}</option>
                        <option value="livetv" {{ in_array('livetv', (array)$selectedPlacements) ? 'selected' : '' }}>{{ __('frontend.live_tv') }}</option>
                    </select>
                    @error('placements')
                        <span class="text-danger small">{{ $message }}</span>
                    @enderror
                </div>

                {{-- Sort Order --}}
                <div class="col-md-6 col-lg-2">
                    <label class="form-label">{{ __('messages.lbl_order') }}</label>
                    {{ html()->number('sort_order')->class('form-control')->value(old('sort_order', $data->sort_order))->attribute('min', 0) }}
                </div>

                {{-- Status --}}
                <div class="col-md-6 col-lg-2">
                    <label class="form-label">{{ __('messages.lbl_status') }}</label>
                    <div class="form-check form-switch mt-2">
                        <input class="form-check-input" type="checkbox" name="status" id="status" value="1"
                            {{ old('status', $data->status) ? 'checked' : '' }}>
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
                    <i class="ph ph-floppy-disk me-1"></i>{{ __('messages.update') }}
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
