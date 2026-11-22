<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpKernel\Exception\HttpException;

/** Public navigation comes only from Core's existing network-platforms catalog. */
class PlatformLinks
{
    private ?array $resolved = null;
    public function all(): array
    {
        if ($this->resolved !== null) return $this->resolved;
        $identity = (string) config('platform-links.core_url').':'.(string) config('platform-links.core_host');
        $key = 'core-platform-links.v1.'.hash('sha256', $identity);
        $cached = Cache::get($key);
        if (is_array($cached)) return $this->resolved = $cached;

        try {
            $rows = $this->catalog();
            $links = [];
            foreach ($rows as $row) {
                if (($row['status'] ?? '') !== 'active' || ! is_string($row['slug'] ?? null)) continue;
                $url = $this->validUrl($row['url'] ?? null);
                if ($url !== null) $links[$row['slug']] = $url;
            }
            // Do not cache an unavailable catalog, or replace it with guessed domains.
            if ($links) Cache::put($key, $links, now()->addSeconds(60));
            return $this->resolved = $links;
        } catch (\Throwable $exception) {
            report($exception);
            return $this->resolved = [];
        }
    }

    protected function catalog(): array
    {
        $base = rtrim((string) config('platform-links.core_url'), '/');
        $token = (string) config('platform-links.core_token');
        if ($base === '' || $token === '') return [];
        $request = Http::acceptJson()->withToken($token)->connectTimeout(2)->timeout(6);
        if ($host = config('platform-links.core_host')) $request = $request->withHeaders(['Host' => $host]);
        $rows = [];
        for ($page = 1; $page <= 10; $page++) {
            $response = $request->get($base.'/api/network-platforms', ['status' => 'active', 'limit' => 100, 'page' => $page]);
            if (! $response->successful() || ! is_array($response->json('data'))) return [];
            $rows = array_merge($rows, $response->json('data'));
            if (! $response->json('meta.has_more', false)) break;
        }
        return $rows;
    }

    public function url(string $slug, string $path = ''): ?string
    {
        $base = $this->all()[$slug] ?? null;
        if ($base === null) return null;
        return $path === '' ? $base : $base.'/'.ltrim($path, '/');
    }

    public function required(string $slug, string $path = '', bool $secure = false): string
    {
        $url = $this->url($slug, $path);
        if ($url === null || ($secure && parse_url($url, PHP_URL_SCHEME) !== 'https')) {
            throw new HttpException(503, 'The '.$slug.' platform link is currently unavailable. Please try again.');
        }
        return $url;
    }

    private function validUrl(mixed $value): ?string
    {
        if (! is_string($value) || ! filter_var($value, FILTER_VALIDATE_URL)) return null;
        $parts = parse_url($value);
        if (! in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)
            || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])
            || isset($parts['query']) || isset($parts['fragment'])) return null;
        return rtrim($value, '/');
    }
}
