<?php

namespace App\Services\MediaCompressor;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class MediaCompressorClient
{
    public function enabled(): bool
    {
        $configEnabled = filter_var(config('services.media_compressor.enabled'), FILTER_VALIDATE_BOOLEAN);

        return $configEnabled
            && $this->settingEnabled('media_compressor_api_enabled', $configEnabled)
            && (string) config('services.media_compressor.url') !== ''
            && (string) config('services.media_compressor.token') !== '';
    }

    private function settingEnabled(string $key, bool $default): bool
    {
        try {
            $value = DB::table('settings')
                ->where('name', $key)
                ->latest('updated_at')
                ->value('val');

            if ($value !== null) {
                return filter_var($value, FILTER_VALIDATE_BOOLEAN);
            }
        } catch (\Throwable $exception) {
            // Fall back to the normal setting helper when the settings table is not available.
        }

        if (! function_exists('setting')) {
            return $default;
        }

        $value = setting($key, $default ? '1' : '0');

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    public function submitImage(string $path, string $fileName, array $metadata = []): array
    {
        return $this->attachFile($this->request(), 'file', $path, $fileName)
            ->post($this->url((string) config('services.media_compressor.paths.images')), $this->payload($metadata))
            ->throw()
            ->json() ?? [];
    }

    public function submitVideo(string $path, string $fileName, array $metadata = []): array
    {
        $threshold = (int) config('services.media_compressor.video_direct_upload_threshold');
        $chunkUploadEnabled = filter_var(config('services.media_compressor.video_remote_chunk_upload_enabled'), FILTER_VALIDATE_BOOLEAN);

        if ($chunkUploadEnabled && $threshold > 0 && filesize($path) > $threshold) {
            return $this->submitLargeVideo($path, $fileName, $metadata);
        }

        return $this->attachFile($this->request(), 'file', $path, $fileName)
            ->post($this->url((string) config('services.media_compressor.paths.videos')), $this->payload($metadata))
            ->throw()
            ->json() ?? [];
    }

    public function job(string $jobId): array
    {
        return $this->request()
            ->get($this->url(str_replace('{job}', rawurlencode($jobId), (string) config('services.media_compressor.paths.job'))))
            ->throw()
            ->json() ?? [];
    }

    private function submitLargeVideo(string $path, string $fileName, array $metadata): array
    {
        $totalSize = filesize($path);
        $originalName = (string) data_get($metadata, 'original_name', $fileName);
        $targetPath = (string) data_get($metadata, 'target_path', '');

        $init = $this->request()
            ->post($this->url((string) config('services.media_compressor.paths.video_upload_init')), $this->payload($metadata + [
                'filename' => $fileName,
                'original_name' => $originalName,
                'size' => $totalSize,
                'total_size' => $totalSize,
                'target_path' => $targetPath,
                'content_type' => mime_content_type($path) ?: 'application/octet-stream',
            ]))
            ->throw()
            ->json() ?? [];

        $uploadId = (string) data_get($init, 'upload_id', data_get($init, 'id', data_get($init, 'upload.id', '')));
        $chunkEndpoint = (string) data_get($init, 'endpoints.chunk',
            data_get($init, 'upload.endpoints.chunk',
                data_get($init, 'data.endpoints.chunk',
                    data_get($init, 'chunk_endpoint', '')
                )
            )
        );
        $completeEndpoint = (string) data_get($init, 'endpoints.complete',
            data_get($init, 'upload.endpoints.complete',
                data_get($init, 'data.endpoints.complete',
                    data_get($init, 'complete_endpoint', '')
                )
            )
        );

        if ($uploadId === '' && ($chunkEndpoint === '' || $completeEndpoint === '')) {
            return $init;
        }

        $chunkSize = max(1024 * 1024, (int) config('services.media_compressor.video_chunk_size'));
        $totalChunks = (int) ceil($totalSize / $chunkSize);
        $handle = fopen($path, 'rb');

        try {
            for ($index = 0; $index < $totalChunks; $index++) {
                $chunk = fread($handle, $chunkSize);
                $tmp = tmpfile();
                fwrite($tmp, $chunk);
                $meta = stream_get_meta_data($tmp);

                $chunkPath = $chunkEndpoint !== ''
                    ? $chunkEndpoint
                    : str_replace('{upload}', rawurlencode($uploadId), (string) config('services.media_compressor.paths.video_upload_chunk'));

                $this->attachFile($this->request(), 'chunk', $meta['uri'], $fileName.'.part'.$index)
                    ->post($this->endpointUrl($chunkPath, ['upload' => $uploadId]), [
                        'index' => $index,
                        'chunk_index' => $index,
                        'total_chunks' => $totalChunks,
                        'original_name' => $originalName,
                        'target_path' => $targetPath,
                    ])
                    ->throw();

                fclose($tmp);
            }
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }
        }

        $completePath = $completeEndpoint !== ''
            ? $completeEndpoint
            : str_replace('{upload}', rawurlencode($uploadId), (string) config('services.media_compressor.paths.video_upload_complete'));

        return $this->request()
            ->post($this->endpointUrl($completePath, ['upload' => $uploadId]), $this->payload($metadata + [
                'upload_id' => $uploadId,
                'filename' => $fileName,
                'original_name' => $originalName,
                'total_chunks' => $totalChunks,
                'total_size' => $totalSize,
                'target_path' => $targetPath,
            ]))
            ->throw()
            ->json() ?? [];
    }

