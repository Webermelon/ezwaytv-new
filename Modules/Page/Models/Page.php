<?php

namespace Modules\Page\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Page extends BaseModel
{
    use HasFactory;
    use SoftDeletes;

    public const CONTENT_TYPE_LANDING = 'landing';
    public const CONTENT_TYPE_EMBED = 'embed';

    protected $table = 'pages';
    protected $fillable = ['name', 'description', 'status', 'slug', 'content_type', 'embed_code'];

    const CUSTOM_FIELD_MODEL = 'Modules\Page\Models\Page';

    /**
     * Create a new factory instance for the model.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */


    protected $appends = ['feature_image', 'public_url'];

    protected function getFeatureImageAttribute()
    {
        $media = $this->getFirstMediaUrl('feature_image');
        return isset($media) && ! empty($media) ? $media : 'https://dummyimage.com/600x300/cfcfcf/000000.png';
    }

    protected function getPublicUrlAttribute(): string
    {
        return route('page.show', ['slug' => $this->slug]);
    }

    protected static function newFactory()
    {
        // return \Modules\Page\database\factories\PageFactory::new();
    }

    protected static function boot()
    {
        parent::boot();

        static::saving(function ($page) {
            $slug = Str::slug($page->slug ?: $page->name);

            if (empty($slug)) {
                $slug = Str::slug($page->name);
            }

            $originalSlug = $slug;
            $count = 1;

            while (self::where('slug', $slug)->where('id', '!=', $page->id)->exists()) {
                $slug = $originalSlug . '-' . $count++;
            }

            $page->slug = $slug;
            $page->content_type = $page->content_type ?: self::CONTENT_TYPE_LANDING;
        });
    }
    public static function getValueBySlug($slug)
    {
        // Retrieve the setting by slug
        $page = self::where('slug', $slug)->first();
        // dd($page);

        // If the setting exists, return its value, otherwise return null
        return $page ? $page->description : null;
    }

}
