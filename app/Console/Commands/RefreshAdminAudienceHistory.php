<?php

namespace App\Console\Commands;

use App\Services\AdminAudienceHistory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

final class RefreshAdminAudienceHistory extends Command
{
    protected $signature = 'tv-admin:refresh-audience-history';

    protected $description = 'Refresh the cached history on the TV admin Statistics page';

    public function handle(AdminAudienceHistory $history): int
    {
        try {
            Cache::lock('tv-admin:audience-history:refresh', 120)->get(function () use ($history) {
                $history->refresh();
                $this->info('TV admin audience history refreshed.');
            });

            return self::SUCCESS;
        } catch (Throwable $exception) {
            Log::warning('TV admin audience history refresh failed', ['exception' => get_class($exception)]);
            $this->error('TV admin audience history refresh failed.');

            return self::FAILURE;
        }
    }
}
