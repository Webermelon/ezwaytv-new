<?php

namespace Modules\Page\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PageRequest extends FormRequest
{
   public function rules()
    {
        $page = $this->route('page');

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('pages', 'slug')->ignore(optional($page)->id)],
            'status' => ['nullable', 'boolean'],
            'content_type' => ['required', Rule::in(['landing', 'embed'])],
            'description' => [Rule::requiredIf($this->input('content_type', 'landing') === 'landing'), 'nullable', 'string'],
            'embed_code' => [Rule::requiredIf($this->input('content_type') === 'embed'), 'nullable', 'string'],
        ];
    }


    public function messages()
    {
        return [
            'name.required' => __('messages.name_field_required'),
            'name.string' => __('messages.name_must_be_string'),
            'name.max' => __('messages.name_max_length'),
            'slug.required' => 'Custom URL is required.',
            'slug.regex' => 'Custom URL may only contain lowercase letters, numbers, and hyphens.',
            'slug.unique' => 'This custom URL is already in use.',
            'description.required' => __('messages.description_field_required'),
            'description.string' => __('messages.description_must_be_string'),
            'content_type.required' => 'Page type is required.',
            'content_type.in' => 'Invalid page type selected.',
            'embed_code.required' => 'Embed code is required for embed pages.',
        ];
    }
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }
}
