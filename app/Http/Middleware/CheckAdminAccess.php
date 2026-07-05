<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckAdminAccess
{
    private const ALWAYS_ALLOWED_ROUTE_NAMES = [
        'backend.home',
        'backend.daterange',
        'backend.setUserSetting',
        'backend.update-player-id',
        'backend.get_search_data',
        'backend.authData',
        'backend.my-profile',
        'backend.change_password',
        'language.switch',
        'check-in-trash',
        'admin-logout',
        'password.confirm',
    ];

    private const ADMIN_ONLY_ROUTE_PREFIXES = [
        'backend.permission-role.',
        'backend.permission.',
        'backend.role.',
        'backend.module.',
        'backend.backups.',
    ];

    private const PATH_PERMISSION_MODULES = [
        'app/media' => 'media',
        'app/on-demand-channels' => 'author_channels',
        'app/author-channels' => 'author_channels',
        'app/genres' => 'genres',
        'app/categories' => 'categories',
        'app/movies' => 'movies',
        'app/entertainments' => 'movies',
        'app/tvshows' => 'tvshows',
        'app/seasons' => 'seasons',
        'app/episodes' => 'episodes',
        'app/videos' => 'videos',
        'app/users-submission' => 'users_submission',
        'app/music-video-submissions' => 'users_submission',
        'app/livetvs' => 'livetv',
        'app/tv-category' => 'tvcategory',
        'app/tv-channel' => 'tvchannel',
        'app/castcrew' => 'castcrew',
        'app/vastads' => 'vastads',
        'app/video-ads' => 'ads',
        'app/customads' => 'customads',
        'app/adbannersides' => 'ads',
        'app/ads' => 'ads',
        'app/subscriptions' => 'subscriptions',
        'app/pay-per-view-history' => 'subscriptions',
        'app/plans' => 'plans',
        'app/planlimitation' => 'planlimitation',
        'app/coupon' => 'coupon',
        'app/users' => 'users',
        'app/soon-to-expire-users' => 'subscriptions',
        'app/reviews' => 'reviews',
        'app/banners' => 'banners',
        'app/constants' => 'constants',
        'app/mobile-setting' => 'dashboard_setting',
        'app/appconfig' => 'app_config',
        'app/notifications' => 'notification',
        'app/notification-templates' => 'notification_template',
        'app/email-logs' => 'email_logs',
        'app/core-api-keys' => 'core_api_keys',
        'app/settings' => 'setting',
        'app/setting' => 'setting',
        'app/clear-cache' => 'setting',
        'app/clear-cache-config' => 'setting',
        'app/dataload' => 'setting',
        'app/datareset' => 'setting',
        'app/pages' => 'page',
        'app/onboardings' => 'onboarding',
        'app/taxes' => 'taxes',
        'app/faqs' => 'faqs',
        'app/statistics/booster' => 'statistics_booster',
        'app/statistics' => 'statistics',
        'app/app-configuration' => 'app_config',
        'app/data-configuration' => 'app_config',
        'app/app/users' => 'subscriptions',
        'app/creator-channels' => 'author_channels',
        'app/currencies' => 'currency',
        'app/currencies_data' => 'currency',
        'app/country' => 'country',
        'app/state' => 'state',
        'app/city' => 'city',
        'app/worlds' => 'world',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->is('app') && !$request->is('app/*')) {
            return $next($request);
        }

        $user = $request->user();

        if (!$user) {
            return $next($request);
        }

        if ($user->hasRole('admin')) {
            return $next($request);
        }

        $routeName = $request->route()?->getName();

        if ($routeName && in_array($routeName, self::ALWAYS_ALLOWED_ROUTE_NAMES, true)) {
            return $next($request);
        }

        if ($routeName && $this->startsWithAny($routeName, self::ADMIN_ONLY_ROUTE_PREFIXES)) {
            abort(Response::HTTP_FORBIDDEN, __('messages.permission_denied'));
        }

        $module = $this->moduleForPath($request->path());

        if (!$module) {
            abort(Response::HTTP_FORBIDDEN, __('messages.permission_denied'));
        }

        if ($user->can($this->permissionName($request, $routeName, $module))) {
            return $next($request);
        }

        abort(Response::HTTP_FORBIDDEN, __('messages.permission_denied'));
    }

    private function moduleForPath(string $path): ?string
    {
        foreach (self::PATH_PERMISSION_MODULES as $prefix => $module) {
            if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
                return $module;
            }
        }

        return null;
    }

    private function permissionName(Request $request, ?string $routeName, string $module): string
    {
        $suffix = $routeName ? str($routeName)->afterLast('.')->toString() : '';
        $method = $request->method();

        if (str_contains($suffix, 'force')) {
            return 'force_delete_' . $module;
        }

        if (str_contains($suffix, 'restore')) {
            return 'restore_' . $module;
        }

        if (in_array($suffix, ['destroy', 'delete'], true) || $method === 'DELETE') {
            return 'delete_' . $module;
        }

        if (in_array($suffix, ['create', 'store', 'import'], true)) {
            return 'add_' . $module;
        }

        if (
            in_array($suffix, ['edit', 'update', 'update_status', 'bulk_action', 'assign', 'unassign', 'save'], true)
            || in_array($method, ['POST', 'PUT', 'PATCH'], true)
        ) {
            return 'edit_' . $module;
        }

        return 'view_' . $module;
    }

    private function startsWithAny(string $value, array $prefixes): bool
    {
        foreach ($prefixes as $prefix) {
            if (str_starts_with($value, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
