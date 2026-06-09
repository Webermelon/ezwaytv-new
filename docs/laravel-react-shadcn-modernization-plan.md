# Laravel, React, and shadcn Modernization Plan

Date: 2026-06-08

## Goal

Modernize the eZWay TV codebase to the latest practical Laravel and React stack, redesign the frontend with shadcn/ui, and migrate the application module by module while preserving the existing database schema and existing API behavior.

## Non-Negotiables

- Do not alter existing database tables without explicit approval.
- Do not run pending migrations automatically.
- Keep existing APIs stable and use them from the new React frontend.
- Migrate module by module, not as a single full rewrite.
- Keep the current Blade/Vue pages working until each React replacement is verified.
- Prefer compatibility, rollback safety, and testability over rushing the visual rewrite.

## Current Read-Only Findings

- Application: eZWay TV
- Laravel runtime version: 12.33.0
- PHP version: 8.3.12
- Composer version: 2.8.10
- Database: MySQL 8.0.30
- Database name: ezwayott
- Database tables: 106
- Database size: about 10.22 MB
- Queue driver: database
- Session driver: file
- Current backend architecture: modular Laravel app using `Modules/`
- Current frontend stack: Laravel Mix, Vue 3, Bootstrap, Sass, jQuery, and related Vue packages
- Target frontend stack: Vite, React, TypeScript, Tailwind CSS, shadcn/ui
- Current route inventory blocker: `php artisan route:list` fails because `routes/web.php` references missing string controller routes for `Auth\LoginController`.

## Version Direction

- Laravel should be upgraded from 12.x to Laravel 13.x only after package compatibility is confirmed.
- React should be introduced as React 19.x unless compatibility testing shows a specific package requires React 18.
- Vite should replace Laravel Mix for new frontend builds.
- shadcn/ui should be installed into the project as owned source components, not treated as a black-box UI package.

Important: "latest" should be pinned to explicit versions during implementation. The project should not use floating dependency versions in production.

## Phase 1: Safety Baseline and DB Audit

1. Create a dedicated modernization branch.
2. Take a database backup before any dependency or frontend work.
3. Generate a read-only database inventory:
   - tables
   - columns
   - indexes
   - nullable fields
   - soft delete columns
   - inferred relationships from models and migrations
4. List pending migrations separately.
5. Do not run pending migrations unless approved.
6. Produce a schema reference document for frontend and API work.

Known pending migrations from the first scan include:

- `2026_06_03_000001_create_music_video_submissions_table`
- `2026_06_03_000002_add_channel_and_purchase_fields_to_music_video_submissions_table`
- `2026_06_03_000003_add_soft_deletes_to_music_video_submissions_table`
- `2026_06_08_000001_add_channel_id_to_statistics_events`

## Phase 2: API Contract Audit

1. Fix or isolate the `Auth\LoginController` route-list blocker.
2. Generate a full API route inventory from:
   - `routes/api.php`
   - module route files such as `Modules/*/Routes/api.php`
   - lowercase route folders such as `Modules/*/routes/api.php`
3. For each endpoint, document:
   - HTTP method
   - URI
   - controller method
   - middleware
   - auth requirement
   - request parameters/body
   - response resource/shape
   - frontend page or module that will consume it
4. Identify endpoints that are public, Sanctum-protected, admin-only, or TV-device-specific.
5. Add missing API tests only where risk is high or behavior is unclear.

## Phase 3: Laravel Upgrade

1. Run dependency compatibility checks before editing `composer.json`.
2. Check blockers for Laravel 13:
   - `nwidart/laravel-modules`
   - `nasirkhan/module-manager`
   - Laravel Sanctum
   - Spatie Permission
   - Spatie Media Library
   - Yajra DataTables
   - Livewire
   - payment SDKs
   - storage adapters
3. Update Composer constraints only when blockers are understood.
4. Run Composer update in a controlled pass.
5. Run verification:
   - `php artisan about`
   - `php artisan route:list`
   - `php artisan config:clear`
   - `php artisan test`
6. Fix Laravel 13 compatibility issues module by module.
7. Do not run migrations during the framework upgrade unless approved.

## Phase 4: React and shadcn Foundation

1. Add Vite to the Laravel app.
2. Add React and React DOM.
3. Add TypeScript.
4. Add Tailwind CSS.
5. Initialize shadcn/ui.
6. Add frontend aliases such as `@/components`, `@/lib`, and `@/modules`.
7. Create a React app shell that can be mounted from Blade.
8. Keep backend Blade layouts available during the transition.
9. Add shared frontend services:
   - API client
   - Sanctum token/session handling
   - request error handling
   - loading states
   - toast notifications
   - route guards
   - permission helpers

## Phase 4A: Full React SPA Target

The target customer frontend should become a single React SPA mounted by Laravel, while Laravel remains the backend/API/payment/media-access layer.

SPA route namespace during migration:

- `GET /spa`
- `GET /spa/{path}`

Production switch strategy:

