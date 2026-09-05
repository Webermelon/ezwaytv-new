<?php

namespace App\Jobs;

use App\Models\MediaCompressorJob;
use App\Services\MediaCompressor\FilemanagerCompressorApplier;
use App\Services\MediaCompressor\MediaCompressorClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Filemanager\Models\Filemanager;
use Illuminate\Support\Facades\Storage;
use Illuminate\Bus\Batchable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Aws\S3\S3Client;
use Aws\S3\MultipartUploader;
use Illuminate\Support\Facades\Schema;

class ProcessFileUpload implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Batchable;

    public $filemanager;
    public $filePath;
    public $diskType;
    public $originalName;
    public $page_type;
    public $fileType;
    /**
     * Create a new job instance.
     */
    public function __construct(Filemanager $filemanager, $filePath, $diskType, $originalName, $page_type, $fileType)
    {
        $this->filemanager = $filemanager;
        $this->filePath = $filePath;
        $this->diskType = $diskType;
        $this->originalName = $originalName;
        $this->page_type = $page_type;
        $this->fileType = $fileType;
    }

    /**
     * Attempt to compress an image using ffmpeg if available.
     * Returns path to processed file on success, or null on failure/no-op.
     */
    private function compressImage(string $inputPath)
    {
        if (!file_exists($inputPath)) {
            return null;
        }

        if (!function_exists('shell_exec')) {
            return null;
        }

        $ffmpeg = trim(shell_exec('command -v ffmpeg'));
        if (empty($ffmpeg)) {
            return null;
        }

        $quality = intval(env('MEDIA_COMPRESS_IMAGE_QUALITY', 75));
        $ext = strtolower(pathinfo($inputPath, PATHINFO_EXTENSION));
        $outputExt = $ext;
        // prefer jpg for widest compression unless original is webp
        if (!in_array($ext, ['jpg','jpeg','webp'])) {
            $outputExt = 'jpg';
        }

        $outputPath = storage_path('app/temp/processed_' . uniqid() . '.' . $outputExt);

        // Build ffmpeg command to re-encode image
        // Use libwebp for webp outputs, otherwise use mjpeg/jpeg encoder
        if ($outputExt === 'webp') {
            $cmd = sprintf('%s -y -i %s -qscale %d %s 2>&1', escapeshellcmd($ffmpeg), escapeshellarg($inputPath), max(10, min(100, $quality)), escapeshellarg($outputPath));
        } else {
            // re-encode to JPEG with quality
            $q = max(2, min(31, (int)(31 - ($quality / 100 * 29))));
            $cmd = sprintf('%s -y -i %s -q:v %d %s 2>&1', escapeshellcmd($ffmpeg), escapeshellarg($inputPath), $q, escapeshellarg($outputPath));
        }

        @unlink($outputPath);
        $output = shell_exec($cmd);

        if (file_exists($outputPath) && filesize($outputPath) > 0) {
            // only use processed if smaller
            if (filesize($outputPath) < filesize($inputPath)) {
                return $outputPath;
            }
            @unlink($outputPath);
        }

        return null;
    }

    /**
     * Attempt to compress a video using ffmpeg if available.
     * Returns path to processed file on success, or null on failure/no-op.
     */
    private function compressVideo(string $inputPath)
    {
        if (!file_exists($inputPath)) {
            return null;
        }

        if (!function_exists('shell_exec')) {
            return null;
        }

        $ffmpeg = trim(shell_exec('command -v ffmpeg'));
        if (empty($ffmpeg)) {
            return null;
        }

        $crf = intval(env('MEDIA_COMPRESS_VIDEO_CRF', 23));
        $preset = env('MEDIA_COMPRESS_VIDEO_PRESET', 'slow');
        $audioBitrate = env('MEDIA_COMPRESS_AUDIO_BITRATE', '128k');

        $ext = strtolower(pathinfo($inputPath, PATHINFO_EXTENSION));
        $outputPath = storage_path('app/temp/processed_' . uniqid() . '.' . $ext);

        // Transcode using x264 and aac
        $cmd = sprintf('%s -y -i %s -c:v libx264 -preset %s -crf %d -c:a aac -b:a %s %s 2>&1', escapeshellcmd($ffmpeg), escapeshellarg($inputPath), escapeshellarg($preset), $crf, escapeshellarg($audioBitrate), escapeshellarg($outputPath));

        @unlink($outputPath);
        $output = shell_exec($cmd);

        if (file_exists($outputPath) && filesize($outputPath) > 0) {
            // use processed only if appreciably smaller (or always if you prefer)
            if (filesize($outputPath) < filesize($inputPath) * 0.98) {
                return $outputPath;
            }
            @unlink($outputPath);
        }

        return null;
    }

    private function uploadS3CompatibleFile(string $sourcePath, string $targetPath, string $diskType): void
    {
        $diskConfig = config("filesystems.disks.{$diskType}");
        if (!$diskConfig || ($diskConfig['driver'] ?? null) !== 's3') {
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

        $uploader = new MultipartUploader($client, $sourcePath, [
            'bucket' => $diskConfig['bucket'],
            'key' => ltrim($targetPath, '/'),
            'part_size' => 16 * 1024 * 1024,
            'concurrency' => 1,
            'params' => [
                'ACL' => 'public-read',
                'ContentType' => mime_content_type($sourcePath) ?: 'application/octet-stream',
            ],
        ]);

        $uploader->upload();
    }

    private function remoteJobId(array $response): string
    {
        return (string) data_get($response, 'job.id',
            data_get($response, 'job_id',
                data_get($response, 'id',
                    data_get($response, 'data.job.id', '')
                )
            )
        );
    }

    private function primaryUrl(array $response): string
    {
        foreach ([
            'primary_url',
            'url',
            'job.primary_url',
            'job.url',
            'job.result.primary_url',
            'job.result.url',
            'result.primary_url',
            'result.url',
            'data.primary_url',
            'data.url',
        ] as $key) {
            $url = (string) data_get($response, $key, '');
            if ($url !== '') {
                return $url;
            }
        }

        foreach ([
            'job.result.variants',
            'job.result.renditions',
            'job.result.results',
            'job.result',
            'job.variants',
            'result.variants',
            'result.renditions',
            'result.results',
            'result',
            'variants',
            'job.renditions',
            'job.results',
            'renditions',
            'results',
            'data.job.result.variants',
            'data.job.result.renditions',
            'data.job.result.results',
            'data.job.result',
        ] as $key) {
            $url = $this->firstUrl(data_get($response, $key));
            if ($url !== '') {
                return $url;
            }
        }

        return '';
    }

    private function firstUrl(mixed $value): string
    {
        if (! is_array($value)) {
            return '';
        }

        if (isset($value['url']) && is_string($value['url']) && $value['url'] !== '') {
            return $value['url'];
        }

        foreach ($value as $child) {
            $url = $this->firstUrl($child);
            if ($url !== '') {
                return $url;
            }
        }

        return '';
    }

    private function variants(array $response): array
    {
        $variants = data_get($response, 'variants',
            data_get($response, 'job.variants',
                data_get($response, 'job.result.variants',
                    data_get($response, 'job.result.renditions',
                        data_get($response, 'result.variants',
                            data_get($response, 'result.renditions', [])
                        )
                    )
                )
            )
        );

        return is_array($variants) ? $variants : [];
    }

    private function responseStatus(array $response): string
    {
        return strtolower((string) data_get($response, 'status', data_get($response, 'job.status', 'processing')));
    }

    private function fallbackOnCompressorFailure(): bool
    {
        return filter_var(config('services.media_compressor.fallback_on_failure'), FILTER_VALIDATE_BOOLEAN);
    }

    private function markRemoteCompressorFailed(array $metadata, string $targetPath, string $message): void
    {
        $job = MediaCompressorJob::updateOrCreate(
            ['remote_job_id' => 'failed-local-'.$this->filemanager->id],
            [
                'user_id' => $this->filemanager->created_by,
                'owner_type' => Filemanager::class,
                'owner_id' => $this->filemanager->id,
                'media_type' => $this->fileType,
                'status' => 'failed',
                'primary_url' => null,
                'variants' => [],
                'payload' => array_replace_recursive($metadata, ['error' => $message]),
                'error_message' => $message,
                'completed_at' => now(),
            ]
        );

        if (Schema::hasColumn('filemanagers', 'remote_job_id')) {
            $this->filemanager->remote_job_id = $job->remote_job_id;
        }

        $this->filemanager->file_url = $targetPath;

        if (Schema::hasColumn('filemanagers', 'status')) {
            $this->filemanager->status = 'failed';
        }

        $this->filemanager->save();
    }

    private function cleanupTemporaryFiles(?string $processedPath = null, ?string $localSourcePath = null): void
    {
        if (Storage::exists($this->filePath)) {
            Storage::disk('local')->delete($this->filePath);
        }

        if (!empty($processedPath) && file_exists($processedPath) && $processedPath !== $localSourcePath) {
            @unlink($processedPath);
        }

        if ($this->originalName) {
            $originalTempPath = 'temp/uploads/' . $this->originalName;
            if (Storage::disk('local')->exists($originalTempPath)) {
                Storage::disk('local')->delete($originalTempPath);
            }
        }
    }

    private function tryRemoteCompressor(string $localSourcePath, string $targetPath): bool
    {
        if (! in_array($this->fileType, ['image', 'video'], true)) {
            return false;
        }

        /** @var MediaCompressorClient $client */
        $client = app(MediaCompressorClient::class);
        if (! $client->enabled()) {
            return false;
        }

        $metadata = [
            'source_app' => 'ezway_tv',
            'owner_type' => Filemanager::class,
            'owner_id' => $this->filemanager->id,
            'filemanager_id' => $this->filemanager->id,
            'file_name' => $this->filemanager->file_name,
            'original_name' => $this->originalName,
            'page_type' => $this->page_type,
            'file_type' => $this->fileType,
            'disk_type' => $this->diskType,
            'target_path' => $targetPath,
        ];

        try {
            $response = $this->fileType === 'image'
                ? $client->submitImage($localSourcePath, $this->filemanager->file_name, $metadata)
                : $client->submitVideo($localSourcePath, $this->filemanager->file_name, $metadata);
        } catch (\Throwable $exception) {
            $fallbackOnFailure = $this->fallbackOnCompressorFailure();

            Log::warning($fallbackOnFailure
                ? 'Remote media compressor upload failed, falling back to local upload'
                : 'Remote media compressor upload failed; local fallback disabled', [
                'file_name' => $this->filemanager->file_name,
                'error' => $exception->getMessage(),
                'fallback_on_failure' => $fallbackOnFailure,
            ]);

            if ($fallbackOnFailure) {
                return false;
            }

            $this->markRemoteCompressorFailed($metadata, $targetPath, $exception->getMessage());
            $this->cleanupTemporaryFiles(null, $localSourcePath);

            return true;
        }

        $remoteJobId = $this->remoteJobId($response);
        $status = $this->responseStatus($response);
        $primaryUrl = $this->primaryUrl($response);

        Log::info('Remote media compressor upload submitted', [
            'file_name' => $this->filemanager->file_name,
            'file_type' => $this->fileType,
            'remote_job_id' => $remoteJobId,
            'status' => $status,
            'has_primary_url' => $primaryUrl !== '',
        ]);

        if ($remoteJobId === '' && $primaryUrl === '') {
            Log::warning('Remote media compressor response did not include a job id or URL', [
                'file_name' => $this->filemanager->file_name,
                'response' => $response,
            ]);

            return false;
        }

        $job = MediaCompressorJob::updateOrCreate(
            ['remote_job_id' => $remoteJobId ?: 'inline-'.sha1($primaryUrl)],
            [
                'user_id' => $this->filemanager->created_by,
                'owner_type' => Filemanager::class,
                'owner_id' => $this->filemanager->id,
                'media_type' => $this->fileType,
                'status' => $primaryUrl !== '' ? 'completed' : $status,
                'primary_url' => $primaryUrl ?: null,
                'variants' => $this->variants($response),
                'payload' => array_replace_recursive($metadata, ['submit_response' => $response]),
                'completed_at' => $primaryUrl !== '' ? now() : null,
            ]
        );

        if (Schema::hasColumn('filemanagers', 'remote_job_id')) {
            $this->filemanager->remote_job_id = $job->remote_job_id;
        }
        $this->filemanager->file_url = $targetPath;
        if (Schema::hasColumn('filemanagers', 'status')) {
            $this->filemanager->status = $primaryUrl !== '' ? 'processing' : 'processing';
        }
        $this->filemanager->save();

        if ($this->fileType === 'image' && $primaryUrl === '' && $remoteJobId !== '') {
            $deadline = microtime(true) + (int) config('services.media_compressor.image_wait_seconds');
            while (microtime(true) < $deadline) {
                sleep(1);
                try {
                    $jobResponse = $client->job($remoteJobId);
                } catch (\Throwable $exception) {
                    Log::warning('Unable to poll media compressor image job', [
                        'remote_job_id' => $remoteJobId,
                        'error' => $exception->getMessage(),
                    ]);
                    break;
                }

                $primaryUrl = $this->primaryUrl($jobResponse);
                if ($primaryUrl !== '') {
                    $job->status = 'completed';
                    $job->primary_url = $primaryUrl;
                    $job->variants = $this->variants($jobResponse);
                    $job->payload = array_replace_recursive($job->payload ?? [], ['poll_response' => $jobResponse]);
                    $job->completed_at = now();
                    $job->save();
                    break;
                }
            }
        }

        if ($primaryUrl !== '') {
            app(FilemanagerCompressorApplier::class)->apply($job, $primaryUrl);
        }

        $this->cleanupTemporaryFiles(null, $localSourcePath);

        return true;
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        try {

            if (!Storage::exists($this->filePath)) {
                Log::info("File does not exist at path: {$this->filePath}");
                throw new \Exception("File does not exist at path: {$this->filePath}");
            }

            if($this->page_type == 'season' ) {
                $this->page_type = 'tvshow/season';
            }

            if($this->page_type == 'episode' ) {
                $this->page_type = 'tvshow/episode';
            }



            $localSourcePath = storage_path('app/' . $this->filePath);

            if (empty($this->filemanager->file_name)) {
                throw new \Exception('Filemanager file_name is empty, cannot determine upload path.');
            }

            $folderPath = $this->diskType === 'local'
                ? 'public/' . $this->page_type . '/'. $this->fileType . '/' . $this->filemanager->file_name
                : $this->page_type . '/' . $this->fileType . '/' . $this->filemanager->file_name;

            if ($this->tryRemoteCompressor($localSourcePath, $folderPath)) {
                return;
            }

            $processedPath = null;
            $compressEnabled = setting('media_compress_enable', env('MEDIA_COMPRESS_ENABLE', false));
            if ($compressEnabled && in_array($this->fileType, ['image', 'video'])) {
                if ($this->fileType === 'image') {
                    $quality = intval(setting('media_compress_image_quality', env('MEDIA_COMPRESS_IMAGE_QUALITY', 75)));
                    putenv('MEDIA_COMPRESS_IMAGE_QUALITY=' . $quality);
                    $processedPath = $this->compressImage($localSourcePath);
                } else {
                    $crf = intval(setting('media_compress_video_crf', env('MEDIA_COMPRESS_VIDEO_CRF', 23)));
                    $preset = setting('media_compress_video_preset', env('MEDIA_COMPRESS_VIDEO_PRESET', 'slow'));
                    $audioBitrate = setting('media_compress_audio_bitrate', env('MEDIA_COMPRESS_AUDIO_BITRATE', '128k'));
                    putenv('MEDIA_COMPRESS_VIDEO_CRF=' . $crf);
                    putenv('MEDIA_COMPRESS_VIDEO_PRESET=' . $preset);
                    putenv('MEDIA_COMPRESS_AUDIO_BITRATE=' . $audioBitrate);
                    $processedPath = $this->compressVideo($localSourcePath);
                }
            }

            $fileToStreamPath = $processedPath ?: $localSourcePath;

            if ($this->diskType === 'local') {
                $file = fopen($fileToStreamPath, 'rb');

                $directoryPath = 'public/' . $this->page_type . '/' . $this->fileType;

                if(!Storage::disk('local')->exists($directoryPath)) {
                    Log::info("Directory does not exist at path: {$directoryPath}");
                    $absoluteDirectoryPath = storage_path('app/' . $directoryPath);
                    File::makeDirectory($absoluteDirectoryPath, 0775, true, true);
                }

                Storage::disk('local')->writeStream($folderPath, $file);
                if (is_resource($file)) {
                    fclose($file);
                }

                $fullPath = storage_path('app/' . $folderPath);
                if (file_exists($fullPath)) {
                    chmod($fullPath, 0664);

                    $dirPath = dirname($fullPath);
                    if (is_dir($dirPath)) {
                        chmod($dirPath, 0775);
                    }
                }
            } else {
                if (in_array($this->diskType, ['dg-ocean', 's3'], true) && file_exists($fileToStreamPath)) {
                    $this->uploadS3CompatibleFile($fileToStreamPath, $folderPath, $this->diskType);
                } else {
                    $file = fopen($fileToStreamPath, 'rb');
                    Storage::disk($this->diskType)->writeStream($folderPath, $file);
                    if (is_resource($file)) {
                        fclose($file);
                    }
                }
            }

            $this->filemanager->file_url = $folderPath;
            if (Schema::hasColumn('filemanagers', 'status')) {
                $this->filemanager->status = 'ready';
            }
            $this->filemanager->save();

            $this->cleanupTemporaryFiles($processedPath, $localSourcePath);

            Artisan::call('config:clear');
            Artisan::call('cache:clear');
        } catch (\Exception $e) {

            Log::info("Error processing file upload: " . $e->getMessage());
            try {
                if (Schema::hasColumn('filemanagers', 'status')) {
                    $this->filemanager->status = 'failed';
                    $this->filemanager->save();
                }
            } catch (\Throwable $statusException) {
                Log::warning("Unable to mark file upload as failed: " . $statusException->getMessage());
            }

            throw $e;
        }
    }


}
