<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthorizeAppAnalyticsReport
{
    public function handle(Request $request, Closure $next): Response
    {
        $configuredToken = (string) config('services.tv_analytics.service_token');
        $providedToken = (string) $request->bearerToken();

        if (
            $configuredToken !== ''
            && $providedToken !== ''
            && hash_equals($configuredToken, $providedToken)
        ) {
            return $next($request);
        }

        $user = $request->user();

        if ($user && $user->hasBackendAccess()) {
            return $next($request);
        }

        return response()->json([
            'message' => 'Unauthenticated.',
        ], Response::HTTP_UNAUTHORIZED);
    }
}