1. Build and verify modules inside `/spa/*`.
2. Keep legacy frontend routes available while each module reaches parity.
3. Switch `/` to the SPA only after Home, navigation, auth/profile entry points, On Demand, Videos, Live TV, Movies/TV, search, subscription/payment entry points, and key static pages are verified.
4. Keep high-risk legacy routes as bridges until React parity exists:
   - video player/detail paths
   - payment and pay-per-view checkout
   - auth/session endpoints
   - admin backend routes
   - VAST/custom ad endpoints
5. Remove legacy Blade surfaces only after acceptance and rollback window.

SPA business rules:

- React owns navigation, browsing views, layout, filters, modals, loading states, and user-facing module pages.
- Laravel remains source of truth for database, APIs, auth/session, permissions, payment flows, media authorization, statistics, VAST/custom ads, and admin actions.
- Do not bypass ad, stat, subscription, pay-per-view, or On Demand context logic when replacing pages.

## Phase 5: Design System

Build a shadcn-based design system before migrating large pages.

Core UI:

- Button
- Input
- Select
- Textarea
- Dialog
- Sheet
- Dropdown menu
- Tabs
- Table
- Badge
- Avatar
- Toast
- Tooltip
- Form controls
- Date/time picker integration

OTT-specific UI:

- Media poster card
- Continue watching row
- Hero carousel
- On Demand channel card
- On Demand video rail
- Video detail layout
- TV show detail layout
- Episode list
- Live TV channel card
- VAST/custom ad-aware player shell
- Plan/subscription card
- Payment summary
- Watchlist controls
- Video player shell

Admin-specific UI:

- Admin sidebar
- Header/search bar
- Data table shell
- Filter bar
- Bulk action toolbar
- Status badges
- Create/edit form shell
- Delete/restore confirmation dialogs
- Settings panels

## Phase 6: Module-by-Module Migration Order

Recommended order:

1. Auth, layout, navigation, profile
2. Frontend home/dashboard and app configuration
3. On Demand channels and video rails
4. Movies, TV shows, seasons, episodes
5. Live TV, schedules, and channel detail
6. Watchlist, continue watching, likes, reviews, reminders
7. Subscriptions, invoices, pay-per-view, coupons
8. User/admin management
9. Settings, mobile settings, language, currency, tax
10. Ads, banners, VAST, custom ads, SEO
11. Pages, FAQ, onboarding
12. Cast/crew, genres, categories
13. File manager
14. Statistics and reporting
15. Author channels and creator/channel workflows

Each module should follow the same workflow:

1. Document current Blade/Vue behavior.
2. Identify API endpoints used by the module.
3. Add or stabilize missing API responses.
4. Build React module screens.
5. Reuse shadcn design system components.
6. Verify permissions and auth.
7. Compare behavior against the old module.
8. Switch the route to React.
9. Keep old code temporarily for rollback.
10. Remove old assets only after acceptance.

## Phase 7: Testing and Verification

Backend checks:

- `php artisan about`
- `php artisan route:list`
- `php artisan test`
- targeted feature tests for critical APIs
- permission checks for admin routes

Frontend checks:

- `npm run build`
- route smoke tests
- login/logout flow
- authenticated API flow
- mobile and desktop viewport checks
- media card rendering
- form validation
- table filtering and pagination

Business-critical smoke tests:

- login and registration
- profile update
- home/dashboard content load
- movie detail
- on-demand channel listing/profile
- on-demand video detail with `ondemand_channel` context
- VAST ad lookup for movie/video/tvshow/livetv playback
- custom ad lookup for detail pages
- TV show detail
- episode playback path
- live TV listing/detail
- watchlist add/remove
- subscription plan display
- invoice download
- pay-per-view listing
- admin dashboard
- user management
- settings save

## First Implementation Recommendation

Start with a small but important foundation sprint:

1. Fix the route-list blocker in `routes/web.php`.
2. Generate the DB schema audit document.
3. Generate the API contract document.
4. Check Laravel 13 dependency blockers.
5. Add Vite + React + shadcn foundation without replacing any production page yet.
6. Convert one low-risk module as the pilot.

Recommended pilot module:

- Genres, FAQ, or Pages if the goal is admin CRUD first.
- Watchlist or Profile if the goal is customer frontend first.

## Risks

- Route boot errors can hide additional route/API problems.
- Some packages may not support Laravel 13 yet.
- Existing APIs may return Blade-oriented or inconsistent response shapes.
- Some frontend behavior may currently depend on global scripts, jQuery, or Bootstrap plugins.
- Video playback, payment, and auth flows are high-risk and should be migrated later, after the foundation is stable.
- Pending migrations indicate code and database may already be partially out of sync; they must be reviewed carefully before any schema action.

## Decision Points

Before implementation begins, confirm:

1. Should the first React target be customer frontend or admin backend?
2. Should Laravel 13 upgrade happen before React work, or should React foundation start on Laravel 12 first?
3. Should pending migrations remain untouched for the whole redesign phase?
4. Should old Blade/Vue pages remain available behind fallback routes during rollout?

## Recommended Answer to Decision Points

Recommended path:

1. Fix route-list blocker.
2. Keep database unchanged.
3. Create DB and API audit docs.
4. Upgrade Laravel first if package compatibility is clean.
5. Add React + shadcn foundation.
6. Pilot one low-risk module.
7. Continue module by module after the pilot is accepted.
