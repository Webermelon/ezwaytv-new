<?php

namespace Modules\Statistics\Models;

use Illuminate\Database\Eloquent\Model;

class StatSetting extends Model
{
    protected $table = 'stat_settings';

    protected $fillable = ['key', 'value'];

    public static function get(string $key, $default = null)
    {
        $record = static::where('key', $key)->first();
        return $record ? $record->value : $default;
    }

    public static function set(string $key, $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
    }
}
