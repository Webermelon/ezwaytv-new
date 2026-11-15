<?php

namespace App\Services;

trait UsesTvAnalyticsStorage
{
    public function getTable(): string
    {
        return TvAnalyticsStorage::name(parent::getTable());
    }
}
