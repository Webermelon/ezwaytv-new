<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class VerifyVideoApiKey
{
    private const PLAYBACK_SOURCE_KEYS = [
        'video_url',
        'video_url_input',
        'bunny_video_url',
        'trailer_url',
        'download_url',
        'html',
    ];

    private const PLAYBACK_COLLECTION_KEYS = [
        'video_links',
        'download_quality',
        'clips',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('services.video_api.key');
        $provided = (string) ($request->bearerToken() ?: $request->header('X-API-Key', ''));

        if ($expected !== '' && $provided !== '' && hash_equals($expected, $provided)) {
            return $this->redactPlaybackSources($next($request));
        }

        if ($this->hasValidSiteSession($request)) {
            return $next($request);
        }

        return response()->json(['message' => 'Unauthorized.'], 401);
    }

    private function hasValidSiteSession(Request $request): bool
    {
        if (! $request->hasSession()) {
            return false;
        }

        $source = (string) ($request->header('Origin') ?: $request->header('Referer', ''));
        $sourceHost = parse_url($source, PHP_URL_HOST);

        if (! is_string($sourceHost) || ! hash_equals($request->getHost(), $sourceHost)) {
            return false;
        }

        $provided = (string) $request->header('X-CSRF-TOKEN', '');
        $expected = (string) $request->session()->token();

        return $provided !== '' && $expected !== '' && hash_equals($expected, $provided);
    }

    private function redactPlaybackSources(Response $response): Response
    {
        if (! $response instanceof JsonResponse) {
            return $response;
        }

        $payload = $response->getData(true);
        $response->setData($this->redactValue($payload));

        return $response;
    }

    private function redactValue(mixed $value, ?string $key = null): mixed
    {
        if ($key !== null && in_array($key, self::PLAYBACK_SOURCE_KEYS, true)) {
            return null;
        }

        if ($key !== null && in_array($key, self::PLAYBACK_COLLECTION_KEYS, true)) {
            return [];
        }

        if (! is_array($value)) {
            return $value;
        }

        foreach ($value as $childKey => $childValue) {
            $value[$childKey] = $this->redactValue(
                $childValue,
                is_string($childKey) ? $childKey : null
            );
        }

        return $value;
    }
}
