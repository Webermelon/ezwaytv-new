<?php

namespace Modules\Page\Transformers;

use Illuminate\Http\Resources\Json\JsonResource;

class PageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray($request): array
    {
        return [
            'id'   => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'description' => $this->description,
            'content_type' => $this->content_type,
            'embed_code' => $this->embed_code,
            'url'  => $this->public_url,
        ];
    }
}
