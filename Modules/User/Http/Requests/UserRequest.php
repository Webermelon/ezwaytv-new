<?php

namespace Modules\User\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
class UserRequest extends FormRequest
{
   public function rules()
    {
        $rules = [
            'first_name' => ['required'],
            'last_name' => ['required'],
            'file_url' => ['required'],
            'email' => [
                'required',
                'email',
                Rule::unique('users')->ignore($this->route('user'))
            ],
            'mobile' => ['required'], 
            'gender' => ['required', 'in:male,female,other'],
            'date_of_birth' => ['required', 'date', 'before_or_equal:today'],
            'access_role' => ['required', 'string', Rule::in(['user', 'content_manager', 'admin'])],
        ];


        if ($this->isMethod('post')) {
            $rules['password'] = [
                'required', 
                'min:8', 
                'max:14',
                'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&#^()_+\-=\[\]{};\':"\\|,.<>\/])[A-Za-z\d@$!%*?&#^()_+\-=\[\]{};\':"\\|,.<>\/]{8,14}$/',
                'confirmed'
            ];
            $rules['password_confirmation'] = ['required'];
        }

        return $rules;
    }


    public function messages()
    {
        return [
            'first_name.required' => __('messages.first_name_required'),
            'last_name.required' => __('messages.last_name_required'),
            'file_url.required' => 'Please choose a profile image.',
            'email.required' => __('messages.email_required'),
            'email.email' => __('messages.email_invalid'),
            'email.unique' => __('messages.email_unique'),
            'password.required' => __('messages.password_field_required'),
            'password.min' => __('messages.password_min'),
            'password.max' => __('messages.password_max'),
            'password.regex' => __('messages.password_requirements'),
            'password.confirmed' => __('messages.passwords_do_not_match'),
            'password_confirmation.required' => __('messages.confirm_password_field_required'),
            'gender.required' => __('messages.gender_required'),
            'mobile.required' => __('messages.mobile_required'),
            'gender.in' => __('messages.gender_invalid'),
            'date_of_birth.required' => __('messages.date_of_birth_required'),
            'date_of_birth.date' => 'Please enter a valid date of birth.',
            'date_of_birth.before_or_equal' => 'Date of birth cannot be in the future.',
            'access_role.required' => 'Please select an access role.',
            'access_role.in' => 'Please select a valid access role.',
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
