<?php

namespace App\Services;

use App\Exceptions\ConnectSsoAccountException;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class ConnectSsoAccountService
{
    public function resolve(int $connectUserId, array $identity): User
    {
        try {
            return DB::transaction(function () use ($connectUserId, $identity): User {
                $user = User::withTrashed()->where('connect_user_id', $connectUserId)->lockForUpdate()->first();
                if ($user) {
                    return $this->available($user);
                }

                $email = $this->text($identity['email'] ?? null);
                if (strlen($email) > 191 || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw new ConnectSsoAccountException('failed');
                }
                $email = strtolower($email);
                $user = $this->existingAccount($connectUserId, $email);
                if ($user) {
                    return $user;
                }

                [$firstName, $lastName] = $this->name($identity);
                $role = Role::findByName('user', 'web');
                $user = new User;
                $user->forceFill([
                    'connect_user_id' => $connectUserId,
                    'email' => $email,
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'username' => $this->username($identity, $connectUserId),
                    'password' => Hash::make(Str::random(64)),
                    'login_type' => 'connect_sso',
                    'user_type' => 'user',
                    'status' => 1,
                    'is_banned' => 0,
                    'is_subscribe' => 0,
                ])->save();
                $user->assignRole($role);
                $user->createOrUpdateProfileWithAvatar();
                DB::afterCommit(fn () => Cache::forget('roles_cache'));

                return $user;
            }, 3);
        } catch (UniqueConstraintViolationException $exception) {
            // Re-read after a concurrent SSO or Core signup commits its account.
            return DB::transaction(function () use ($connectUserId, $identity, $exception): User {
                $user = User::withTrashed()->where('connect_user_id', $connectUserId)->lockForUpdate()->first();
                if ($user) {
                    return $this->available($user);
                }
                $user = $this->existingAccount($connectUserId, strtolower($this->text($identity['email'] ?? null)));
                if ($user) {
                    return $user;
                }

                throw $exception;
            }, 3);
        }
    }

    private function existingAccount(int $connectUserId, string $email): ?User
    {
        // Only the email from the authenticated server-to-server Connect exchange is trusted.
        $matches = User::withTrashed()->whereRaw('LOWER(email) = ?', [$email])->lockForUpdate()->get();
        if ($matches->isEmpty()) {
            return null;
        }
        if ($matches->count() !== 1) {
            throw new ConnectSsoAccountException('identity_conflict');
        }
        $user = $this->available($matches->first());
        if ($user->connect_user_id !== null && (int) $user->connect_user_id !== $connectUserId) {
            throw new ConnectSsoAccountException('identity_conflict');
        }
        if ($user->connect_user_id === null) {
            $user->forceFill(['connect_user_id' => $connectUserId])->save();
        }

        return $user;
    }

    private function available(User $user): User
    {
        if ($user->trashed() || (int) $user->status !== 1 || (int) $user->is_banned !== 0) {
            throw new ConnectSsoAccountException('disabled');
        }

        return $user;
    }

    private function text(mixed $value): string
    {
        return is_string($value) ? trim($value) : '';
    }

    private function name(array $identity): array
    {
        $parts = explode(' ', preg_replace('/\s+/u', ' ', $this->text($identity['name'] ?? null)) ?? '', 2);

        return [
            Str::substr($this->text($identity['first_name'] ?? null) ?: ($parts[0] ?: 'eZWay'), 0, 191),
            Str::substr($this->text($identity['last_name'] ?? null) ?: ($parts[1] ?? ''), 0, 191),
        ];
    }

    private function username(array $identity, int $connectUserId): string
    {
        $username = strtolower(preg_replace('/[^A-Za-z0-9_.-]/', '', $this->text($identity['username'] ?? null)) ?? '');
        $username = Str::substr($username ?: 'connect-'.$connectUserId, 0, 80);
        while (User::withTrashed()->where('username', $username)->exists()) {
            $username = 'connect-'.$connectUserId.'-'.Str::lower(Str::random(8));
        }

        return $username;
    }
}
