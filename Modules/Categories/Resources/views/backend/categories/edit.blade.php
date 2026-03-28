@extends('backend.layouts.app')

@section('title')
    {{ $module_title ?? 'Edit Category' }}
@endsection

@section('content')
    <x-back-button-component route="backend.categories.index" />
    {{ html()->form('PUT', route('backend.categories.update', $category->id))->attribute('enctype', 'multipart/form-data')->attribute('id', 'form-submit')->class('requires-validation')->attribute('novalidate', 'novalidate')->open() }}
    <div class="card">
        <div class="card-body">
            <div class="row gy-3">
                <div class="col-md-12 col-xl-3 position-relative">
                    {{ html()->label(__('messages.image'), 'Image')->class('form-label') }}
                    <div class="input-group btn-file-upload">
                        {{ html()->button(__('<i class="ph ph-image"></i>' . __('messages.lbl_choose_image')))->class('input-group-text form-control')->type('button')->attribute('data-bs-toggle', 'modal')->attribute('data-bs-target', '#exampleModal')->attribute('data-image-container', 'selectedImageContainer1')->attribute('data-hidden-input', 'file_url1') }}
                        {{ html()->text('image_input1')->class('form-control')->placeholder(__('placeholder.lbl_image'))->attribute('aria-label', 'Image Input 1')->attribute('data-bs-toggle', 'modal')->attribute('data-bs-target', '#exampleModal')->attribute('data-image-container', 'selectedImageContainer1')->attribute('data-hidden-input', 'file_url1') }}
                    </div>
                    <div class="mb-3 uploaded-image" id="selectedImageContainer1">
                        @if ($category->file_url)
                            <img src="{{ $category->file_url }}" class="img-fluid mb-2 box-preview-image">
                            <span class="remove-media-icon" onclick="removeImage('selectedImageContainer1', 'file_url1', 'remove_image_flag1')">×</span>
                        @endif
                    </div>
                    {{ html()->hidden('file_url')->id('file_url1')->value($category->file_url) }}
                    {{ html()->hidden('remove_image')->id('remove_image_flag')->value(0) }}
                </div>

                <div class="col-xl-9">
                    <div class="row gy-3">
                        <div class="col-md-6 col-lg-6">
                            <div class="mb-3">
                                {{ html()->label(__('messages.name') . ' <span class="text-danger">*</span>', 'name')->class('form-label') }}
                                {{ html()->text('name', $category->name)->class('form-control')->id('name')->placeholder('Category name')->attribute('required', 'required')->attribute('maxlength', '255') }}
                                @error('name')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>

                            <div class="mb-3">
                                {{ html()->label('Description', 'description')->class('form-label') }}
                                {{ html()->textarea('description', $category->description)->class('form-control')->id('description')->placeholder('Category description (optional)')->rows(3) }}
                            </div>

                            <div>
                                {{ html()->label(__('plan.lbl_status'), 'status')->class('form-label') }}
                                <div class="d-flex justify-content-between align-items-center form-control">
                                    {{ html()->label(__('messages.active'), 'status')->class('form-label mb-0') }}
                                    <div class="form-check form-switch">
                                        {{ html()->hidden('status', 0) }}
                                        {{ html()->checkbox('status', $category->status)->class('form-check-input')->id('status')->value(1) }}
                                    </div>
                                </div>
                                @error('status')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="d-grid d-sm-flex justify-content-sm-end gap-3">
        {{ html()->submit(trans('messages.save'))->class('btn btn-md btn-primary float-right')->id('submit-button') }}
    </div>

    {{ html()->form()->close() }}

    @include('components.media-modal')

    <script>
        function removeImage(containerId, hiddenInputId, removedFlagId) {
            document.getElementById(containerId).innerHTML = '';
            document.getElementById(hiddenInputId).value = '';
            document.getElementById(removedFlagId).value = 1;
        }
    </script>
@endsection
