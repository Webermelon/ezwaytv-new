<?php

namespace Modules\Categories\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends BaseModel
{
    use SoftDeletes;

    protected $table = 'categories';

    protected $fillable = [
        'name',
        'slug',
        'file_url',
        'description',
        'status',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    public function setSlugAttribute($value)
    {
        $this->attributes['slug'] = $value ?: \Illuminate\Support\Str::slug($this->attributes['name'] ?? '');
    }

    public function videos()
    {
        return $this->belongsToMany(
            \Modules\Video\Models\Video::class,
            'video_category_mapping',
            'category_id',
            'video_id'
        );
    }
}
