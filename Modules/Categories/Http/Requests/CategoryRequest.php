<?php

namespace Modules\Categories\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CategoryRequest extends FormRequest
{
    public function rules()
    {
        return [
            'name'   => 'required|string|max:255|unique:categories,name,' . $this->route('category'),
            'status' => 'sometimes|boolean',
        ];
    }

    public function messages()
    {
        return [
            'name.required' => __('messages.name_required'),
            'name.string'   => __('messages.name_must_be_a_string'),
            'name.max'      => __('messages.name_cannot_exceed_255_characters'),
            'name.unique'   => __('messages.name_already_exists'),
            'status.boolean'=> __('messages.status_must_be_true_or_false'),
        ];
    }
}
