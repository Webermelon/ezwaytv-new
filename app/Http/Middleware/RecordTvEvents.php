<?php

namespace App\Http\Middleware;

use App\Services\TvLog;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class RecordTvEvents
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        if (!config('tv-logs.enabled')) {
            return $response;
        }

        try {
            $route = $request->route();
            $name = $route?->getName();
            $uri = $route?->uri();
            $status = $response->getStatusCode();

            if ($request->is('app/*') && !$request->isMethodSafe()) {
                $actor = $request->user();
                if ($actor) {
                    $params = $route?->parameters() ?? [];
                    $targetId = null;
                    foreach ($params as $value) {
                        if ($value instanceof \Illuminate\Database\Eloquent\Model) {
                            $value = $value->getKey();
                        }
                        if (is_int($value) || (is_string($value) && ctype_digit($value))) {
                            $targetId = $value;
                            break;
                        }
                    }
                    $result = $response instanceof JsonResponse ? json_decode($response->getContent(), true) : [];
                    $rejected = is_array($result) && (($result['success'] ?? null) === false || ($result['status'] ?? null) === false);
                    TvLog::record('admin_audit_events', 'admin.request', [
                        'actor_id' => $actor->getAuthIdentifier(),
                        'actor_type' => 'staff',
                        'target_type' => TvLog::label(explode('/', $uri ?? 'app/admin')[1] ?? 'admin', 48) ?? 'admin',
                        'target_id' => $targetId,
                        'action' => TvLog::label($name, 64) ?? 'admin_request',
                        'outcome' => $status === 403 ? 'denied' : ($status >= 400 || $rejected ? 'failed' : ($status >= 300 ? 'redirected' : 'accepted')),
                        'details' => ['route' => $uri, 'method' => $request->method(), 'http_status' => $status],
                    ], $request);
                }
            }

            if (in_array($name, ['auth.spa-otp.send', 'auth.spa-otp.verify'], true)) {
                $sending = $name === 'auth.spa-otp.send';
                $payload = $response instanceof JsonResponse ? json_decode($response->getContent(), true) : [];
                $success = $status < 400 && ($payload['status'] ?? false) === true;
                $userId = !$sending && $success ? $request->user()?->getAuthIdentifier() : null;
                TvLog::auth($request,
                    $sending ? 'otp_request' : ($success ? 'login_succeeded' : 'login_failed'),
                    $success ? 'success' : ($status === 429 ? 'denied' : 'failed'),
                    $userId, $status === 429 ? 'rate_limited' : ($success ? 'completed' : 'otp_request_rejected'),
                    'email_otp');
            }

            if ($status === 403 || $status === 429) {
                TvLog::record('security_events', 'security.request_rejected', [
                    'rule_code' => $status === 429 ? 'rate_limited' : 'access_denied',
                    'action_taken' => 'rejected',
                    'outcome' => 'denied',
                    'route_name' => TvLog::label($name ?? $uri, 128),
                ], $request);
            }

            if ($status >= 500) {
                TvLog::record('system_events', 'system.http_error', [
                    'service' => 'tv',
                    'severity' => 'error',
                    'outcome' => 'failed',
                    'http_method' => $request->method(),
                    'route_template' => $uri,
                    'http_status' => $status,
                ], $request);
            }
        } catch (Throwable $exception) {
            error_log('TV request telemetry could not be prepared.');
        }

        return $response;
    }
}
