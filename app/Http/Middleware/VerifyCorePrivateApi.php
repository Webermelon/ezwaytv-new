<?php

namespace App\Http\Middleware;

use App\Models\CoreApiKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyCorePrivateApi
{
    public function handle(Request $request, Closure $next): Response
    {
        $provided = (string) $request->bearerToken();

        if ($provided === '') {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        if ($this->matchesEnvToken($request, $provided)) {
            return $next($request);
        }

        $apiKey = CoreApiKey::query()
            ->where('token_hash', hash('sha256', $provided))
            ->whereNull('revoked_at')
            ->first();

        if (! $apiKey || ! $apiKey->is_active) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        $allowedIps = $apiKey->allowedIpList();
        if ($allowedIps !== [] && ! in_array($request->ip(), $allowedIps, true)) {
            return response()->json(['message' => 'IP address is not allowed for this API key.'], 401);
        }

        $secret = $apiKey->decryptedSecret();
        if ($secret !== null && $secret !== '') {
            $signature = (string) $request->header('X-Core-Signature');
            $expected = hash_hmac('sha256', $request->getContent(), $secret);

            if ($signature === '' || ! hash_equals($expected, $signature)) {
                return response()->json(['message' => 'Invalid signature.'], 401);
            }
        }

        $apiKey->markUsed();

        return $next($request);
    }

    private function matchesEnvToken(Request $request, string $provided): bool
    {
        $token = (string) config('services.core_private_api.token');
        $secret = (string) config('services.core_private_api.secret');

        if ($token === '' || ! hash_equals($token, $provided)) {
            return false;
        }

        if ($secret !== '') {
            $signature = (string) $request->header('X-Core-Signature');
            $expected = hash_hmac('sha256', $request->getContent(), $secret);

            if ($signature === '' || ! hash_equals($expected, $signature)) {
                return false;
            }
        }

        return true;
    }
}
