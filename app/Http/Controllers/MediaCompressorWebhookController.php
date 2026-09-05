<?php

namespace App\Http\Controllers;

use App\Models\MediaCompressorJob;
use App\Services\MediaCompressor\FilemanagerCompressorApplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MediaCompressorWebhookController extends Controller
{
    public function __invoke(Request $request, FilemanagerCompressorApplier $applier): JsonResponse
    {
        if (! $this->validSignature($request)) {
            return response()->json(['message' => 'Invalid signature.'], 401);
        }

        $payload = $request->json()->all();
        $event = (string) data_get($payload, 'event', data_get($payload, 'type', ''));
        $remoteJobId = $this->remoteJobId($payload);

        if ($event === 'webhook.test') {
            return response()->json(['ok' => true, 'event' => $event]);
        }

        if ($remoteJobId === '') {
            return response()->json(['message' => 'Missing job id.'], 422);
        }

        $job = MediaCompressorJob::where('remote_job_id', $remoteJobId)->first();
        if (! $job) {
            Log::warning('Unknown media compressor webhook job', ['remote_job_id' => $remoteJobId, 'event' => $event]);
            return response()->json(['ok' => true]);
        }

        $job->payload = array_replace_recursive($job->payload ?? [], ['webhook' => $payload]);

        if ($event === 'job.failed' || str_contains(strtolower((string) data_get($payload, 'status', '')), 'fail')) {
            $message = (string) data_get($payload, 'error.message', data_get($payload, 'error_message', 'Media compressor failed.'));
            $job->status = 'failed';
            $job->error_message = $message;
            $job->save();
            $applier->fail($job, $message);

            return response()->json(['ok' => true]);
        }

        if ($event === 'job.completed' || in_array(strtolower((string) data_get($payload, 'status', '')), ['completed', 'complete', 'ready', 'success'], true)) {
            $primaryUrl = $this->primaryUrl($payload);
            if ($primaryUrl === '') {
                $message = 'Media compressor completed without an output URL.';
                $job->status = 'failed';
                $job->error_message = $message;
                $job->save();
                $applier->fail($job, $message);

                return response()->json(['ok' => true, 'message' => $message]);
            }

            $job->status = 'completed';
            $job->primary_url = $primaryUrl;
            $job->variants = $this->variants($payload);
            $job->completed_at = now();
            $job->save();

            $applier->apply($job, $primaryUrl);

            return response()->json(['ok' => true]);
        }

        $job->status = (string) data_get($payload, 'status', $job->status ?: 'processing');
        $job->save();

        return response()->json(['ok' => true]);
    }

    private function validSignature(Request $request): bool
    {
        $secret = (string) config('services.media_compressor.webhook_secret');
        if ($secret === '') {
            return ! (bool) config('services.media_compressor.enabled');
        }

        $signature = (string) $request->header('X-Media-Signature', '');
        $signature = preg_replace('/^sha256=/i', '', trim($signature));
        $expected = hash_hmac('sha256', $request->getContent(), $secret);

        return $signature !== '' && hash_equals($expected, $signature);
    }

    private function remoteJobId(array $payload): string
    {
        return (string) data_get($payload, 'job.id',
            data_get($payload, 'job_id',
                data_get($payload, 'id',
                    data_get($payload, 'data.job.id', '')
                )
            )
        );
    }

    private function primaryUrl(array $payload): string
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
            $url = (string) data_get($payload, $key, '');
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
            $url = $this->firstUrl(data_get($payload, $key));
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

    private function variants(array $payload): array
    {
        $variants = data_get($payload, 'variants',
            data_get($payload, 'job.variants',
                data_get($payload, 'job.result.variants',
                    data_get($payload, 'job.result.renditions',
                        data_get($payload, 'result.variants',
                            data_get($payload, 'result.renditions', [])
                        )
                    )
                )
            )
        );

        return is_array($variants) ? $variants : [];
    }
}
