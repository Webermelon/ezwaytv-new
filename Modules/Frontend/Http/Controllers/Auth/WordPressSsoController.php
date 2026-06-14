<?php

namespace Modules\Frontend\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Modules\Frontend\Trait\LoginTrait;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\DB;

class WordPressSsoController extends Controller
{
    use LoginTrait;

    public function login(Request $request)
    {
        $token = (string) $request->query('token', '');

        if ($token === '') {
            return $this->redirectWithError('Missing SSO token.');
        }

        $payload = $this->decodeAndValidateToken($token);

        if (! $payload) {
            return $this->redirectWithError('Invalid or expired SSO token.');
        }

        $user = $this->findOrCreateUser($payload);

        Auth::login($user, true);
        $request->session()->regenerate();
        $this->setDevice($user, $request);

        return redirect()->intended('/');
    }

    private function decodeAndValidateToken(string $token): ?array
    {
        if (! str_contains($token, '.')) {
            return null;
        }

        [$encodedPayload, $signature] = explode('.', $token, 2);
        $secret = (string) config('services.wordpress_sso.secret', '');

        if ($secret === '') {
            return null;
        }

        $expectedSignature = hash_hmac('sha256', $encodedPayload, $secret);
        if (! hash_equals($expectedSignature, $signature)) {
            return null;
        }

        $decodedPayload = $this->base64UrlDecode($encodedPayload);
        if ($decodedPayload === null) {
            return null;
        }

        $payload = json_decode($decodedPayload, true);
        if (! is_array($payload)) {
            return null;
        }

        $email = filter_var((string) ($payload['email'] ?? ''), FILTER_VALIDATE_EMAIL);
        $expiry = (int) ($payload['exp'] ?? 0);
        $issuer = rtrim((string) ($payload['iss'] ?? ''), '/');
        $allowedIssuer = rtrim((string) config('services.wordpress_sso.issuer', ''), '/');

        if (! $email || $expiry < time()) {
            return null;
        }

        if ($allowedIssuer !== '' && $issuer !== '' && $issuer !== $allowedIssuer) {
            return null;
        }

        $payload['email'] = $email;
        $payload['name'] = $this->normalizeName((string) ($payload['name'] ?? ''));

        return $payload;
    }

    private function findOrCreateUser(array $payload): User
    {
        $email = $payload['email'];
        $fullName = $payload['name'] !== '' ? $payload['name'] : 'EZWay User';
        [$firstName, $lastName] = $this->splitName($fullName);

        $user = User::query()->firstOrNew(['email' => $email]);

        if (! $user->exists) {
            $user->fill([
                'first_name' => $firstName,
                'last_name' => $lastName,
                'username' => $this->buildUsername($email, $firstName, $lastName),
                'password' => Hash::make(Str::random(40)),
                'login_type' => 'wordpress_sso',
                'user_type' => 'user',
                'status' => 1,
                'email_verified_at' => now(),
                'is_network_user' => 1,
                'network_user_id' => isset($payload['user_id']) ? (int) $payload['user_id'] : null,
            ]);
            $user->save();
            $user->createOrUpdateProfileWithAvatar();
            try {
                if (method_exists($user, 'syncRoles')) {
                    $user->syncRoles(['user']);
                } else {
                    $this->assignUserRole($user);
                }
            } catch (\Throwable $e) {
                // ignore role sync errors
            }

            return $user;
        }

        $user->fill([
            'first_name' => $user->first_name ?: $firstName,
            'last_name' => $user->last_name ?: $lastName,
            'login_type' => $user->login_type ?: 'wordpress_sso',
            'user_type' => $user->user_type ?: 'user',
            'status' => $user->status ?: 1,
            'email_verified_at' => $user->email_verified_at ?: now(),
            'is_network_user' => 1,
            'network_user_id' => $user->network_user_id ?: (isset($payload['user_id']) ? (int) $payload['user_id'] : null),
        ]);
        $user->save();
        try {
            if (method_exists($user, 'syncRoles')) {
                $user->syncRoles(['user']);
            } else {
                $this->assignUserRole($user);
            }
        } catch (\Throwable $e) {
            // ignore role sync errors
        }

        return $user;
    }

    private function assignUserRole(User $user): void
    {
        if (! method_exists($user, 'assignRole')) {
            return;
        }

        $role = Role::query()->where('name', 'user')->first();
        if (! $role) {
            return;
        }

        $pivotExists = DB::table('model_has_roles')
            ->where('role_id', $role->id)
            ->where('model_id', $user->id)
            ->where('model_type', User::class)
            ->exists();

        if ($pivotExists) {
            return;
        }

        try {
            $user->assignRole($role->name);
        } catch (\Illuminate\Database\QueryException $e) {
            // race condition or duplicate entry — ignore safely
        }
    }

    private function splitName(string $name): array
    {
        $name = trim(preg_replace('/\s+/u', ' ', $name));

        if ($name === '') {
            return ['EZWay', 'User'];
        }

        $parts = explode(' ', $name, 2);

        return [
            $parts[0],
            $parts[1] ?? $parts[0],
        ];
    }

    private function buildUsername(string $email, string $firstName, string $lastName): string
    {
        $base = $firstName . $lastName;
        $base = preg_replace('/[^a-z0-9]+/i', '', $base);
        $base = strtolower($base ?: strstr($email, '@', true) ?: 'ezwayuser');

        return substr($base, 0, 40);
    }

    private function normalizeName(string $name): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', strip_tags($name)));
    }

    private function base64UrlDecode(string $value): ?string
    {
        $remainder = strlen($value) % 4;
        if ($remainder > 0) {
            $value .= str_repeat('=', 4 - $remainder);
        }

        $decoded = base64_decode(strtr($value, '-_', '+/'), true);

        return $decoded === false ? null : $decoded;
    }

    private function redirectWithError(string $message)
    {
        return redirect('/login')->with('error', $message);
    }
}
