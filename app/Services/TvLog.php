<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

final class TvLog
{
    public const CRITICAL = ['auth_events', 'security_events', 'admin_audit_events', 'system_events', 'operation_events', 'incident_events', 'creator_events'];

    public const ANALYTICS = ['viewer_events', 'playback_events', 'ad_events'];

    public static function record(string $table, string $event, array $fields, ?Request $request = null, ?string $eventId = null): void
    {
        if (!config('tv-logs.enabled')) {
            return;
        }

        $temporary = null;
        try {
            $directory = config('tv-logs.spool').'/'.(in_array($table, self::CRITICAL, true) ? 'critical' : 'analytics');
            $minimumFreeSpace = in_array($table, self::CRITICAL, true) ? 256 : 512;
            if (@disk_free_space($directory) < $minimumFreeSpace * 1024 * 1024) {
                throw new \RuntimeException('TV logging spool unavailable');
            }

            $id = $eventId ?? str_replace('-', '', (string) Str::uuid());
            $record = [
                'table' => $table,
                'row' => [
                    'event_id' => $id,
                    'event_name' => $event,
                    'source' => $request ? ($request->is('api/*') ? 'tv_api' : 'tv_web') : 'tv_console',
                    'occurred_at' => now('UTC')->format('Y-m-d H:i:s.v'),
                    'environment' => app()->environment(),
                    'request_id' => $request ? self::requestId($request) : null,
                ] + $fields,
            ];
            if (isset($record['row']['details'])) {
                $record['row']['details'] = json_encode($record['row']['details'], JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
            }
            $payload = json_encode($record, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
            if (strlen($payload) > 16384) {
                throw new \RuntimeException('TV logging event too large');
            }

            $temporary = $directory.'/.'.$id.'.'.bin2hex(random_bytes(4));
            if (@file_put_contents($temporary, $payload) !== strlen($payload)
                || !@rename($temporary, $directory.'/'.$id.'.json')) {
                throw new \RuntimeException('TV logging spool write failed');
            }
        } catch (Throwable $exception) {
            if ($temporary !== null) {
                @unlink($temporary);
            }
            error_log('TV logging could not enqueue '.$event.' ('.get_class($exception).').');
        }
    }

    public static function auth(
        Request $request, string $event, string $outcome, ?int $userId, string $reason, string $method,
        ?string $provider = null
    ): void {
        try {
            $login = $request->input('email');
            $key = config('app.key');
            $fingerprint = is_string($login) && strlen($login) <= 255 && is_string($key) && $key !== ''
                ? hash_hmac('sha256', 'tv-auth:'.strtolower(trim($login)), $key)
                : null;

            self::record('auth_events', 'auth.'.$event, [
                'tv_user_id' => $userId,
                'account_fingerprint' => $fingerprint,
                'auth_method' => $method,
                'provider' => $provider,
                'outcome' => $outcome,
                'reason_code' => $reason,
                'platform' => self::label($request->input('platform'), 32),
            ], $request);
        } catch (Throwable $exception) {
            error_log('TV logging could not prepare an authentication event.');
        }
    }

    public static function requestId(Request $request): string
    {
        if (!$request->attributes->has('tv_log_request_id')) {
            $request->attributes->set('tv_log_request_id', (string) Str::uuid());
        }

        return $request->attributes->get('tv_log_request_id');
    }

    public static function label(mixed $value, int $length): ?string
    {
        return is_string($value) && preg_match('/\A[a-zA-Z0-9_. :\/{}-]+\z/D', $value)
            ? substr($value, 0, $length)
            : null;
    }

    public static function legacyId(string $type, int $id): string
    {
        return substr(hash('sha256', 'ezway-tv:'.$type.':'.$id), 0, 32);
    }

    public static function exception(Throwable $exception): void
    {
        try {
            $origin = $exception->getFile().':'.$exception->getLine();
            $request = app()->runningInConsole() ? null : request();
            self::record('system_events', 'system.exception', [
                'service' => 'tv',
                'severity' => 'error',
                'outcome' => 'failed',
                'error_code' => substr(get_class($exception), 0, 96),
                'error_fingerprint' => hash('sha256', get_class($exception).':'.$origin),
                'route_template' => $request?->route()?->uri(),
                'http_method' => $request?->method(),
            ], $request);
        } catch (Throwable $loggingFailure) {
            error_log('TV logging could not prepare a system exception event.');
        }
    }
}
