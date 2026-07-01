<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Modules\Video\Models\Video;
use Symfony\Component\Process\Process;

class SyncVideoDurations extends Command
{
    protected $signature = 'videos:sync-durations
        {--id= : Sync a single video ID}
        {--force : Update videos even when duration already has a value}
        {--dry-run : Show detected durations without saving}
        {--with-trashed : Include soft-deleted videos}';

    protected $description = 'Detect saved video media durations with ffprobe and sync videos.duration.';

    public function handle(): int
    {
        if (!$this->hasFfprobe()) {
            $this->error('ffprobe is not available on this server.');
            return 1;
        }

        $query = Video::query();

        if ($this->option('with-trashed')) {
            $query->withTrashed();
        }

        if ($id = $this->option('id')) {
            $query->where('id', $id);
        }

        if (!$this->option('force')) {
            $query->where(function ($builder) {
                $builder->whereNull('duration')->orWhere('duration', '');
            });
        }

        $total = 0;
        $updated = 0;
        $unchanged = 0;
        $skipped = 0;
        $failed = 0;

        $query->orderBy('id')->chunkById(50, function ($videos) use (&$total, &$updated, &$unchanged, &$skipped, &$failed) {
            foreach ($videos as $video) {
                $total++;

                $source = $this->resolveVideoSource($video);
                if (!$source) {
                    $skipped++;
                    $this->warn("Skipped #{$video->id}: no probeable video source.");
                    continue;
                }

                $seconds = $this->probeDuration($source);
                if ($seconds === null) {
                    $failed++;
                    $this->warn("Failed #{$video->id}: could not read duration.");
                    continue;
                }

                $duration = $this->formatDuration($seconds);
                if ($video->duration === $duration) {
                    $unchanged++;
                    $this->line("Unchanged #{$video->id}: {$duration}");
                    continue;
                }

                $oldDuration = $video->duration ?: 'empty';
                $this->info("Detected #{$video->id}: {$oldDuration} -> {$duration}");

                if (!$this->option('dry-run')) {
                    $video->duration = $duration;
                    $video->save();
                }

                $updated++;
            }
        });

        $action = $this->option('dry-run') ? 'detected' : 'updated';
        $this->info("Done. Checked {$total}, {$action} {$updated}, unchanged {$unchanged}, skipped {$skipped}, failed {$failed}.");

        return $failed > 0 ? 1 : 0;
    }

    private function hasFfprobe(): bool
    {
        $process = new Process(['ffprobe', '-version']);
        $process->setTimeout(5);
        $process->run();

        return $process->isSuccessful();
    }

    private function resolveVideoSource(Video $video): ?string
    {
        $value = trim((string) $video->video_url_input);
        if ($value === '') {
            return null;
        }

        if (preg_match('/<iframe[^>]+src=["\']([^"\']+)["\']/i', $value, $matches)) {
            $value = $matches[1];
        }

        foreach ($this->localCandidates($value) as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        $url = $value;
        if ($video->video_upload_type === 'Local' && function_exists('setBaseUrlWithFileName')) {
            $url = setBaseUrlWithFileName($value, 'video', 'video');
        }

        if ($this->isProbeableUrl($url)) {
            return $url;
        }

        return null;
    }

    private function localCandidates(string $value): array
    {
        $path = (string) parse_url($value, PHP_URL_PATH);
        $fileName = basename($path ?: $value);
        $trimmedPath = ltrim($path ?: $value, '/');

        return array_values(array_unique(array_filter([
            $value,
            $trimmedPath ? public_path($trimmedPath) : null,
            $trimmedPath ? base_path($trimmedPath) : null,
            public_path("storage/video/video/{$fileName}"),
            storage_path("app/public/video/video/{$fileName}"),
            public_path("storage/video/{$fileName}"),
            storage_path("app/public/video/{$fileName}"),
            public_path("video/video/{$fileName}"),
            public_path("video/{$fileName}"),
        ])));
    }

    private function isProbeableUrl(string $url): bool
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        $path = strtolower((string) parse_url($url, PHP_URL_PATH));
        $extension = pathinfo($path, PATHINFO_EXTENSION);

        return in_array($extension, ['mp4', 'm4v', 'mov', 'webm', 'ogg', 'ogv', 'mkv', 'avi', 'm3u8', 'mpd'], true);
    }

    private function probeDuration(string $source): ?int
    {
        $process = new Process([
            'ffprobe',
            '-v',
            'error',
            '-show_entries',
            'format=duration',
            '-of',
            'default=nokey=1:noprint_wrappers=1',
            $source,
        ]);
        $process->setTimeout(30);
        $process->run();

        if (!$process->isSuccessful()) {
            return null;
        }

        $duration = (float) trim($process->getOutput());
        if ($duration <= 0) {
            return null;
        }

        return (int) round($duration);
    }

    private function formatDuration(int $seconds): string
    {
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $remainingSeconds = $seconds % 60;

        return sprintf('%02d:%02d:%02d', $hours, $minutes, $remainingSeconds);
    }
}
