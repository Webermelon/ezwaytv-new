<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyCorePrivateApi
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = (string) config('services.core_private_api.token');
        $secret = (string) config('services.core_private_api.secret');
        $provided = (string) $request->bearerToken();

        if ($token === '' || ! hash_equals($token, $provided)) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        if ($secret !== '') {
            $signature = (string) $request->header('X-Core-Signature');
            $expected = hash_hmac('sha256', $request->getContent(), $secret);

            if ($signature === '' || ! hash_equals($expected, $signature)) {
                return response()->json(['message' => 'Invalid signature.'], 401);
            }
        }

        return $next($request);
    }
}
