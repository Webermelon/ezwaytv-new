<?php

namespace App\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class TvAnalyticsStorage
{
    public static function name(string $table): string
    {
        $schema = config('database.analytics_schema');

        return $schema && !str_contains($table, '.') ? $schema.'.'.$table : $table;
    }

    public static function table(string $table): Builder
    {
        return DB::table(self::name($table));
    }
}
