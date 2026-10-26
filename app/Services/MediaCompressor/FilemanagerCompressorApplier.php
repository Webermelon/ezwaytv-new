<?php

namespace App\Services\MediaCompressor;

use App\Models\MediaCompressorJob;
use Aws\S3\MultipartUploader;
use Aws\S3\S3Client;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Modules\Filemanager\Models\Filemanager;

class FilemanagerCompressorApplier
{
    public function apply(MediaCompressorJob $job, ?string $primaryUrl = null): void
    {
        $primaryUrl = $primaryUrl ?: (string) $job->primary_url;

        if ($primaryUrl === '') {
            throw new \RuntimeException('Compressor job completed without a primary URL.');
        }

        $filemanager = $job->owner;
        if (! $filemanager instanceof Filemanager) {
            return;
        }

        $payload = $job->payload ?? [];
        $targetPath = (string) data_get($payload, 'target_path', '');
        $diskType = (string) data_get($payload, 'disk_type', config('filesystems.active', env('ACTIVE_STORAGE', 'local')));

        if ($targetPath === '') {
            $pageType = (string) data_get($payload, 'page_type', 'default');
            $fileType = (string) ($job->media_type ?: data_get($payload, 'file_type', 'video'));
            $targetPath = $this->targetPath($diskType, $pageType, $fileType, $filemanager->file_name);
        }

        $downloadedPath = $this->download($primaryUrl);

        try {
            $this->writeToDisk($downloadedPath, $targetPath, $diskType);
        } finally {
            @unlink($downloadedPath);
        }

        $filemanager->file_url = $targetPath;
        if (Schema::hasColumn('filemanagers', 'status')) {
            $filemanager->status = 'ready';
        }
        if (Schema::hasColumn('filemanagers', 'remote_job_id')) {
            $filemanager->remote_job_id = $job->remote_job_id;
        }
        $filemanager->save();
    }

    public function fail(MediaCompressorJob $job, ?string $message = null): void
    {
        $filemanager = $job->owner;
        if (! $filemanager instanceof Filemanager) {
            return;
        }

        if (Schema::hasColumn('filemanagers', 'status')) {
            $filemanager->status = 'failed';
        }
        if (Schema::hasColumn('filemanagers', 'remote_job_id')) {
            $filemanager->remote_job_id = $job->remote_job_id;
        }
        $filemanager->save();

        Log::warning('Media compressor job failed', [
            'remote_job_id' => $job->remote_job_id,
            'message' => $message,
        ]);
    }

    public function targetPath(string $diskType, string $pageType, string $fileType, string $fileName): string
    {
        if ($pageType === 'season') {
            $pageType = 'tvshow/season';
        } elseif ($pageType === 'episode') {
            $pageType = 'tvshow/episode';
        }

        $prefix = $diskType === 'local' ? 'public/' : '';

        return $prefix.trim($pageType, '/').'/'.$fileType.'/'.$fileName;
    }

    private function download(string $url): string
    {
        $tmp = storage_path('app/temp/compressor_'.uniqid('', true).'_'.basename(parse_url($url, PHP_URL_PATH) ?: 'media'));
        $directory = dirname($tmp);
        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $response = Http::timeout((int) config('services.media_compressor.download_timeout'))
            ->sink($tmp)
            ->get($url)
            ->throw();

        if (! file_exists($tmp) || filesize($tmp) === 0) {
            throw new \RuntimeException('Unable to download compressed media: '.$response->status());
        }

        return $tmp;
    }

    private function writeToDisk(string $sourcePath, string $targetPath, string $diskType): void
    {
        if ($diskType === 'local') {
            $directory = dirname(storage_path('app/'.$targetPath));
            if (! is_dir($directory)) {
                mkdir($directory, 0775, true);
            }

            $stream = fopen($sourcePath, 'rb');
            Storage::disk('local')->writeStream($targetPath, $stream);
            if (is_resource($stream)) {
                fclose($stream);
            }

            @chmod(storage_path('app/'.$targetPath), 0664);
            @chmod($directory, 0775);

            return;
        }

        if (in_array($diskType, ['dg-ocean', 's3'], true)) {
            $this->uploadS3CompatibleFile($sourcePath, $targetPath, $diskType);
            return;
        }

        $stream = fopen($sourcePath, 'rb');
        Storage::disk($diskType)->writeStream($targetPath, $stream);
        if (is_resource($stream)) {
            fclose($stream);
        }
    }

    private function uploadS3CompatibleFile(string $sourcePath, string $targetPath, string $diskType): void
    {
        $diskConfig = config("filesystems.disks.{$diskType}");
        if (! $diskConfig || ($diskConfig['driver'] ?? null) !== 's3') {
            throw new \RuntimeException("Disk {$diskType} is not an S3-compatible disk.");
        }

        $client = new S3Client([
            'version' => 'latest',
            'region' => $diskConfig['region'] ?? 'us-east-1',
            'endpoint' => $diskConfig['endpoint'] ?? null,
            'use_path_style_endpoint' => filter_var($diskConfig['use_path_style_endpoint'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'credentials' => [
                'key' => $diskConfig['key'] ?? '',
                'secret' => $diskConfig['secret'] ?? '',
            ],
        ]);

        (new MultipartUploader($client, $sourcePath, [
            'bucket' => $diskConfig['bucket'],
            'key' => ltrim($targetPath, '/'),
            'part_size' => 16 * 1024 * 1024,
            'concurrency' => 1,
            'params' => [
                'ACL' => 'public-read',
                'ContentType' => mime_content_type($sourcePath) ?: 'application/octet-stream',
            ],
        ]))->upload();
    }
}