    private function request(): PendingRequest
    {
        $request = Http::withToken((string) config('services.media_compressor.token'))
            ->acceptJson()
            ->timeout((int) config('services.media_compressor.timeout'));

        $bucket = (string) config('services.media_compressor.bucket');
        if ($bucket !== '') {
            $request = $request->withHeaders(['X-Media-Bucket' => $bucket]);
        }

        return $request;
    }

    private function attachFile(PendingRequest $request, string $field, string $path, string $fileName): PendingRequest
    {
        return $request->attach($field, fopen($path, 'rb'), $fileName);
    }

    private function payload(array $metadata): array
    {
        $targetPath = (string) data_get($metadata, 'target_path', '');
        $fileType = (string) data_get($metadata, 'file_type', '');
        $pageType = (string) data_get($metadata, 'page_type', '');
        $fileName = (string) data_get($metadata, 'file_name', data_get($metadata, 'filename', ''));
        $originalName = (string) data_get($metadata, 'original_name', $fileName);
        $directory = $targetPath !== '' ? trim(dirname($targetPath), '.') : '';

        return array_filter([
            'bucket' => config('services.media_compressor.bucket'),
            'converter' => config('services.media_compressor.default_converter'),
            'webhook_url' => config('services.media_compressor.webhook_url'),
            'target_path' => $targetPath,
            'output_path' => $targetPath,
            'destination_path' => $targetPath,
            'directory' => $directory,
            'folder' => $directory,
            'file_type' => $fileType,
            'media_type' => $fileType,
            'page_type' => $pageType,
            'filename' => $fileName,
            'original_name' => $originalName,
            'size' => data_get($metadata, 'size'),
            'total_size' => data_get($metadata, 'total_size'),
            'content_type' => data_get($metadata, 'content_type'),
            'upload_id' => data_get($metadata, 'upload_id'),
            'total_chunks' => data_get($metadata, 'total_chunks'),
            'metadata' => $metadata,
        ], static fn ($value) => $value !== null && $value !== '');
    }

    private function url(string $path): string
    {
        return rtrim((string) config('services.media_compressor.url'), '/').'/'.ltrim($path, '/');
    }

    private function endpointUrl(string $endpoint, array $replacements = []): string
    {
        foreach ($replacements as $key => $value) {
            $endpoint = str_replace('{'.$key.'}', rawurlencode((string) $value), $endpoint);
        }

        if (preg_match('/^https?:\/\//i', $endpoint)) {
            return $endpoint;
        }

        return $this->url($endpoint);
    }
}
