<?php

namespace App\Http\Controllers\Auth;

use App\Events\Auth\UserLoginSuccess;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\TvLog;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

final class BackofficeAdminLoginController extends Controller
{
    private const SESSION_KEY = 'tv_admin_backoffice_login';

    private const PUBLIC_KEY_PATH = '/etc/ezway-tv-backoffice.pub';

    private const HEADERS = [
        'Cache-Control' => 'no-store, private',
        'Referrer-Policy' => 'no-referrer',
    ];

    public function start(Request $request): RedirectResponse
    {
        $state = bin2hex(random_bytes(32));
        $request->session()->put(self::SESSION_KEY, [
            'state' => $state,
            'expires' => time() + 600,
        ]);

        return redirect()->away('https://ezwaybackoffice.com/tv-admin/authorize?'.http_build_query(['state' => $state]))
            ->withHeaders(self::HEADERS);
    }

    public function callback(Request $request): RedirectResponse
    {
        $token = $request->query('token');
        $key = is_readable(self::PUBLIC_KEY_PATH) ? trim(file_get_contents(self::PUBLIC_KEY_PATH)) : '';
        abort_unless(preg_match('/\A[a-f0-9]{64}\z/', $key), 503);
        abort_unless(is_string($token) && strlen($token) <= 4096
            && preg_match('/\A([A-Za-z0-9_-]+)\.([a-f0-9]{128})\z/', $token, $parts), 403);

        [, $payload, $signature] = $parts;
        abort_unless(sodium_crypto_sign_verify_detached(hex2bin($signature), $payload, hex2bin($key)), 403);

        $json = base64_decode(strtr($payload, '-_', '+/'), true);
        $claims = $json !== false ? json_decode($json, true) : null;
        $pending = $request->session()->get(self::SESSION_KEY);
        $now = time();

        abort_unless(is_array($claims) && is_array($pending)
            && ($claims['iss'] ?? null) === 'https://ezwaybackoffice.com'
            && ($claims['aud'] ?? null) === 'https://ezway.tv/admin'
            && is_string($claims['sub'] ?? null) && preg_match('/\A[1-9][0-9]{0,17}\z/', $claims['sub'])
            && is_int($claims['iat'] ?? null) && is_int($claims['exp'] ?? null)
            && $claims['iat'] <= $now + 5 && $claims['exp'] > $now && $claims['exp'] - $claims['iat'] === 60
            && ($pending['expires'] ?? 0) > $now && is_string($claims['state'] ?? null)
            && hash_equals($pending['state'], $claims['state']), 403, 'Start a new Backoffice sign-in from TV Admin.');

        abort_unless(Cache::add('tv-admin:backoffice-login-used:'.hash('sha256', $claims['state']), true, 120), 403);
        $request->session()->forget(self::SESSION_KEY);

        abort_unless(!$request->user(), 403);
        try {
            $user = $this->backofficeAccount($claims['sub']);
        } catch (LockTimeoutException) {
            abort(503, 'TV Admin sign-in is busy. Please try again.');
        }
        if (!$this->eligible($user)) {
            TvLog::auth($request, 'login_failed', 'failed', null, 'tv_admin_account_disabled', 'admin_backoffice', 'backoffice');
            return redirect()->route('admin-login')
                ->withErrors(['backoffice' => 'TV Admin access for this Backoffice account is disabled.'])
                ->withHeaders(self::HEADERS);
        }

        $profile = $this->profile($claims['profile'] ?? null);
        Auth::guard('web')->login($user);
        $request->session()->regenerate();
        $request->session()->put('tv_admin_login_method', 'admin_backoffice');
        $request->session()->put('tv_admin_backoffice_profile', $profile);
        event(new UserLoginSuccess($request, $user));
        TvLog::auth($request, 'login_succeeded', 'success', $user->getAuthIdentifier(), 'session_issued', 'admin_backoffice', 'backoffice');

        return redirect()->route('backend.home')->withHeaders(self::HEADERS);
    }

    private function profile(?string $sealed): array
    {
        if ($sealed === null) {
            return ['name' => '', 'email' => ''];
        }

        abort_unless(preg_match('/\A[A-Za-z0-9_-]+\z/', $sealed), 403);
        $path = '/etc/ezway-tv-backoffice-profile.key';
        $keys = is_readable($path) ? file_get_contents($path) : '';
        abort_unless(is_string($keys) && strlen($keys) === SODIUM_CRYPTO_BOX_KEYPAIRBYTES, 503);
        $json = sodium_crypto_box_seal_open(
            sodium_base642bin($sealed, SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING),
            $keys,
        );
        $profile = is_string($json) ? json_decode($json, true) : null;
        abort_unless(is_array($profile) && is_string($profile['name'] ?? null) && is_string($profile['email'] ?? null), 403);

        return ['name' => $profile['name'], 'email' => $profile['email']];
    }

    private function backofficeAccount(string $subject): ?User
    {
        return Cache::lock('tv-admin:backoffice-account:'.$subject, 30)->block(3, function () use ($subject) {
            return DB::transaction(function () use ($subject) {
                $account = DB::table('tv_admin_backoffice_accounts')
                    ->where('backoffice_user_id', $subject)
                    ->first();
                if ($account) {
                    return User::find($account->tv_user_id);
                }

                $login = 'backoffice-'.Str::lower(Str::random(24));
                $user = User::create([
                    'name' => 'Backoffice Administrator',
                    'first_name' => 'Backoffice',
                    'last_name' => 'Admin '.$subject,
                    'username' => $login,
                    'email' => $login.'@login.invalid',
                    'password' => Hash::make(Str::random(64)),
                    'user_type' => 'admin',
                    'status' => 1,
                ]);
                $user->assignRole('admin');

                DB::table('tv_admin_backoffice_accounts')->insert([
                    'tv_user_id' => $user->getAuthIdentifier(),
                    'backoffice_user_id' => $subject,
                    'created_at' => now(),
                ]);

                return $user;
            });
        });
    }

    private function eligible(?User $user): bool
    {
        return $user && (int) $user->status === 1 && !(bool) $user->is_banned
            && $user->user_type !== 'demo_admin' && $user->hasBackendAccess() && !$user->hasRole('user');
    }
}
