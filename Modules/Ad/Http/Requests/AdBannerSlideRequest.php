<?php

namespace Modules\Ad\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AdBannerSlideRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title'      => 'nullable|string|max:255',
            'image'      => 'required|string',
            'link_url'   => 'nullable|url|max:500',
            'placements' => 'required|array|min:1',
            'placements.*' => 'in:home,tvshow,video,livetv,all',
            'sort_order' => 'nullable|integer|min:0',
            'status'     => 'nullable|boolean',
        ];
    }
}
