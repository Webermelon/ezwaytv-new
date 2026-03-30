<?php

namespace Modules\CastCrew\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;


class CastCrewRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {

        $rules = [
            'name' => ['required'],
            'type' => ['required'],
            // Make bio, dob, and place_of_birth optional per request
        
        ];

        return $rules;
        
    }

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function messages()
    {
        return [
            'name.required' => 'Name is required.',
            'type.required' =>'Type is required',
            // Optional: no validation messages for bio, dob, place_of_birth

        ];
    }
}
