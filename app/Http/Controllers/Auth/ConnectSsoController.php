<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\CoreTvAccessService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Throwable;

class ConnectSsoController extends Controller
{
    private const INTENT_SESSION_KEY = 'connect_sso';

    public function __construct(private CoreTvAccessService $tvAccess)
    {
    }

    public function start(Request $request): RedirectResponse
    {
        $destination = $this->safeDestination((string) $request->query('redirect', '/'));

        if (Auth::check()) {
            return $this->noStoreRedirect($destination);
        }

        if (! $this->isConfigured()) {
            return $this->failureRedirect('failed', $destination);
        }

        try {
            $state = bin2hex(random_bytes(32));
        } catch (Throwable $exception) {
            report($exception);

            return $this->failureRedirect('failed', $destination);
        }

        $request->session()->put(self::INTENT_SESSION_KEY, [
            'state' => $state,
            'destination' => $destination,
            'expires_at' => time() + 1800,
        ]);

        $query = http_build_query([
            'client_id' => (string) config('connect.sso_client_id'),
            'state' => $state,
        ]);

        return redirect()->away(config('connect.url').'/portal/authorize?'.$query);
    }

    public function callback(Request $request): RedirectResponse
    {
        $intent = $request->session()->pull(self::INTENT_SESSION_KEY);
        $state = (string) $request->query('state', '');
        $destination = is_array($intent)
            ? $this->safeDestination((string) ($intent['destination'] ?? '/'))
            : '/';

        if (! is_array($intent)
            || (int) ($intent['expires_at'] ?? 0) < time()
            || preg_match('/^[a-f0-9]{64}$/', $state) !== 1
            || ! hash_equals((string) ($intent['state'] ?? ''), $state)) {
            return $this->failureRedirect('failed', $destination);
        }

        if ((string) $request->query('error') === 'access_denied') {
            return $this->failureRedirect('cancelled', $destination);
        }

        $code = (string) $request->query('code', '');
        if (preg_match('/^[a-f0-9]{64}$/', $code) !== 1 || ! $this->isConfigured()) {
            return $this->failureRedirect('failed', $destination);
        }

        try {
            $connectUser = $this->exchangeCode($code, $state);
            if (! is_array($connectUser)
                || empty($connectUser['id'])
                || ! filter_var($connectUser['email'] ?? null, FILTER_VALIDATE_EMAIL)) {
                return $this->failureRedirect('failed', $destination);
            }

            $user = $this->tvAccess->syncUser([
                'core_user_id' => (int) ($connectUser['network_id'] ?? 0),
                'connect_user_id' => (int) $connectUser['id'],
                'username' => (string) ($connectUser['username'] ?? ''),
                'email' => strtolower(trim((string) $connectUser['email'])),
                'first_name' => (string) ($connectUser['first_name'] ?? ''),
                'last_name' => (string) ($connectUser['last_name'] ?? ''),
            ], $request);

            if ((int) $user->is_banned === 1) {
                return $this->failureRedirect('failed', $destination);
            }

            if (! empty($connectUser['avatar']) && empty($user->file_url)) {
                $user->forceFill(['file_url' => (string) $connectUser['avatar']])->save();
            }

            $request->session()->regenerate();
            Auth::login($user);
            setCurrentProfileSession();
        } catch (ConnectionException $exception) {
            report($exception);

            return $this->failureRedirect('failed', $destination);
        } catch (Throwable $exception) {
            report($exception);

            return $this->failureRedirect('failed', $destination);
        }

        return $this->noStoreRedirect($destination);
    }

    private function exchangeCode(string $code, string $state): ?array
    {
        $body = json_encode([
            'client_id' => (string) config('connect.sso_client_id'),
            'code' => $code,
            'state' => $state,
        ], JSON_THROW_ON_ERROR);
        $timestamp = (string) time();
        $nonce = bin2hex(random_bytes(16));
        $signature = hash_hmac(
            'sha256',
            $timestamp.'.'.$nonce.'.'.$body,
            (string) config('connect.sso_secret')
        );

        $response = Http::acceptJson()
            ->connectTimeout(5)
            ->timeout(max(1, (int) config('connect.sso_timeout', 10)))
            ->withHeaders([
                'X-Portal-Sso-Timestamp' => $timestamp,
                'X-Portal-Sso-Nonce' => $nonce,
                'X-Portal-Sso-Signature' => $signature,
            ])
            ->withBody($body, 'application/json')
            ->post(config('connect.url').'/api/portal/sso/exchange');

        $user = $response->json('data');

        return $response->successful() && is_array($user) ? $user : null;
    }

    private function isConfigured(): bool
    {
        return (string) config('connect.url') !== ''
            && (string) config('connect.sso_client_id') !== ''
            && (string) config('connect.sso_secret') !== '';
    }

    private function safeDestination(string $destination): string
    {
        $destination = trim($destination);
        if ($destination === '' || ! str_starts_with($destination, '/') || str_starts_with($destination, '//')) {
            return '/';
        }

        $parts = parse_url($destination);
        $path = (string) ($parts['path'] ?? '');
        if ($parts === false
            || isset($parts['host'])
            || str_starts_with($path, '/login')
            || str_starts_with($path, '/auth/connect')) {
            return '/';
        }

        return $destination;
    }

    private function failureRedirect(string $status, string $destination): RedirectResponse
    {
        return $this->noStoreRedirect('/login?'.http_build_query([
            'connect_status' => $status,
            'redirect' => $this->safeDestination($destination),
        ]));
    }

    private function noStoreRedirect(string $destination): RedirectResponse
    {
        $response = redirect($destination);
        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('Referrer-Policy', 'no-referrer');

        return $response;
    }
}
