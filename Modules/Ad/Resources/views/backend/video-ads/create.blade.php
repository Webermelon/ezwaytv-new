@extends('backend.layouts.app')
@section('title') {{ __('messages.create') }} {{ __('messages.video_ad') }} @endsection
@section('content')
<x-back-button-component route="backend.video-ads.index" />

{{ html()->form('POST', route('backend.video-ads.store'))
    ->attribute('id', 'form-submit')
    ->attribute('enctype', 'multipart/form-data')
    ->class('requires-validation')
    ->open()
}}

@csrf
<div class="card">
    <div class="card-body">
        <div class="row gy-3">
            <div class="col-md-6">
                {{ html()->label(__('messages.ad_name') . ' <span class="text-danger">*</span>', 'name')->class('form-label') }}
                {{ html()->text('name')
                    ->attribute('value', old('name'))
                    ->placeholder(__('messages.enter_name'))
                    ->class('form-control')
                    ->required()
                }}
                @error('name')
                    <span class="text-danger">{{ $message }}</span>
                @enderror
            </div>

            <div class="col-md-6">
                {{ html()->label(__('messages.title') . ' (Display Title)', 'title')->class('form-label') }}
                {{ html()->text('title')
                    ->attribute('value', old('title'))
                    ->placeholder(__('messages.enter_title'))
                    ->class('form-control')
                }}
                @error('title')
                    <span class="text-danger">{{ $message }}</span>
                @enderror
            </div>

            <div class="col-md-12">
                {{ html()->label(__('messages.description'), 'description')->class('form-label') }}
                {{ html()->textarea('description')
                    ->attribute('value', old('description'))
                    ->placeholder(__('messages.enter_description'))
                    ->class('form-control')
                    ->rows(3)
                }}
                @error('description')
                    <span class="text-danger">{{ $message }}</span>
                @enderror
            </div>

            <div class="col-md-12">
                <div class="position-relative">
                    {{ html()->label(__('messages.video_file') . ' <span class="text-danger">*</span>', 'video_file')->class('form-label') }}
                    <div class="input-group btn-file-upload">
                        <button type="button"
                            class="input-group-text form-control"
                            style="height:8rem"
                            data-bs-toggle="modal"
                            data-bs-target="#exampleModal"
                            data-image-container="selectedVideoContainer"
                            data-hidden-input="video_file">
                            <i class="ph ph-video"></i> {{ __('messages.choose_video') }}
                        </button>
                    </div>
                    <div class="uploaded-image" id="selectedVideoContainer">
                        @if(old('video_file'))
                            <video width="400" controls>
                                <source src="{{ old('video_file') }}" type="video/mp4">
                            </video>
                        @endif
                    </div>
                    {{ html()->text('video_file')
                        ->id('video_file')
                        ->class('form-control mt-2' . ($errors->has('video_file') ? ' is-invalid' : ''))
                        ->value(old('video_file', ''))
                        ->placeholder('https://...or select from media above')
                        ->attribute('data-validation', 'video')
                        ->required()
                    }}
                    @error('video_file')
                        <span class="text-danger">{{ $message }}</span>
                    @enderror
                    <small class="text-muted">Supported: MP4, WebM, OGG. Select from File Manager</small>
                </div>
            </div>

            <div class="col-md-6">
                {{ html()->label(__('messages.duration') . ' (seconds)', 'duration')->class('form-label') }}
                {{ html()->number('duration')
                    ->attribute('value', old('duration'))
                    ->placeholder('30')
                    ->class('form-control')
                    ->attribute('min', 1)
                }}
                @error('duration')
                    <span class="text-danger">{{ $message }}</span>
                @enderror
            </div>

            <div class="col-md-6">
                {{ html()->label(__('messages.advertiser'), 'advertiser')->class('form-label') }}
                {{ html()->text('advertiser')
                    ->attribute('value', old('advertiser'))
                    ->placeholder(__('messages.advertiser_name'))
                    ->class('form-control')
                }}
            </div>

            <div class="col-md-6">
                {{ html()->label(__('messages.click_through_url'), 'click_through_url')->class('form-label') }}
                {{ html()->text('click_through_url')
                    ->attribute('value', old('click_through_url'))
                    ->placeholder('https://example.com')
                    ->class('form-control')
                }}
                <small class="text-muted">Where users go when clicking the ad</small>
            </div>

            <div class="col-md-4">
                <div class="form-check form-switch mt-4">
                    {{ html()->checkbox('is_skippable', old('is_skippable'), 1)
                        ->class('form-check-input')
                        ->id('is_skippable')
                    }}
                    {{ html()->label(__('messages.allow_skip'), 'is_skippable')->class('form-check-label') }}
                </div>
            </div>

            <div class="col-md-4">
                {{ html()->label(__('messages.skip_after') . ' (seconds)', 'skip_offset')->class('form-label') }}
                {{ html()->number('skip_offset')
                    ->attribute('value', old('skip_offset', 5))
                    ->placeholder('5')
                    ->class('form-control')
                    ->attribute('min', 0)
                }}
                <small class="text-muted">Skip button shows after X seconds</small>
            </div>

            <div class="col-md-4">
                {{ html()->label(__('messages.status'), 'status')->class('form-label') }}
                <div class="form-check form-switch mt-2">
                    {{ html()->checkbox('status', old('status', true), 1)
                        ->class('form-check-input')
                        ->id('status')
                    }}
                    {{ html()->label(__('messages.active'), 'status')->class('form-check-label') }}
                </div>
            </div>

            <div class="col-md-6">
                {{ html()->label(__('messages.impression_url') . ' (Optional)', 'impression_url')->class('form-label') }}
                {{ html()->text('impression_url')
                    ->attribute('value', old('impression_url'))
                    ->placeholder('https://tracking.example.com/impression')
                    ->class('form-control')
                }}
                <small class="text-muted">Tracking pixel URL for impressions</small>
            </div>

            <div class="col-md-6">
                {{ html()->label(__('messages.click_tracking_url') . ' (Optional)', 'click_tracking_url')->class('form-label') }}
                {{ html()->text('click_tracking_url')
                    ->attribute('value', old('click_tracking_url'))
                    ->placeholder('https://tracking.example.com/click')
                    ->class('form-control')
                }}
                <small class="text-muted">Tracking URL for clicks</small>
            </div>
        </div>
    </div>
    <div class="card-footer text-end">
        <button type="submit" class="btn btn-primary">
            <i class="ph ph-floppy-disk"></i> {{ __('messages.save') }}
        </button>
    </div>
</div>

{{ html()->form()->close() }}

@include('components.media-modal', ['page_type' => 'video-ads'])
@endsection

@push('after-scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    var modal = document.getElementById('exampleModal');
    if (!modal) return;
    modal.addEventListener('shown.bs.modal', function() {
        if (typeof FileManager !== 'undefined' && FileManager.navigation && FileManager.navigation.openFolder) {
            setTimeout(function() {
                FileManager.navigation.openFolder('video-ads');
            }, 100);
        }
    });
});
</script>
@endpush
