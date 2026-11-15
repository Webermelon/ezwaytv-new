<?php

namespace App\Console\Commands;

use App\Services\TvLog;
use FilesystemIterator;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Throwable;

final class FlushTvLogs extends Command
{
    protected $signature = 'tv-logs:flush';

    protected $description = 'Deliver queued TV events to the dedicated log database';

    public function handle(): int
    {
        if (!config('tv-logs.enabled')) {
            return self::SUCCESS;
        }

        $spool = config('tv-logs.spool');
        $lock = @fopen($spool.'/flush.lock', 'c');
        if ($lock === false) {
            $this->error('TV log spool lock unavailable.');
            return self::FAILURE;
        }

        try {
            if (!flock($lock, LOCK_EX | LOCK_NB)) {
                return self::SUCCESS;
            }

            $deadline = microtime(true) + 15;
            $processed = 0;
            foreach (['critical' => TvLog::CRITICAL, 'analytics' => TvLog::ANALYTICS] as $priority => $allowedTables) {
                foreach (new FilesystemIterator($spool.'/'.$priority) as $file) {
                    if ($processed >= 1500 || microtime(true) > $deadline) {
                        break 2;
                    }

                    if (!$file->isFile() || !preg_match('/\A[0-9a-f]{32}\.json\z/', $file->getFilename())) {
                        continue;
                    }

                    $payload = json_decode(file_get_contents($file->getPathname()), true);
                    if (!is_array($payload) || !in_array($payload['table'] ?? '', $allowedTables, true)
                        || !is_array($payload['row'] ?? null)) {
                        $this->quarantine($file->getPathname(), $spool);
                        continue;
                    }

                    $row = $payload['row'];
                    foreach (['event_id', 'playback_id', 'account_fingerprint', 'network_fingerprint', 'error_fingerprint'] as $key) {
                        if (isset($row[$key])) {
                            $row[$key] = hex2bin($row[$key]);
                        }
                    }
                    // Keep the accepted timestamp stable across retries and set it in UTC.
                    $row['received_at'] = $row['occurred_at'];

                    try {
                        DB::connection('tv_logs')->table($payload['table'])->insert($row);
                    } catch (QueryException $exception) {
                        $driverCode = $exception->errorInfo[1] ?? null;
                        if (in_array($driverCode, [1048, 1054, 1264, 1366, 1406, 4025], true)) {
                            $this->quarantine($file->getPathname(), $spool);
                            $this->warn('TV log record quarantined after a validation error.');
                            continue;
                        }
                        if ($driverCode !== 1062) {
                            throw $exception;
                        }
                    }

                    if (!@unlink($file->getPathname())) {
                        throw new \RuntimeException('TV log spool record could not be removed.');
                    }
                    $processed++;
                }
            }

            if ($processed > 0) {
                $this->info('TV log events delivered: '.$processed);
            }
            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error('TV event delivery paused. Queued events remain available for retry.');
            error_log('TV event delivery failed ('.get_class($exception).').');
            return self::FAILURE;
        } finally {
            fclose($lock);
        }
    }

    private function quarantine(string $path, string $spool): void
    {
        if (!@rename($path, $spool.'/quarantine/'.basename($path))) {
            throw new \RuntimeException('TV log spool record could not be quarantined.');
        }
    }
}
