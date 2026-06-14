# API Contract Audit

Date: 2026-06-09

## Purpose

This document tracks the existing Laravel API surface that the React + shadcn frontend will consume. The goal is to preserve existing API behavior while migrating UI modules one by one.

## Current Route Inventory

Generated with:

```bash
php artisan route:list --json --path=api
```

Current API route summary:

- Total API routes: 205
- Sanctum-protected routes: 90
- Public or non-Sanctum routes: 115
- v1 routes: 3
- v2 routes: 11
- v3 routes: 33

HTTP method distribution:

- `GET|HEAD`: 133
- `POST`: 53
- `DELETE`: 10
- `PUT`: 5
- `PUT|PATCH`: 4

Top API controllers by route count:

- `Modules\Entertainment\Http\Controllers\API\EntertainmentsController`: 22
- `App\Http\Controllers\Auth\API\AuthController`: 15
- `Modules\LiveTV\Http\Controllers\API\LiveTVsController`: 12
- `Modules\Video\Http\Controllers\API\CreatorChannelController`: 10
- `Modules\User\Http\Controllers\API\UserController`: 10
- `App\Http\Controllers\Backend\API\DashboardController`: 9
- `App\Http\Controllers\Backend\API\NotificationsController`: 8
- `Modules\Entertainment\Http\Controllers\API\WatchlistController`: 8
- `Modules\Frontend\Http\Controllers\PerviewPaymentController`: 8
- `App\Http\Controllers\Backend\SettingController`: 7
- `Modules\AuthorChannel\Http\Controllers\API\AuthorChannelAPIController`: 7
- `Modules\Frontend\Http\Controllers\DashboardController`: 7

## Current CLI/Boot Findings

Route discovery originally failed on two boot-time issues:

1. `routes/web.php` referenced legacy string controller routes for `Auth\LoginController`, but that controller does not exist.
2. `Modules\Subscriptions\Http\Controllers\Backend\SubscriptionController` assumed `$request->route()` was always available during construction.

Both have been fixed locally.

On 2026-06-09, MySQL was not accepting connections from the shell. During that state, `php artisan route:list --json --path=api` attempted to read `ChatGPT_key` from `settings` through `isenablemodule()`. The helper now fails closed to `0` and logs the connection problem instead of crashing route discovery.

## Frontend API Client

Added a shared React API client:

- `resources/react/lib/api.ts`

Initial behavior:

- JSON request/response handling
- same-origin credentials
- optional Bearer token support
- typed `get` and `post` helpers
- `ApiError` with status and payload

This is intentionally small. We should extend it only when a migrated module needs the behavior.

## First Pilot Endpoint

Pilot module:

- Genres

Endpoint:

- `GET /api/genre-list`

React pilot:

- `resources/react/modules/genres/GenresPilot.tsx`

Behavior:

- Calls the existing API endpoint.
- Normalizes common response shapes such as `data`, `genres`, `items`, or `results`.
- Shows loading, empty, success, and error states.
- Does not replace the current production Genres page yet.

Live verification on 2026-06-09:

- `GET http://127.0.0.1:8008/api/genre-list` returned HTTP 200.
- Response shape:

```json
{
  "status": true,
  "data": [
    {
      "id": 19,
      "name": "Crime Thriller",
      "poster_image": "http://127.0.0.1:8008/default-image/Default-Image.jpg",
      "status": 1
    }
  ],
  "message": "Genres List"
}
```

The React pilot normalization supports this shape through the `data` array.

## API Migration Rules

For every React module:

1. Use existing API endpoints where possible.
2. Avoid changing response shapes unless old clients are confirmed unaffected.
3. If a new API shape is needed, prefer adding a versioned endpoint instead of mutating an old one.
4. Preserve Sanctum and device middleware behavior.
5. Keep auth-required pages behind route guards in React.
6. Add frontend normalization at the boundary, not scattered across components.
7. Add targeted backend tests for high-risk endpoints.

## Module Mapping Backlog

Recommended API mapping order:

1. Auth and profile
2. Genres pilot
3. Home/dashboard
4. Movie list/detail
5. TV show list/detail
6. Video list/detail
7. Live TV dashboard/detail
8. Watchlist and continue watching
9. Subscriptions and pay-per-view
10. Notifications
11. Admin CRUD modules
12. Settings

## Known Risks

- Some API names have spelling mistakes that must be preserved for compatibility, such as `cancle-subscription` and `user-subscription_histroy`.
- Some API routes are closures, which makes contract tracing harder.
- Several endpoints are public but may still rely on settings or module flags from the database.
- MySQL downtime can still break endpoints that need real data, even though route discovery now survives it.
- Existing API responses may not be consistent across modules.
