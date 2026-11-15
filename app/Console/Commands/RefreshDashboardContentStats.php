<?php

namespace App\Console\Commands;

use App\Services\DashboardContentStats;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

final class RefreshDashboardContentStats extends Command
{
    protected $signature = 'tv-admin:refresh-content-stats';

    protected $description = 'Refresh the cached TV admin content inventory and Most Watched chart';

    public function handle(DashboardContentStats $stats): int
    {
        try {
            $refreshed = Cache::lock('admin-dashboard:content-stats:refresh', 120)->get(function () use ($stats) {
                $stats->refresh();
                $this->info('TV dashboard content stats refreshed.');

                return true;
            });

            if ($refreshed === false) {
                $this->info('A TV dashboard content stats refresh is already running.');
            }

            return self::SUCCESS;
        } catch (Throwable $exception) {
            Log::warning('TV dashboard content stats refresh failed', ['exception' => get_class($exception)]);
            $this->error('TV dashboard content stats refresh failed.');

            return self::FAILURE;
        }
    }
}
