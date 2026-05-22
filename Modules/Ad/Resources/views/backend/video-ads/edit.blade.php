@extends('backend.layouts.app')
@section('title') {{ __('messages.edit') }} {{ __('messages.video_ad') }} @endsection
@section('content')
<x-back-button-component route="backend.video-ads.index" />

{{ html()->modelForm($videoAd, 'PUT', route('backend.video-ads.update', $videoAd->id))
    ->attribute('id', 'form-submit')
    ->attribute('enctype', 'multipart/form-data')
    ->class('requires-validation')
    ->open()
}}

@csrf
@method('PUT')
<div class="card">
    <div class="card-body">
        <div class="row gy-3">
            <div class="col-md-6">
                {{ html()->label(__('messages.ad_name') . ' <span class="text-danger">*</span>', 'name')->class('form-label') }}
                {{ html()->text('name')
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
                    ->class('form-control')
                }}
            </div>

            <div class="col-md-12">
                {{ html()->label(__('messages.description'), 'description')->class('form-label') }}
                {{ html()->textarea('description')
                    ->class('form-control')
                    ->rows(3)
                }}
            </div>

            <div class="col-md-12">
                <div class="position-relative">
                    {{ html()->label(__('messages.video_file'), 'video_file')->class('form-label') }}
                    <div class="input-group btn-file-upload">
                        <button type="button"
                            class="input-group-text form-control"
                            style="height:8rem"
                            data-bs-toggle="modal"
                            data-bs-target="#exampleModal"
                            data-image-container="selectedVideoContainer"
                            data-hidden-input="video_file_url">
                            <i class="ph ph-video"></i> {{ __('messages.choose_video') }}
                        </button>
                    </div>
                    <div class="uploaded-image" id="selectedVideoContainer">
                        @if($videoAd->video_url)
                            <video width="400" controls>
                                <source src="{{ $videoAd->video_url }}" type="{{ $videoAd->mime_type }}">
                            </video>
                        @endif
                    </div>
                    {{ html()->text('video_file_url')
                        ->id('video_file_url')
                        ->class('form-control mt-2')
                        ->value($videoAd->video_url ?? '')
                        ->placeholder('https://...or select from media above')
                        ->attribute('data-validation', 'video')
                    }}
                    <small class="text-muted">Leave empty to keep current video. Select new video from File Manager</small>
                    @error('video_file_url')
                        <span class="text-danger">{{ $message }}</span>
                    @enderror
                </div>
            </div>

            <div class="col-md-6">
                {{ html()->label(__('messages.duration') . ' (seconds)', 'duration')->class('form-label') }}
                {{ html()->number('duration')
                    ->class('form-control')
                    ->attribute('min', 1)
                }}
            </div>

            <div class="col-md-6">
                {{ html()->label(__('messages.advertiser'), 'advertiser')->class('form-label') }}
                {{ html()->text('advertiser')
                    ->class('form-control')
                }}
            </div>

            <div class="col-md-6">
                {{ html()->label(__('messages.click_through_url'), 'click_through_url')->class('form-label') }}
                {{ html()->text('click_through_url')
                    ->class('form-control')
                }}
            </div>

            <div class="col-md-4">
                <div class="form-check form-switch mt-4">
                    {{ html()->checkbox('is_skippable', $videoAd->is_skippable, 1)
                        ->class('form-check-input')
                        ->id('is_skippable')
                    }}
                    {{ html()->label(__('messages.allow_skip'), 'is_skippable')->class('form-check-label') }}
                </div>
            </div>

            <div class="col-md-4">
                {{ html()->label(__('messages.skip_after') . ' (seconds)', 'skip_offset')->class('form-label') }}
                {{ html()->number('skip_offset')
                    ->class('form-control')
                    ->attribute('min', 0)
                }}
            </div>

            <div class="col-md-4">
                {{ html()->label(__('messages.status'), 'status')->class('form-label') }}
                <div class="form-check form-switch mt-2">
                    {{ html()->checkbox('status', $videoAd->status, 1)
                        ->class('form-check-input')
                        ->id('status')
                    }}
                    {{ html()->label(__('messages.active'), 'status')->class('form-check-label') }}
                </div>
            </div>

            <div class="col-md-6">
                {{ html()->label(__('messages.impression_url'), 'impression_url')->class('form-label') }}
                {{ html()->text('impression_url')
                    ->class('form-control')
                }}
            </div>

            <div class="col-md-6">
                {{ html()->label(__('messages.click_tracking_url'), 'click_tracking_url')->class('form-label') }}
                {{ html()->text('click_tracking_url')
                    ->class('form-control')
                }}
            </div>

            <div class="col-md-12">
                <div class="alert alert-info">
                    <strong>VAST XML URL:</strong><br>
                    <div class="input-group mt-2">
                        <input type="text" class="form-control" value="{{ $videoAd->vast_xml_url }}" readonly id="vast-url-input">
                        <button type="button" class="btn btn-primary" onclick="copyVastUrl()">
                            <i class="fa fa-copy"></i> Copy URL
                        </button>
                    </div>
                    <small class="text-muted mt-2 d-block">Use this URL in your VAST Ad settings</small>
                </div>
            </div>
        </div>
    </div>
    <div class="card-footer text-end">
        <button type="submit" class="btn btn-primary">
            <i class="ph ph-floppy-disk"></i> {{ __('messages.update') }}
        </button>
    </div>
</div>

{{ html()->closeModelForm() }}

@include('components.media-modal', ['page_type' => 'video-ads'])

<script>
function copyVastUrl() {
    const input = document.getElementById('vast-url-input');
    input.select();
    input.setSelectionRange(0, 99999);
    navigator.clipboard.writeText(input.value);
    alert('VAST URL copied to clipboard!');
}

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
@endsection
