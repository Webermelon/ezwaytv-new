<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

final class RefreshDashboardStorage extends Command
{
    protected $signature = 'tv-admin:refresh-storage-usage';

    protected $description = 'Refresh the cached TV admin dashboard storage total';

    public function handle(): int
    {
        $disk = env('ACTIVE_STORAGE', 'local');
        $key = 'admin-dashboard:storage-bytes:'.$disk;

        try {
            $refreshed = Cache::lock($key.':refresh', 120)->get(function () use ($disk, $key) {
                $config = config('filesystems.disks.'.$disk, []);
                $storage = ($config['driver'] ?? null) === 's3'
                    ? Storage::build(array_replace_recursive($config, [
                        'http' => ['connect_timeout' => 2, 'timeout' => 3],
                        'retries' => 0,
                    ]))
                    : Storage::disk($disk);

                $bytes = 0;
                foreach ($storage->getDriver()->listContents('', true) as $item) {
                    if ($item->isFile()) {
                        $bytes += $item->fileSize() ?? $storage->size($item->path());
                    }
                }

                if (!Cache::forever($key, $bytes)) {
                    throw new RuntimeException('Dashboard storage cache write failed.');
                }

                $this->info("TV dashboard storage total refreshed: $bytes bytes.");
                return true;
            });

            if ($refreshed === false) {
                $this->info('A TV dashboard storage refresh is already running.');
            }

            return self::SUCCESS;
        } catch (Throwable $exception) {
            Log::warning('TV dashboard storage refresh failed', [
                'disk' => $disk,
                'exception' => get_class($exception),
            ]);
            $this->error('TV dashboard storage refresh failed.');

            return self::FAILURE;
        }
    }
}
