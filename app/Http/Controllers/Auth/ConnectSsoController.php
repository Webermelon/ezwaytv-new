<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Exceptions\ConnectSsoAccountException;
use App\Services\ConnectSsoAccountService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Frontend\Trait\LoginTrait;

class ConnectSsoController extends Controller
{
    use LoginTrait;

    public function start(Request $request): RedirectResponse
    {
        $url = $this->connectUrl();
        abort_if($url === null || (string) config('connect_sso.secret') === '', 503, 'SSO is not configured.');

        $state = bin2hex(random_bytes(32));
        $request->session()->put('connect_sso', [
            'state' => $state,
            'destination' => $this->safeDestination($request->query('redirect_to')),
            'open_profile_editor' => $request->routeIs('connect-sso.profile-editor'),
            'expected_tv_user_id' => $request->routeIs('connect-sso.profile-editor') ? Auth::id() : null,
            'expires_at' => now()->addMinutes(30)->timestamp,
        ]);

        $query = [
            'client_id' => (string) config('connect_sso.client_id'),
            'state' => $state,
        ];
        if ($request->boolean('register')) {
            $query['register'] = '1';
        }

        return redirect()->away($url.'/portal/authorize?'.http_build_query($query));
    }

    public function callback(Request $request): RedirectResponse
    {
        $intent = $request->session()->pull('connect_sso');
        $state = $request->query('state');

        if (! is_array($intent)
            || (int) ($intent['expires_at'] ?? 0) <= now()->timestamp
            || ! is_string($state) || $state === ''
            || ! is_string($intent['state'] ?? null)
            || ! hash_equals($intent['state'], $state)) {
            return $this->failure();
        }

        if ($request->query('error') !== null) {
            return $this->failure($request->query('error') === 'access_denied' ? 'cancelled' : 'failed');
        }

        $code = $request->query('code');
        $secret = (string) config('connect_sso.secret');
        $url = $this->connectUrl();
        if (! is_string($code) || preg_match('/^[a-f0-9]{64}$/D', $code) !== 1 || $secret === '' || $url === null) {
            return $this->failure();
        }

        $body = json_encode([
            'client_id' => (string) config('connect_sso.client_id'),
            'code' => $code,
            'state' => $state,
        ], JSON_THROW_ON_ERROR);
        $timestamp = (string) now()->timestamp;
        $nonce = bin2hex(random_bytes(16));
        $signature = hash_hmac('sha256', $timestamp.'.'.$nonce.'.'.$body, $secret);

        try {
            // A consumed code must never be retried or forwarded to another host.
            $response = Http::acceptJson()
                ->timeout(max(1, (int) config('connect_sso.timeout', 10)))
                ->withoutRedirecting()
                ->withHeaders([
                    'X-Portal-Sso-Timestamp' => $timestamp,
                    'X-Portal-Sso-Nonce' => $nonce,
                    'X-Portal-Sso-Signature' => $signature,
                ])
                ->withBody($body, 'application/json')
                ->post($url.'/api/portal/sso/exchange');
        } catch (ConnectionException) {
            // Do not report request details: they contain the one-time code and signature.
            return $this->failure();
        }

        $identity = $response->json('data');
        $id = is_array($identity) ? ($identity['user_id'] ?? null) : null;
        $connectUserId = is_int($id) || is_string($id)
            ? filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
            : false;
        if (! $response->successful() || $connectUserId === false) {
            return $this->failure();
        }

        try {
            $user = app(ConnectSsoAccountService::class)->resolve($connectUserId, $identity);
        } catch (ConnectSsoAccountException $exception) {
            return $this->failure($exception->status);
        } catch (\Throwable $exception) {
            Log::error('Connect SSO account preparation failed.', ['exception_type' => get_class($exception)]);

            return $this->failure();
        }

        $expectedTvUserId = filter_var($intent['expected_tv_user_id'] ?? null, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);
        if ($expectedTvUserId !== false && (int) $user->id !== $expectedTvUserId) {
            return redirect('/update-profile?'.http_build_query(['connect_status' => 'identity_conflict']));
        }

        $request->session()->forget([
            'tv_pending_registration', 'tv_pending_core_login', 'tv_login_otp_email', 'tv_login_otp_expires_at',
        ]);
        Auth::guard('web')->login($user);
        $request->session()->regenerate();
        $this->setDevice($user, $request);

        if (($intent['open_profile_editor'] ?? false) === true) {
            return redirect()->away($this->profileEditorUrl());
        }

        return redirect()->to($this->safeDestination($intent['destination'] ?? null));
    }

    private function failure(string $status = 'failed'): RedirectResponse
    {
        return redirect('/login?'.http_build_query(['connect_status' => $status]));
    }

    private function connectUrl(): ?string
    {
        $url = (string) config('connect_sso.url');
        $parts = parse_url($url);
        if (! filter_var($url, FILTER_VALIDATE_URL) || ! is_array($parts)
            || ($parts['scheme'] ?? '') !== 'https' || empty($parts['host'])
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
            return null;
        }

        return rtrim($url, '/');
    }

    private function safeDestination(mixed $value): string
    {
        if (! is_string($value) || ! str_starts_with($value, '/')
            || str_starts_with($value, '//') || str_contains($value, '\\')
            || preg_match('/[\x00-\x20\x7f]/', $value)) {
            return '/';
        }

        return $value;
    }

    private function profileEditorUrl(): string
    {
        return rtrim((string) config('connect_sso.url'), '/').'/profile/edit';
    }
}
