<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Modules\Frontend\Http\Controllers\API\NavigationMenuController;
use Throwable;

final class RefreshPublicNavigation extends Command
{
    protected $signature = 'tv-public:refresh-navigation';

    protected $description = 'Refresh the public navigation catalog';

    public function handle(NavigationMenuController $navigation): int
    {
        try {
            $navigation->refreshCatalog();
            $this->info('TV public navigation catalog refreshed.');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            Log::warning('TV public navigation refresh failed', ['exception' => get_class($exception)]);
            $this->error('TV public navigation refresh failed.');

            return self::FAILURE;
        }
    }
}
