<?php

namespace Modules\Ad\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VideoAdRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $rules = [
            'name' => 'required|string|max:255',
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'duration' => 'nullable|integer|min:1',
            'click_through_url' => 'nullable|url',
            'skip_offset' => 'nullable|string',
            'is_skippable' => 'boolean',
            'advertiser' => 'nullable|string|max:255',
            'impression_url' => 'nullable|url',
            'click_tracking_url' => 'nullable|url',
            'width' => 'nullable|integer|min:1',
            'height' => 'nullable|integer|min:1',
            'mime_type' => 'nullable|string',
            'status' => 'boolean',
        ];

        // Video file URL is required on create, optional on update
        if ($this->isMethod('post')) {
            $rules['video_file'] = 'required|string'; // URL from file manager
        } else {
            $rules['video_file_url'] = 'nullable|string'; // URL from file manager for edit
        }

        return $rules;
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => __('messages.ad_name_required'),
            'video_file.required' => __('messages.video_file_required'),
            'video_file.mimes' => __('messages.video_file_invalid_format'),
            'video_file.max' => __('messages.video_file_too_large'),
            'duration.min' => __('messages.duration_must_be_positive'),
        ];
    }
}
