# Modernization Working Log

## 2026-06-08

### Backup

- Created a full sibling backup before source changes:
  - `C:\laragon\www\ezwaytv\public_html_backup_20260608-173957`

### Source Fixes

- Fixed Laravel route discovery by removing stale `Auth\LoginController` string routes from `routes/web.php`.
- Fixed route discovery by making `Modules\Subscriptions\Http\Controllers\Backend\SubscriptionController` tolerate a null route during Artisan route inspection.

### Verification

- `php artisan route:list --path=api` passes.
  - Current API route count: 205
- `php artisan route:list` passes.
  - Current full route count: 1124
- `php artisan about` passes.
  - Laravel: 12.33.0
  - PHP: 8.3.12
  - Database: MySQL
- `npm run react:build` passes.

### React + shadcn Foundation

- Added React 19 runtime packages.
- Added Vite, TypeScript, Tailwind CSS v4, and Tailwind's Vite integration.
- Added shadcn-compatible project structure:
  - `components.json`
  - `resources/react/lib/utils.ts`
  - `resources/react/components/ui/button.tsx`
- Added React entry files:
  - `resources/react/main.tsx`
  - `resources/react/App.tsx`
  - `resources/react/styles.css`
- Added Vite config:
  - `vite.config.ts`
- Added a local-only preview route:
  - `GET /react-modernization`
- Added preview Blade mount:
  - `resources/views/react-modernization.blade.php`

### Preview

- `http://ezway.tv/react-modernization` was not reachable from the shell.
- Started a local Artisan server on:
  - `http://127.0.0.1:8008`
- Verified the preview route returns HTTP 200:
  - `http://127.0.0.1:8008/react-modernization`

### Laravel 13 Blockers

Composer dependency resolution currently blocks a clean Laravel 13 upgrade. Packages requiring Illuminate/Laravel 12 or lower include:

- `bangnokia/laravel-bunny-storage`
- `barryvdh/laravel-dompdf`
- `kreait/laravel-firebase`
- `laracasts/flash`
- `laravel/sanctum`
- `laravel/socialite`
- `laravel/tinker`
- `livewire/livewire`
- `maatwebsite/excel`
- `mews/purifier`
- `propaganistas/laravel-phone`
- `spatie/laravel-activitylog`
- `spatie/laravel-html`
- `spatie/laravel-medialibrary`
- `spatie/laravel-package-tools`
- `spatie/laravel-permission`
- `spatie/laravel-webhook-client`
- `spatie/laravel-webhook-server`
- `yajra/laravel-datatables-oracle`
- `laravel/breeze`
- `laravel/telescope`
- `spatie/laravel-ignition`

Recommendation: continue React/Vite/shadcn foundation work on Laravel 12 first, then handle Laravel 13 as a separate package-compatibility pass.

### Notes

- No database migrations were run.
- No database tables were altered.
- No code was committed or pushed.
- npm reports existing dependency audit warnings. These were not auto-fixed because `npm audit fix` may introduce broad dependency changes.

## 2026-06-09

### Resilience Fix

- MySQL was not accepting connections from the shell.
- `php artisan route:list --json --path=api` was blocked by a boot-time `settings` query for `ChatGPT_key`.
- Updated `isenablemodule()` in `app/helpers.php` to fail closed to `0` and log the error when settings cannot be read.
- Confirmed API route JSON works again while MySQL is down.

### API Audit

- Added `docs/api-contract-audit.md`.
- Captured API route counts and migration rules.
- Current API route count remains 205.

### Database Audit

- Added `docs/database-audit.md`.
- Live schema refresh is currently blocked because MySQL is not accepting connections.
- Reused the read-only database facts captured on 2026-06-08.
- Pending migrations are documented as review items only.

### React Pilot

- Added shared React API client:
  - `resources/react/lib/api.ts`
- Added first API-connected pilot module:
  - `resources/react/modules/genres/GenresPilot.tsx`
- Updated the React modernization preview to include the Genres pilot panel.
- `npm run react:build` passes.

### MySQL Back Online

- Confirmed `php artisan db:show` works.
- Confirmed `php artisan migrate:status` works.
- Confirmed `php artisan db:table users` works.
- Refreshed DB audit with live table snapshots for:
  - `genres`
  - `entertainments`
  - `videos`
  - `episodes`
  - `live_tv_channel`
  - `subscriptions`
  - `pay_per_views`
  - `pages`
- Restarted preview server at `http://127.0.0.1:8008`.
- Verified `GET /api/genre-list` returns HTTP 200 and real genre records.

### Preview UI Upgrade

- Added shadcn-style Badge component:
  - `resources/react/components/ui/badge.tsx`
- Added migration control panel:
  - `resources/react/components/MigrationDashboard.tsx`
- Improved Genres pilot cards with:
  - poster thumbnails
  - active/inactive badge
  - record ID fallback
- Rebuilt React assets successfully.
- Verified:
  - `GET /react-modernization` returns HTTP 200.
  - `GET /api/genre-list` returns HTTP 200.
  - The API response includes real genre data.

### Netflix-Style OTT Direction

- Updated React preview styling toward a Netflix-like OTT direction:
  - dark cinematic canvas
  - red primary accent
  - branded top navigation
  - large hero copy
  - content rail treatment for Genres
  - poster-forward cards with hover emphasis
- Updated:
  - `resources/react/App.tsx`
  - `resources/react/styles.css`
  - `resources/react/modules/genres/GenresPilot.tsx`
  - `resources/react/components/MigrationDashboard.tsx`
- `npm run react:build` passes.
- `GET /react-modernization` returns HTTP 200.

### Frontend-Only Replacement Start

- User confirmed the backend should stay intact and only the frontend should be replaced, module by module.
- Added the first parallel React replacement route:
  - `GET /react-home`
- The legacy Blade homepage at `/` is not switched yet.
- Added API-connected React home module:
  - `resources/react/modules/home/HomePage.tsx`
  - `resources/react/modules/home/homeApi.ts`
  - `resources/react/modules/home/types.ts`
- API sampling for the home module:
  - `GET /api/v3/dashboard-detail`: 200; current data includes language, empty movie rails.
  - `GET /api/genre-list`: 200; genre rail available.
  - `GET /api/v3/video-list?is_ajax=1&per_page=14`: 200; video rail available.
  - `GET /api/v3/livetv-dashboard`: 200; live TV hero/channel data available.
  - `GET /api/v3/dashboard-detail-data`: 500 locally, so the React home module does not depend on it.
- No migrations were run and no database tables were altered.

### On Demand and VAST Requirements

- User noted On Demand Video and VAST ads must keep working during the frontend replacement.
- Confirmed On Demand web routes:
  - `GET /on-demand`
  - `GET /on-demand/{username}`
- Confirmed On Demand public APIs:
  - `GET /api/v3/ondemand`
  - `GET /api/v3/ondemand/{username}`
  - `GET /api/v3/ondemand/{username}/videos`
- Confirmed VAST API:
  - `GET /api/vast-ads/get-active`
- Sample checks:
  - `GET /api/v3/ondemand?per_page=8`: 200 with active channel data.
  - `GET /api/vast-ads/get-active?type=video&content_id=264`: 200 with an empty list, meaning endpoint is healthy but no matching active ad was returned locally.
- Important migration rule:
  - React video links must preserve `ondemand_channel` when a video belongs to an On Demand channel.
  - React playback screens must preserve existing player metadata: `content_type`, `content_id`, `content-video-type`, `video_type`, and stat channel id where applicable.
  - VAST/custom ad calls must continue to use existing backend endpoints; do not bypass the ad system in React playback modules.

### React On Demand Module

- Added parallel local React route:
  - `GET /react-ondemand/{username?}`
- Added React On Demand files:
  - `resources/react/modules/ondemand/OnDemandPage.tsx`
  - `resources/react/modules/ondemand/ondemandApi.ts`
- Updated the React app shell to render the On Demand module for `/react-ondemand`.
- Updated the React home On Demand rail to open React On Demand profiles instead of replacing the legacy `/on-demand` route.
- React On Demand videos still link to legacy playback:
  - `/video-details/{slug}?autoplay=1&ondemand_channel={channelId}`
- This intentionally preserves the existing video player, statistics, custom ads, and VAST behavior during the browsing UI replacement.
- Verified:
  - `npm.cmd run react:build` passes.
  - `php artisan route:list --path=react-ondemand` passes.
  - `GET /react-ondemand` returns 200.
  - `GET /react-ondemand/ezwaytv` returns 200.
  - `GET /video-details/tpx-the-platform-xperience-episode?autoplay=1&ondemand_channel=1` returns 200.
- No migrations were run and no database tables were altered.

### React Videos Module

- Added parallel local React route:
  - `GET /react-videos/{category?}`
- Added React Videos files:
  - `resources/react/modules/videos/VideosPage.tsx`
  - `resources/react/modules/videos/videosApi.ts`
- Updated the React app shell to render the Videos module for `/react-videos`.
- Updated the React home navigation and Latest Videos rail to point to `/react-videos`.
- The React Videos module uses the existing API:
  - `GET /api/v3/video-list?per_page=48`
  - optional category filter: `category_slug`
- React video cards still link to legacy playback:
  - `/video-details/{slug}?autoplay=1`
  - if present: `/video-details/{slug}?autoplay=1&ondemand_channel={channelId}`
- This intentionally preserves the existing video detail page, player, VAST, custom ads, stats, paywall, and access logic.
- Verified:
  - `npm.cmd run react:build` passes.
  - `php artisan route:list --path=react-videos` passes.
  - `GET /api/v3/video-list?per_page=4` returns 200 with video data.
  - `GET /react-videos` returns 200.
  - `GET /video-details/exclusive-interview-with-sir-david-fagan-by-dr-amb-eric-zuley?autoplay=1` returns 200.
- No migrations were run and no database tables were altered.

### Existing Single Video Page Replacement

- Replaced the existing single video detail page at:
  - `GET /video-details/{slug}`
- Added React detail module:
  - `resources/react/modules/video-detail/VideoDetailPage.tsx`
- Updated:
  - `resources/react/App.tsx`
  - `Modules/Frontend/Resources/views/video_detail.blade.php`
- Replacement approach:
  - React now renders the visible video detail shell, metadata, actions, On Demand channel badges, and related video cards.
  - The existing Laravel `thumbnail` player include remains server-rendered to preserve player metadata and playback behavior.
  - Custom ad banner include remains in the Blade view.
  - `_ezPageMeta` remains in the Blade view for stats tracking.
  - `ondemand_channel` context remains preserved for On Demand videos.
- Watch behavior:
  - React `Watch Now` uses `/video-details/{slug}?autoplay=1&continue_watch=true`.
  - If an On Demand channel context exists, it also preserves `ondemand_channel={channelId}`.
- Verified:
  - `npm.cmd run react:build` passes.
  - `php artisan route:list --path=video-details` passes.
  - `GET /video-details/exclusive-interview-with-sir-david-fagan-by-dr-amb-eric-zuley?autoplay=1` returns 200.
  - `GET /video-details/exclusive-interview-with-sir-david-fagan-by-dr-amb-eric-zuley?autoplay=1&continue_watch=true` returns 200.
  - `GET /video-details/tpx-the-platform-xperience-episode?autoplay=1&ondemand_channel=1` returns 200.
  - Response contains `__EZWAY_VIDEO_DETAIL__`, `react-modernization-root`, and built Vite assets.
- No migrations were run and no database tables were altered.

### Full React SPA Foundation

- User clarified the target is a full React SPA for the frontend.
- Added local SPA namespace:
  - `GET /spa/{path?}`
- Existing converted modules now work through the SPA namespace:
  - `/spa`
  - `/spa/ondemand`
  - `/spa/ondemand/{username}`
  - `/spa/videos`
  - `/spa/videos/{category}`
- Existing preview routes remain temporarily available:
  - `/react-home`
  - `/react-ondemand/{username?}`
  - `/react-videos/{category?}`
- Updated React navigation and module links to prefer `/spa/*`.
- High-risk legacy bridges remain intentionally available:
  - `/video-details/{slug}` for player/VAST/custom ads/stats/access logic.
  - payment and admin routes.
- Verified:
  - `npm.cmd run react:build` passes.
  - `php artisan route:list --path=spa` passes.
  - `GET /spa` returns 200.
  - `GET /spa/ondemand/ezwaytv` returns 200.
  - `GET /spa/videos` returns 200.
  - `GET /video-details/exclusive-interview-with-sir-david-fagan-by-dr-amb-eric-zuley?autoplay=1` still returns 200.
- No migrations were run and no database tables were altered.

### Client-Side SPA Navigation

- User reported SPA pages were still doing full page reloads.
- `react-router-dom` is not installed, so added a lightweight internal router instead of introducing a new dependency:
  - `resources/react/lib/spa-router.tsx`
- Updated:
  - `resources/react/main.tsx`
  - `resources/react/App.tsx`
  - `resources/react/modules/ondemand/OnDemandPage.tsx`
- Behavior:
  - Same-origin `/spa/*` links are intercepted.
  - Internal SPA navigation now uses `history.pushState` / `history.replaceState`.
  - React re-renders the active module without asking Laravel for a fresh page.
  - Browser back/forward is handled through `popstate`.
  - Legacy/high-risk routes still use normal browser navigation, including `/video-details/*`, payment routes, and admin routes.
- Verified:
  - `npm.cmd run react:build` passes.
  - `GET /spa` returns 200.
  - `GET /spa/ondemand/ezwaytv` returns 200.
  - `GET /spa/videos` returns 200.
- No migrations were run and no database tables were altered.

### React Live TV Module

- User requested the SPA set: Home, On Demand, Live TV, Videos.
- Added React Live TV files:
  - `resources/react/modules/live-tv/LiveTvPage.tsx`
  - `resources/react/modules/live-tv/liveTvApi.ts`
- Added SPA route handling for:
  - `/spa/live-tv`
- Updated SPA navigation across converted modules to the four requested pages:
  - Home: `/spa`
  - On Demand: `/spa/ondemand`
  - Live TV: `/spa/live-tv`
  - Videos: `/spa/videos`
- React Live TV uses the existing API:
  - `GET /api/v3/livetv-dashboard`
- Live TV channel cards intentionally bridge to existing Laravel detail/player routes:
  - `/livetv-details/{id}`
- This preserves existing Live TV player, schedule, chat, VAST, custom ads, stats, and access behavior while the browsing UI moves into React.
- Verified:
  - `npm.cmd run react:build` passes.
  - `GET /spa` returns 200.
  - `GET /spa/ondemand` returns 200.
  - `GET /spa/live-tv` returns 200.
  - `GET /spa/videos` returns 200.
  - `GET /livetv-details/37` returns 200.
- No migrations were run and no database tables were altered.

### Live TV Detail Link Fix

- User reported `GET /livetv-details/86` was not working.
- Root cause:
  - Legacy `LiveTvController::liveTvDetails()` only looked up channels by `slug`.
  - React Live TV cards were bridging with numeric channel ids because the public V3 Live TV dashboard resource did not expose slugs.
- Fixed:
  - `Modules/Frontend/Http/Controllers/LiveTvController.php` now accepts either slug or numeric id for Live TV detail lookup.
  - `Modules/LiveTV/Transformers/LiveTvChannelResourceV3.php` now includes `slug` at top level and in `details`.
  - React Live TV/Home links now prefer slug and fall back to id.
- Verified:
  - `npm.cmd run react:build` passes.
  - `GET /livetv-details/86` returns 200.
  - `GET /livetv-details/trailblazers-tv` returns 200.
  - `GET /spa/live-tv` returns 200.
- No migrations were run and no database tables were altered.

### Live TV Detail SPA Route

- User clarified Live TV details should also stay inside the SPA.
- Added React detail handling for:
  - `/spa/live-tv/{slugOrId}`
- Updated React Live TV and Home Live TV links so channel cards open SPA detail pages instead of reloading into `/livetv-details/{slugOrId}`.
- Kept the high-risk playback bridge:
  - React `Watch Live` still opens `/livetv-details/{slugOrId}`.
  - This preserves the existing Laravel Live TV player, VAST/custom ads, schedule behavior, chat, stats, and access logic.
- Hardened the V3 Live TV details API:
  - `GET /api/v3/livetv-details?channel_id={idOrSlug}`
  - `GET /api/v3/livetv-details?id={idOrSlug}`
  - accepts numeric ids or slugs.
  - returns 404 for missing channels instead of throwing a 500.
- Verified:
  - `npm.cmd run react:build` passes.
  - `GET /spa/live-tv/86` returns 200 with the React mount.
  - `GET /spa/live-tv/trailblazers-tv` returns 200 with the React mount.
  - `GET /api/v3/livetv-details?channel_id=86` returns 200.
  - `GET /api/v3/livetv-details?channel_id=trailblazers-tv` returns 200.
  - `GET /livetv-details/86` still returns 200.
- No migrations were run and no database tables were altered.

### Live TV Watch Behavior

- User rejected embedding the whole legacy Live TV page inside the React detail page.
- Removed the iframe-based player wrapper.
- Current behavior:
  - `/livetv/{slugOrId}` remains a React SPA detail page.
  - `Watch Live` links to `/livetv-details/{slugOrId}` until the Live TV player itself is ported cleanly into React.
  - No hidden iframe of the legacy page is used.
- No migrations were run and no database tables were altered.

### Public Frontend Routes Replaced With React

- User requested removing the legacy frontend feel and replacing the home route.
- Switched the public home route:
  - `/` now renders `react-modernization`.
- Switched converted public browsing routes to React mounts:
  - `/on-demand`
  - `/on-demand/{username}`
  - `/livetv`
  - `/livetv/{slugOrId}`
  - `/videos`
  - `/videos/category/{slug}`
- Updated React client routing so these public URLs navigate without full page reloads.
- Updated React navigation and cards to use canonical public URLs instead of `/spa/*` preview URLs.
- Kept high-risk legacy routes as functional fallbacks/bridges:
  - `/video-details/{slug}` for the existing video player, VAST/custom ads, stats, paywall, and access logic.
  - `/livetv-details/{slugOrId}` for the existing Live TV player/chat/stats/access logic and iframe fallback.
  - Admin, payment, auth, and API routes remain intact.
- Verified:
  - `npm.cmd run react:build` passes.
  - `GET /` returns 200 with the React mount.
  - `GET /on-demand` returns 200 with the React mount.
  - `GET /on-demand/ezwaytv` returns 200 with the React mount.
  - `GET /livetv` returns 200 with the React mount.
  - `GET /livetv/86` returns 200 with the React mount.
  - `GET /videos` returns 200 with the React mount.
  - `GET /videos/category/music` returns 200 with the React mount.
  - `GET /livetv-details/86` still returns 200.
  - `GET /video-details/exclusive-interview-with-sir-david-fagan-by-dr-amb-eric-zuley?autoplay=1` still returns 200.
- No migrations were run and no database tables were altered.

### Public Frontend SPA Expansion

- User requested making the whole project SPA and not waiting module-by-module.
- Expanded the React ownership boundary from converted modules to the broader public frontend route set.
- Added React public fallback screen:
  - `resources/react/modules/public/PublicPage.tsx`
- Replaced the old migration-status fallback in `resources/react/App.tsx` with the public React screen.
- Expanded client-side SPA interception:
  - Public frontend links now stay inside React navigation.
  - Service paths remain excluded: `/api`, `/app`, `/admin`, `/auth`, `/sanctum`, `/livewire`, `/storage`, `/build`, `/vendor`, `/install`, `/_ignition`, `/video/stream`, `/video/1`.
- Converted additional public GET page routes to `react-modernization`, including:
  - auth screens: `/login`, `/otp-login`, `/login-page`, `/register`, `/forget-password`
  - movies and TV: `/movies`, `/movies/{language}`, `/movies/genre/{genre_id}`, `/movie-details/{id}`, `/tv-shows`, `/tvshow-details/{id}`, `/episode-details/{id}`
  - video shell: `/video-details/{id}`
  - pay-per-view and account pages: `/pay-per-view`, `/payment-form/pay-per-view`, `/payment/success`, `/payment/success/pay-per-view`, `/unlock-videos`, `/account-setting`, `/subscription-plan`, `/subscription-payment`, `/payment-history`, `/transaction-history`
  - browsing utility pages: `/content/{type}`, `/section/{slug}`, `/comingsoon`, `/comming-soon-details/{id}`, `/castcrew-list`, `/castcrew-detail/{id}`, `/continuewatch-list`, `/language-list`, `/topchannel-list`, `/genres-list`, `/search`, `/watch-list`, `/faq`, `/all-review/{id}`, `/trending-movies`, `/notifications`, `/update-profile`, `/change-password`, `/music`, `/upload-your-videoes/{channel}`, `/distribution`
- Preserved backend/service routes:
  - admin routes
  - API routes
  - POST actions
  - OAuth/callback routes
  - stream routes
  - payment processing routes
  - notification mutation routes
  - chat, ad, stats, and other service endpoints
- Verified:
  - `npm.cmd run react:build` passes.
  - `php artisan route:list --path=movies` passes.
  - `php artisan route:list --path=video-details` passes.
  - `php artisan route:list --path=login` passes.
  - React mount returns 200 for `/`, `/login`, `/register`, `/movies`, `/movie-details/demo`, `/tv-shows`, `/tvshow-details/demo`, `/episode-details/demo`, `/video-details/exclusive-interview-with-sir-david-fagan-by-dr-amb-eric-zuley`, `/pay-per-view`, `/search`, `/faq`, `/account-setting`, `/subscription-plan`, `/notifications`, `/music`, `/distribution`.
- No migrations were run and no database tables were altered.

### React Video Detail And Watch Pass

- Started replacing the core watch flow without iframe or legacy page embedding.
- Hardened the existing video detail API:
  - `GET /api/video-details?slug={slug}`
  - `GET /api/video-details?id={idOrSlug}`
  - `GET /api/video-details?video_id={idOrSlug}`
  - supports On Demand context with `ondemand_channel={channelId}`.
  - returns related On Demand videos and `ondemand_channel_context` when a matching channel is present.
- Added React API adapter:
  - `resources/react/modules/video-detail/videoDetailApi.ts`
- Rebuilt React video detail page:
  - `resources/react/modules/video-detail/VideoDetailPage.tsx`
- Current React watch behavior:
  - `/video-details/{slug}` loads detail data from the API.
  - Renders a React-native detail page and HTML video player.
  - Preserves On Demand channel context in related links.
  - Calls existing VAST and custom ad discovery endpoints.
  - Calls existing statistics endpoints for page view, play event, and periodic watch-time updates.
  - No iframe of the legacy video page is used.
- Important remaining player work:
  - Existing packages include `video.js`, `videojs-contrib-ads`, and `videojs-ima`.
  - Full IMA/VAST ad playback inside the React player still needs a dedicated Video.js player integration pass.
  - The current pass wires the existing ad endpoints and avoids breaking the route, but does not yet implement full client-side IMA ad playback.
- Verified:
  - `npm.cmd run react:build` passes.
  - `php artisan route:list --path=api/video-details` passes.
  - `GET /api/video-details?slug=exclusive-interview-with-sir-david-fagan-by-dr-amb-eric-zuley` returns 200 with video data and related items.
  - `GET /api/video-details?slug=tpx-the-platform-xperience-episode&ondemand_channel=1` returns 200 with On Demand channel context.
  - `GET /api/vast-ads/get-active?type=video&content_id=264&video_type=full` returns 200.
  - `GET /api/custom-ads/get-active?type=video&content_id=264` returns 200.
  - `GET /video-details/exclusive-interview-with-sir-david-fagan-by-dr-amb-eric-zuley?autoplay=1` returns 200 with the React mount.
  - `GET /video-details/tpx-the-platform-xperience-episode?autoplay=1&ondemand_channel=1` returns 200 with the React mount.
- No migrations were run and no database tables were altered.

### Video.js IMA Player Integration

- Added the Google IMA SDK script to the React shell:
  - `resources/views/react-modernization.blade.php`
- Added React Video.js player component:
  - `resources/react/modules/video-detail/VideoJsPlayer.tsx`
- Updated video detail page to use Video.js instead of a plain HTML `<video>` element:
  - `resources/react/modules/video-detail/VideoDetailPage.tsx`
- Player behavior:
  - Uses `video.js`.
  - Loads `videojs-contrib-ads`.
  - Loads `videojs-ima`.
  - Passes the first active VAST ad `url` from `/api/vast-ads/get-active` into IMA as `adTagUrl`.
  - Calls IMA ad display container initialization from the play gesture when available.
  - Keeps existing React stats hooks for play and watch-time updates.
  - No iframe or legacy page embed is used.
- Added player theme styling:
  - `resources/react/styles.css`
- Verification:
  - `npm.cmd run react:build` passes.
  - `GET /video-details/exclusive-interview-with-sir-david-fagan-by-dr-amb-eric-zuley?autoplay=1` returns 200 with React mount and IMA SDK script.
  - `GET /api/video-details?slug=exclusive-interview-with-sir-david-fagan-by-dr-amb-eric-zuley` returns 200 with playable video URL.
- Note:
  - Vite reports a chunk-size warning because Video.js + IMA adds a large player bundle. This is expected for now and can be improved later with route-level dynamic import/code splitting.
- No migrations were run and no database tables were altered.

### React Live TV Player Integration

- Replaced the Live TV detail watch flow with a React-native player surface.
- Updated Live TV detail page:
  - `resources/react/modules/live-tv/LiveTvPage.tsx`
- Expanded Live TV API helper:
  - `resources/react/modules/live-tv/liveTvApi.ts`
- Added CSRF support for React web requests:
  - `resources/views/react-modernization.blade.php`
  - `resources/react/lib/api.ts`
- Current Live TV behavior:
  - `/livetv/{slugOrId}` loads Live TV detail data from `/api/v3/livetv-details?channel_id={slugOrId}`.
  - Uses the existing `VideoJsPlayer` component for direct HLS/MP4 stream playback.
  - Passes active Live TV VAST ad URL from `/api/vast-ads/get-active?type=livetv&content_id={id}` into IMA.
  - Calls custom ad discovery endpoint:
    - `/api/custom-ads/get-active?type=livetv&content_id={id}`
  - Calls statistics endpoints for view, play, and watch-time updates with `content_type=livetv`.
  - Shows now/next schedule data from the existing V3 detail API.
  - Shows Live TV chat via existing routes:
    - `GET /livetv-chat/{channelId}/messages`
    - `POST /livetv-chat/{channelId}/messages`
  - No iframe or legacy page embed is used.
- Preserved service routes:
  - `/livetv-details/{id}` remains available as a legacy fallback/service route.
  - `/livetv-chat/*`, ad APIs, stats APIs, and stream endpoints remain intact.
- Verified:
  - `npm.cmd run react:build` passes.
  - `GET /livetv/86` returns 200 with React mount and IMA SDK script.
  - `GET /livetv/trailblazers-tv` returns 200 with React mount.
  - `GET /api/v3/livetv-details?channel_id=86` returns 200 with HLS stream data.
  - `GET /api/v3/livetv-details?channel_id=trailblazers-tv` returns 200 with HLS stream data.
  - `GET /livetv-chat/86/messages` returns 200.
  - `GET /api/vast-ads/get-active?type=livetv&content_id=86&video_type=full` returns 200.
  - `GET /api/custom-ads/get-active?type=livetv&content_id=86` returns 200.
  - `php artisan route:list --path=api/statistics` passes.
- No migrations were run and no database tables were altered.

### Live TV Chat And Full Schedule UI

- User requested disabled Live TV chat should not show any UI.
- Updated React Live TV detail behavior:
  - If `GET /livetv-chat/{channelId}/messages` returns `enabled: false`, the chat panel is not rendered at all.
  - If chat is enabled, the existing chat list and send form render normally.
- User also noted the existing Full Schedule feature.
- Added Full Schedule support to React Live TV detail:
  - Tries external `schedules_url` from `/api/v3/livetv-details`.
  - Falls back to internal `/api/channel-schedules?channel_id={id}`.
  - Renders an expandable `Full Schedule` panel only when schedule items exist.
- Updated:
  - `resources/react/modules/live-tv/liveTvApi.ts`
  - `resources/react/modules/live-tv/LiveTvPage.tsx`
- Verified:
  - `npm.cmd run react:build` passes.
  - `GET /api/channel-schedules?channel_id=86` returns 200.
  - `GET /livetv/86` returns 200 with the React mount.
- No migrations were run and no database tables were altered.

### Live TV Schedule Reliability Pass

- User reported the Now, Next, and Full Schedule UI was not good and was not working properly.
- Moved full schedule normalization into the existing Live TV detail API instead of relying on the browser to fetch the external schedule URL directly.
- Updated `Modules/LiveTV/Transformers/LiveTvChannelDetailsResourceV3.php`:
  - Adds `full_schedule` to `/api/v3/livetv-details`.
  - Keeps existing `now_playing` and `next_playing`.
  - Returns a schedule window around the current on-air program instead of stale earliest rows.
- Updated React Live TV detail:
  - `resources/react/modules/live-tv/LiveTvPage.tsx`
  - `resources/react/modules/home/types.ts`
  - `resources/react/modules/live-tv/liveTvApi.ts`
- UI behavior:
  - Now Playing and Up Next are rendered in one schedule panel.
  - Now Playing includes a progress bar when duration/elapsed values exist.
  - Full Schedule renders as a scrollable program list with the current item marked `On Air`.
  - Disabled chat still renders no chat UI.
- Verified:
  - `php -l Modules\LiveTV\Transformers\LiveTvChannelDetailsResourceV3.php` passes.
  - `npm.cmd run react:build` passes.
  - `GET /api/v3/livetv-details?channel_id=86` returns `full_schedule` with 72 rows around the current program and includes the current row.
  - `GET /livetv/86` returns 200.
- No migrations were run and no database tables were altered.

### Live TV Autoplay Pass

- User requested Watch Live should autoplay.
- Updated `resources/react/modules/video-detail/VideoJsPlayer.tsx`:
  - Added `muted`, `playTrigger`, and `unmuteOnPlayTrigger` support.
  - The player can now receive a direct play signal from React instead of depending on clicking the Video.js overlay button.
- Updated `resources/react/modules/live-tv/LiveTvPage.tsx`:
  - Live TV detail passes `autoplay` and `muted` to the player so playback can start automatically when the browser allows muted autoplay.
  - The Watch Live button now sends a direct play request and unmutes from the user gesture.
- Verified:
  - `npm.cmd run react:build` passes.
  - `php -l Modules\LiveTV\Transformers\LiveTvChannelDetailsResourceV3.php` passes.
- No migrations were run and no database tables were altered.

### Video Detail Blank Player Fix

- User reported videos play and then show a blank screen.
- Updated `resources/react/modules/video-detail/VideoJsPlayer.tsx`:
  - The player no longer disposes/recreates when the VAST ad list changes after video load.
  - VAST/IMA setup now runs separately against the existing player instance.
  - Direct play triggers remain supported for React-owned buttons.
- Updated `resources/react/modules/video-detail/VideoDetailPage.tsx`:
  - Watch Now now sends a direct play trigger to the player instead of clicking the Video.js overlay button.
  - Player source resolution now falls back through uploaded URL, stream mappings, then trailer URL.
- Verified:
  - `npm.cmd run react:build` passes.
  - `GET /video-details/exclusive-interview-with-sir-david-fagan-by-dr-amb-eric-zuley` returns 200.
  - `GET /api/video-details?slug=exclusive-interview-with-sir-david-fagan-by-dr-amb-eric-zuley` returns `status: true`.
- No migrations were run and no database tables were altered.

### Card Ratio And Image Fit Pass

- User requested browsing cards should use a `3:2` ratio and square images should fit with a blurred background.
- Added shared thumbnail frame:
  - `resources/react/components/MediaThumbnail.tsx`
- Updated card surfaces to use the shared `3:2` image frame:
  - `resources/react/modules/home/HomePage.tsx`
  - `resources/react/modules/videos/VideosPage.tsx`
  - `resources/react/modules/ondemand/OnDemandPage.tsx`
  - `resources/react/modules/live-tv/LiveTvPage.tsx`
  - `resources/react/modules/video-detail/VideoDetailPage.tsx`
  - `resources/react/modules/genres/GenresPilot.tsx`
- Behavior:
  - Cards now reserve `aspect-[3/2]`.
  - The actual image uses `object-contain`.
  - A blurred, enlarged copy of the same image fills the background behind square or narrow artwork.
  - Actual video player surfaces remain `16:9`.
- Verified:
  - `npm.cmd run react:build` passes.
  - `GET /videos`, `GET /livetv`, and `GET /on-demand` return 200.
- No migrations were run and no database tables were altered.

### Video Card Size And Hover Preview Pass

- User reported the `Latest Videos` cards were too small and video cards should auto-preview on hover like before.
- Updated shared thumbnail behavior:
  - `resources/react/components/MediaThumbnail.tsx`
  - Supports an optional `previewSrc`.
  - Plays muted, looped preview video on hover/focus.
  - Stops and resets preview when hover/focus leaves.
- Updated video card sources:
  - Home `Latest Videos` rail passes `video_url_input`, `video_url`, or `trailer_url` as preview source.
  - Videos listing cards pass preview source.
  - On Demand video cards pass preview source.
  - Video detail related cards pass preview source.
- Increased Home rail sizing:
  - `Latest Videos` now uses wider card columns than normal image/channel rails.
- Verified:
  - `npm.cmd run react:build` passes.
  - `GET /`, `GET /videos`, and `GET /on-demand` return 200.
- No migrations were run and no database tables were altered.

### Default Poster Filtering Pass

- User reported default images appearing in the `Latest Videos` cards.
- Confirmed the existing API returns local placeholder poster URLs for missing video posters:
  - `http://127.0.0.1:8008/default-image/Default-Image.jpg`
  - Affected current latest video examples include IDs `260`, `259`, `258`, and `257`.
- Updated `resources/react/components/MediaThumbnail.tsx`:
  - Treats `/default-image/` and `Default-Image.jpg` URLs as missing images.
  - Prevents backend placeholder artwork from rendering inside React cards.
  - Keeps hover video preview behavior available when a video URL exists.
- Verified:
  - `npm.cmd run react:build` passes.
  - `GET /` returns 200.
- No migrations were run and no database tables were altered.

### Video Fallback Preview Pass

- User clarified that if an item is a video and its poster is only the default placeholder, the card should show the video preview instead.
- Updated `resources/react/components/MediaThumbnail.tsx`:
  - If the image is a known default placeholder and `previewSrc` exists, the video element becomes the visible card media.
  - The video preloads metadata for missing-poster cards so the browser can show the video frame.
  - Hover/focus still plays the muted loop preview.
- Verified:
  - `npm.cmd run react:build` passes.
  - `GET /` and `GET /videos` return 200.
- No migrations were run and no database tables were altered.

### Home Rail Seven-Card Layout Pass

- User requested the Home rails should show `7` cards per row for Live TV, On Demand, and Videos.
- Updated `resources/react/modules/home/HomePage.tsx`:
  - Home rails now use consistent card sizing across content types.
  - Wide desktop uses `7` visible cards with `3:2` media frames.
  - Smaller breakpoints keep responsive card counts for mobile/tablet.
- Verified:
  - `npm.cmd run react:build` passes.
  - `GET /` returns 200.
- No migrations were run and no database tables were altered.

### Vite Manifest 500 Fix

- User reported a 500 error after the latest frontend changes.
- Laravel log showed:
  - `Unable to locate file in Vite manifest: resources/react/main.tsx`
- Cause:
  - Plain Vite was generating the manifest key as a long relative Windows path when the build input was configured as a named object.
  - Laravel `@vite('resources/react/main.tsx')` expects the manifest key to be exactly `resources/react/main.tsx`.
- Updated `vite.config.ts`:
  - Changed `rollupOptions.input` from a named object to the string `resources/react/main.tsx`.
  - Rebuilt assets so `public/build/manifest.json` now contains the expected key.
- Verified:
  - `npm.cmd run react:build` passes.
  - Manifest key is `resources/react/main.tsx`.
  - `GET /`, `GET /videos`, `GET /livetv`, and `GET /on-demand` return 200.
- No migrations were run and no database tables were altered.

### Popular Personalities Home Rail Pass

- User noted there is a `Popular Personality` section.
- Existing backend data source:
  - Mobile setting slug: `your-favorite-personality`
  - Cast/Crew records from the existing `cast_crew` table.
- Updated active dashboard API response:
  - `app/Http/Controllers/Backend/API/DashboardController.php`
  - `/api/v3/dashboard-detail` now includes `personality`.
- Updated React Home:
  - `resources/react/modules/home/HomePage.tsx`
  - `resources/react/modules/home/types.ts`
  - Adds a `Popular Personalities` rail using existing `profile_image` data.
  - Personality cards link to existing `/castcrew-detail/{id}` routes.
- Verified:
  - `php -l app\Http\Controllers\Backend\API\DashboardController.php` passes.
  - `php artisan cache:clear` completed after the API response update.
  - `npm.cmd run react:build` passes.
  - `/api/v3/dashboard-detail` returns `personality` with 11 items.
  - `GET /` returns 200.
- No migrations were run and no database tables were altered.

### Home Rail Visibility Cleanup

- User requested:
  - Remove Popular Language.
  - Remove Genres.
  - Do not show any rail if it has no data.
- Updated `resources/react/modules/home/HomePage.tsx`:
  - Removed the Popular Language rail from React Home.
  - Removed the Genres rail from React Home.
  - `Rail` now returns `null` when `items.length === 0`.
  - Empty placeholder rail text no longer appears.
- Verified:
  - `npm.cmd run react:build` passes.
  - `GET /` returns 200.
- No migrations were run and no database tables were altered.

### Home Card Title And Personality Shape Pass

- User reported card titles were hidden under images and Popular Personalities should be circular.
- Updated `resources/react/modules/home/HomePage.tsx`:
  - Normal media card titles now render below the image frame instead of as an image overlay.
  - Card metadata such as video count or duration also renders below the image.
  - Popular Personalities cards now use circular portrait media.
  - Personality title and type render below the circular image.
- Verified:
  - `npm.cmd run react:build` passes.
  - `GET /` returns 200.
- No migrations were run and no database tables were altered.

### Popular Personalities Ten-Column Pass

- User clarified Popular Personalities should show `10` columns.
- Updated `resources/react/modules/home/HomePage.tsx`:
  - Popular Personalities rail now uses a dedicated responsive column width.
  - Wide desktop targets `10` visible circular personality cards.
  - Other Home media rails keep the previous `7`-card wide desktop layout.
- Verified:
  - `npm.cmd run react:build` passes.
  - `GET /` returns 200.
- No migrations were run and no database tables were altered.

### Cast/Crew List React SPA Pass

- User requested `http://127.0.0.1:8008/castcrew-list` should be a React SPA page.
- Added React API helper:
  - `resources/react/modules/castcrew/castCrewApi.ts`
- Added React page:
  - `resources/react/modules/castcrew/CastCrewPage.tsx`
- Updated route switch:
  - `resources/react/App.tsx`
- Behavior:
  - `/castcrew-list` renders a React page instead of generic fallback content.
  - Uses existing `GET /api/castcrew-list`.
  - Supports search.
  - Supports `all`, `actor`, and `director` filters.
  - Renders circular personality cards in a responsive grid.
  - Cards link to existing `/castcrew-detail/{id}` route.
- Verified:
  - `npm.cmd run react:build` passes.
  - `GET /castcrew-list` returns 200 with the React mount.
  - `GET /api/castcrew-list?per_page=5` returns `status: true`.
- No migrations were run and no database tables were altered.

### Cast/Crew Detail React SPA Pass

- User reported `/castcrew-detail/{id}` still showed the generic React fallback text.
- Added API detail loader:
  - `resources/react/modules/castcrew/castCrewApi.ts`
  - Uses existing `GET /api/v3/cast-details?id={id}&type={type}`.
  - Falls back across `actor` and `director` so old `/castcrew-detail/{id}` links still work.
- Added React detail page:
  - `resources/react/modules/castcrew/CastCrewDetailPage.tsx`
- Updated route switch:
  - `resources/react/App.tsx`
- Updated links:
  - `resources/react/modules/castcrew/CastCrewPage.tsx`
  - `resources/react/modules/home/HomePage.tsx`
  - New links include `?type={actor|director}` when available.
- Behavior:
  - `/castcrew-detail/{id}` is now a dedicated React screen.
  - Shows circular profile image, name, role, bio, movie count, TV count, rating, birth date, birth place, and top genres when available.
- Verified:
  - `npm.cmd run react:build` passes.
  - `GET /castcrew-detail/45?type=actor` returns 200 with the React mount.
  - `GET /castcrew-detail/45` returns 200.
  - `GET /api/v3/cast-details?id=45&type=actor` returns `status: true`.
- No migrations were run and no database tables were altered.

### Live TV Blank First Frame Fix

- User reported Live TV still showed a blank first screen and said autoplay can be off if necessary.
- Updated `resources/react/modules/live-tv/LiveTvPage.tsx`:
  - Live TV detail no longer mounts Video.js automatically on page load.
  - Autoplay is off for the initial Live TV detail render.
  - The player area now shows a poster-based start screen with a play button.
  - Clicking `Watch Live` or the poster start screen mounts the Video.js player and sends the play trigger.
  - Channel changes reset player state, play tracking state, and watch-time timer.
- Verified:
  - `npm.cmd run react:build` passes.
  - `GET /livetv/86` returns 200 with the React mount.
  - `GET /api/v3/livetv-details?channel_id=86` returns poster and HLS stream data.
- No migrations were run and no database tables were altered.

### Live TV Schedule And Card Title Cleanup

- User requested:
  - If no schedule API/data is available, do not show any schedule UI.
  - More Live Channels cards had titles hidden under the image.
- Updated `resources/react/modules/live-tv/LiveTvPage.tsx`:
  - Schedule panel returns nothing when there is no now/next/full schedule data.
  - The entire schedule/chat band is hidden when both schedule data and enabled chat are absent.
  - Full Schedule no longer shows empty fallback text.
  - Now/Next empty slot text no longer appears unless the panel is actively loading.
  - Live TV card titles and categories now render below the image instead of inside the image overlay.
- Verified:
  - `npm.cmd run react:build` passes.
  - `GET /livetv/86` returns 200.
  - `GET /livetv` returns 200.
- No migrations were run and no database tables were altered.

### Settings-Driven Branding Pass

- User requested the React SPA logo and theme color come from the settings API.
- Updated `GET /api/v3/app-configuration` in `app/Http/Controllers/Backend/API/SettingController.php`:
  - Added `app_name`.
  - Added `app_light_logo`.
  - Added `theme_color`.
  - Added `root_colors`.
  - Existing `app_logo`, `app_mini_logo`, favicon, and loader values remain intact.
- Added React branding layer:
  - `resources/react/lib/branding.tsx`
  - Fetches `/api/v3/app-configuration` once.
  - Applies logo/favicon metadata.
  - Applies primary theme color to React/shadcn CSS variables.
  - Supports custom `root_colors` and preset values such as `default`, `color-1` through `color-5`, and `gold`.
- Added shared logo component:
  - `resources/react/components/BrandLogo.tsx`
- Replaced hardcoded header logo text across public SPA modules:
  - Home
  - On Demand
  - Live TV
  - Videos
  - Video Detail
  - Cast/Crew List
  - Cast/Crew Detail
  - Generic public fallback page
- Verified:
  - `GET /api/v3/app-configuration` returns `app_name`, `app_logo`, `theme_color`, and `root_colors`.
  - `GET /` returns 200.
  - `npm.cmd run react:build` passes.
- No migrations were run and no database tables were altered.

### On Demand Thumbnail Cleanup

- User reported `/on-demand` card thumbnails looked broken.
- Updated `resources/react/modules/ondemand/OnDemandPage.tsx`:
  - Channel selector thumbnails now use the shared `MediaThumbnail` component.
  - Channel thumbnails are stable 3:2 instead of raw square image tags.
  - Channel images now use a safer resolver: cover image, TV poster, poster, thumbnail, avatar, then profile image.
  - Selected channel avatar now uses the same thumbnail handling instead of a raw image tag.
  - On Demand video cards now resolve the best available poster before rendering.
- Verified:
  - `npm.cmd run react:build` passes.
  - `GET /on-demand` returns 200.
  - `GET /api/v3/ondemand?per_page=3` returns channel image data.
- No migrations were run and no database tables were altered.

### On Demand Video Card Overlay Refinement

- User showed `/on-demand` video cards still looked wrong because the play icon sat at the top-left of each thumbnail.
- Updated `resources/react/modules/ondemand/OnDemandPage.tsx`:
  - Removed the permanent top-left play button overlay from On Demand video cards.
  - Added a centered play affordance that appears on hover.
  - Reduced the thumbnail overlay to a bottom gradient so the image stays clean.
- Verified:
  - `npm.cmd run react:build` passes.
  - `GET /on-demand` returns 200.
- No migrations were run and no database tables were altered.

### On Demand Navbar Refinement

- User reported the `/on-demand` navbar did not look good.
- Updated `resources/react/modules/ondemand/OnDemandPage.tsx`:
  - Reduced the header height and logo footprint.
  - Added a compact OTT-style nav group.
  - Added a clear active state for `On Demand`.
  - Made the profile button safer on narrow widths by hiding the text on small screens.
  - Kept settings-driven `BrandLogo`.
- Verified:
  - `npm.cmd run react:build` passes.
  - `GET /on-demand` returns 200.
- No migrations were run and no database tables were altered.

### Shared Main Header Navbar Pass

- User clarified the main header/navbar did not look good, not only the On Demand local header.
- Added shared React header:
  - `resources/react/components/AppHeader.tsx`
- Updated public SPA modules to use the shared header:
  - `resources/react/modules/home/HomePage.tsx`
  - `resources/react/modules/ondemand/OnDemandPage.tsx`
  - `resources/react/modules/live-tv/LiveTvPage.tsx`
  - `resources/react/modules/videos/VideosPage.tsx`
  - `resources/react/modules/video-detail/VideoDetailPage.tsx`
  - `resources/react/modules/castcrew/CastCrewPage.tsx`
  - `resources/react/modules/castcrew/CastCrewDetailPage.tsx`
  - `resources/react/modules/public/PublicPage.tsx`
- Header behavior:
  - Uses the settings-driven `BrandLogo`.
  - Consistent height, spacing, dark translucent background, and blur across routes.
  - Clear active nav state for Home, On Demand, Live TV, and Videos.
  - Shared search icon and profile link.
  - Profile text hides on small screens to avoid crowding.
- Verified:
  - `npm.cmd run react:build` passes.
  - `GET /`, `/on-demand`, `/livetv`, and `/videos` return 200.
- No migrations were run and no database tables were altered.

### Videos Pagination And Header Dropdown Menus

- User reported `/videos` was not showing all videos.
- Updated `resources/react/modules/videos/videosApi.ts`:
  - Video loader now follows the existing API `hasMore` pagination.
  - Loads page by page with `is_ajax=1`, `page`, and `per_page=48`.
  - Deduplicates by ID after all pages load.
- User requested main navbar dropdown menus for Videos, Live TV, and On Demand.
- Updated `resources/react/components/AppHeader.tsx`:
  - Videos nav item now has a dropdown populated from `/api/v3/video-list`.
  - Live TV nav item now has a dropdown populated from `/api/v3/livetv-dashboard`.
  - On Demand nav item now has a dropdown populated from `/api/v3/ondemand`.
  - Dropdown rows link to their respective React SPA routes.
  - Dropdown panels are scrollable so long lists stay usable.
- Verified:
  - Existing video API reports 226 videos across 5 pages with `per_page=48`.
  - `npm.cmd run react:build` passes.
  - `GET /videos`, `/on-demand`, and `/livetv` return 200.
- Browser plugin visual check was attempted, but the in-app browser was unavailable in this session.
- No migrations were run and no database tables were altered.

### Videos Infinite Loader Pass

- User requested an infinite loader instead of showing/loading all videos at once.
- Updated `resources/react/modules/videos/videosApi.ts`:
  - Added `loadVideosPage(categorySlug, page, perPage)`.
  - Kept full `loadVideos()` available, but the Videos page no longer uses it for initial rendering.
- Updated `resources/react/modules/videos/VideosPage.tsx`:
  - Loads page 1 initially.
  - Appends the next page when the bottom sentinel enters the viewport.
  - Shows a spinner while loading more.
  - Keeps a manual `Load more` fallback button.
  - Shows `End of videos` once API pagination is exhausted.
- Updated `resources/react/components/AppHeader.tsx`:
  - Header dropdowns now request preview lists only.
  - Videos dropdown loads only the first 14 videos.
  - Live TV dropdown is capped to 14 channels.
  - On Demand dropdown requests 14 channels.
- Verified:
  - `npm.cmd run react:build` passes.
  - `GET /videos` returns 200.
  - First video API page reports `hasMore: true`.
- No migrations were run and no database tables were altered.

### Video Detail VAST Player Cleanup

- User reported `video-details/eric-zuley-xspannsion-interview?autoplay=1` showed a loading player and unwanted VAST UI.
- Updated `resources/react/modules/video-detail/VideoDetailPage.tsx`:
  - Removed the visible `VAST ads available` strip from the public UI.
  - Added a temporary `Preparing player` state while ad lookup finishes.
  - Prevents Video.js/IMA from initializing before the VAST lookup has completed.
- Updated `resources/react/modules/video-detail/VideoJsPlayer.tsx`:
  - Normalizes saved production VAST tag URLs to the current local origin when running on `127.0.0.1` or `localhost`.
  - Adds ad error/timeout/no-preroll fallbacks so content playback can resume instead of staying on a spinner.
- Verified:
  - `npm.cmd run react:build` passes.
  - `GET /video-details/eric-zuley-xspannsion-interview?autoplay=1` returns 200.
  - `GET /api/vast-xml/generate/1` returns 200.
  - `GET /api/vast-ads/get-active?type=video&content_id=193&video_type=full` returns VAST ad data.
- No migrations were run and no database tables were altered.

### Video Detail VAST Request Fix

- User confirmed the video plays but the VAST ad did not play.
- Compared React player behavior against the legacy player:
  - Legacy player explicitly calls `player.ima.requestAds()`.
  - React player initialized IMA but did not explicitly request ads.
- Updated `resources/react/modules/video-detail/VideoJsPlayer.tsx`:
  - Added a stable `id` to the React video element.
  - Passes that player id into `player.ima(...)`.
  - Calls `player.ima.requestAds()` after IMA initialization.
  - Requests ads again on manual play trigger if needed.
  - Prevents content autoplay from racing ahead before VAST setup when ads exist.
  - Sends `adsWillAutoplay` and `adsWillPlayMuted` hints into IMA.
- Verified:
  - VAST XML for `/api/vast-xml/generate/1` contains a valid inline MP4 ad.
  - `npm.cmd run react:build` passes.
  - `GET /video-details/eric-zuley-xspannsion-interview?autoplay=1` returns 200.
- Browser plugin visual check was unavailable in this session.
- No migrations were run and no database tables were altered.

### Direct VAST Preroll Fallback

- User reported VAST ads still did not play even after IMA request wiring.
- Updated `resources/react/modules/video-detail/VideoJsPlayer.tsx`:
  - Fetches the VAST XML directly from the configured VAST tag.
  - Parses the first `MediaFile` from the VAST XML.
  - Plays the parsed MP4 as a direct preroll before the main video.
  - Switches automatically back to the main video after the preroll ends.
  - Keeps main video tracking from firing while the preroll is playing.
  - Mutes autoplay prerolls to satisfy browser autoplay rules, then restores the previous mute state for main content.
- This is a first-party fallback path for local/dev and any browser where IMA silently skips the ad.
- Verified:
  - `npm.cmd run react:build` passes.
  - `GET /video-details/eric-zuley-xspannsion-interview?autoplay=1` returns 200.
  - `GET /api/vast-xml/generate/1` returns 200 and includes an inline MP4 ad media file.
- Browser plugin visual check was unavailable in this session.
- No migrations were run and no database tables were altered.

### Professional VAST Preroll Overlay

- User requested the direct VAST ad experience show skip button, advertisement time, and ad URL professionally like YouTube/Google ads.
- Updated `resources/react/modules/video-detail/VideoJsPlayer.tsx`:
  - Parses `AdTitle`, `Advertiser`, `Duration`, `Linear skipoffset`, `ClickThrough`, and `MediaFile` from VAST XML.
  - Shows an in-player ad overlay instead of external ad UI.
  - Displays `Advertisement` label.
  - Displays sponsor/ad title and advertiser name when available.
  - Displays countdown time while the ad plays.
  - Displays `Visit advertiser` button using the VAST click-through URL.
  - Displays disabled `Skip in N` until VAST skip offset is reached.
  - Displays enabled `Skip Ad` after the skip offset.
  - Keeps player controls hidden while the ad is playing, then restores controls for main content.
- Verified:
  - `npm.cmd run react:build` passes.
  - `GET /video-details/eric-zuley-xspannsion-interview?autoplay=1` returns 200.
  - `GET /api/vast-xml/generate/1` returns 200.
- Browser plugin visual check was unavailable in this session.
- No migrations were run and no database tables were altered.

### VAST Overlay Text Cleanup

- User said the advertisement title should not be shown.
- Updated `resources/react/modules/video-detail/VideoJsPlayer.tsx`:
  - Removed ad title and advertiser text from the top-left ad overlay.
  - Kept the `Advertisement` label, countdown, advertiser link button, and skip button.
- Verified:
  - `npm.cmd run react:build` passes.
  - `GET /video-details/eric-zuley-xspannsion-interview?autoplay=1` returns 200.
- No migrations were run and no database tables were altered.

### VAST Glass Top Bar

- User requested a glass top bar reading `Your video will resume in 11 seconds` with `Learn more >>`.
- Updated `resources/react/modules/video-detail/VideoJsPlayer.tsx`:
  - Replaced the previous top overlay with a glass-style top bar.
  - Displays `Your video will resume in N seconds`.
  - Displays `Learn more >>` using the VAST click-through URL.
  - Keeps `Advertisement` and skip controls in the lower overlay area.
- Verified:
  - `npm.cmd run react:build` passes.
  - `GET /video-details/eric-zuley-xspannsion-interview?autoplay=1` returns 200.
- No migrations were run and no database tables were altered.

### VAST Skip Button Click Fix

- User reported the skip ad control should be clickable.
- Updated `resources/react/modules/video-detail/VideoJsPlayer.tsx`:
  - Added a direct `finishPrerollRef`.
  - Skip button now calls the preroll finish handler directly instead of triggering a synthetic Video.js `ended` event.
  - This switches immediately from ad media back to the main video when skip is allowed.
- Verified:
  - `npm.cmd run react:build` passes.
  - `GET /video-details/eric-zuley-xspannsion-interview?autoplay=1` returns 200.
- No migrations were run and no database tables were altered.

### VAST Configured Duration Resume Fix

- User clarified the main video should resume based on configured ad duration, even if the ad media file is longer.
- Updated `resources/react/modules/video-detail/VideoJsPlayer.tsx`:
  - Direct VAST preroll now starts a hard timer from the parsed VAST `Duration`.
  - When configured duration expires, the player switches to the main video even if the ad MP4 has not ended.
  - Timer is cleared when the ad ends naturally or user skips the ad.
- Verified:
  - `npm.cmd run react:build` passes.
  - `GET /video-details/eric-zuley-xspannsion-interview?autoplay=1` returns 200.
- No migrations were run and no database tables were altered.

### VAST Resume Autoplay Fix

- User requested the main video autoplay after the ad resumes.
- Updated `resources/react/modules/video-detail/VideoJsPlayer.tsx`:
  - Main content handoff now switches source, calls `load()`, waits for `loadedmetadata`/`canplay`, then calls `play()`.
  - Added a short fallback play call after source switch for browsers that do not fire readiness events reliably.
- Verified:
  - `npm.cmd run react:build` passes.
  - `GET /video-details/eric-zuley-xspannsion-interview?autoplay=1` returns 200.
- No migrations were run and no database tables were altered.

### Live TV Stream Loading Flash Fix

- User reported Live TV briefly showed `No playable Live TV stream was returned` before the stream appeared.
- Updated `resources/react/modules/live-tv/LiveTvPage.tsx`:
  - Detail player now shows a `Preparing live stream` loading state while dashboard/detail APIs are still resolving.
  - The no-playable message only appears after loading finishes and no stream URL exists.
  - Watch Live remains usable during loading so a click can start playback once the full stream record arrives.
- Verified:
  - `npm.cmd run react:build` passes.
  - `GET /livetv-details/86` returns 200.
  - `GET /livetv/86` returns 200.
- No migrations were run and no database tables were altered.

### Live TV First Click Playback Fix

- User reported clicking the Live TV player/poster did not start playback until the second click, while the Watch Live button worked.
- Updated `resources/react/modules/video-detail/VideoJsPlayer.tsx`:
  - The player trigger now starts from idle `0` instead of the current prop value.
  - This lets the first `playTrigger` sent while mounting the player run immediately instead of being treated as already handled.
- Verified:
  - `npm.cmd run react:build` passes.
- No migrations were run and no database tables were altered.

### Live TV Full Schedule Fix

- User reported Live TV only showed Now and Next while Full Schedule was missing.
- Confirmed `/api/v3/livetv-details?channel_id=86` returns `full_schedule` rows, while `/api/channel-schedules?channel_id=86` returns an empty fallback list.
- Updated `resources/react/modules/live-tv/LiveTvPage.tsx`:
  - Prioritizes `channel.full_schedule` from the detail API for rendering.
  - Prevents an older empty fallback schedule request from overwriting the full schedule after detail data loads.
  - Passes `channel.schedules_url` into the fallback loader when full schedule is unavailable.
- Verified:
  - `npm.cmd run react:build` passes.
  - `GET /livetv/86` returns 200.
  - Detail API returns 72 `full_schedule` rows for channel 86.
- No migrations were run and no database tables were altered.

### Live TV Schedule Stability And Current-Only Rows

- User reported Full Schedule appeared, disappeared, then appeared again, and asked to hide past schedule rows.
- Updated `resources/react/modules/live-tv/LiveTvPage.tsx`:
  - Keeps the last valid schedule for the same channel during the lightweight channel/detail API swap.
  - Prevents temporary empty schedule states from clearing the visible Full Schedule for the same channel.
  - Filters Full Schedule rows to the current Now Playing program and future programs only.
  - Added schedule date parsing for the API `YYYY-MM-DD HH:mm:ss` format.
- Verified:
  - `npm.cmd run react:build` passes.
  - Channel 86 API returns 72 schedule rows; UI filter keeps 64 rows from the current program onward.
- No migrations were run and no database tables were altered.

### Navbar Search, Distribution, And Join CTA

- User requested a navbar search option, a separate search results page, Distribution link, and Join Our Family CTA.
- Updated `resources/react/components/AppHeader.tsx`:
  - Search icon now links to `/search`.
  - Added `Distribution` nav item linking to `https://ezway.tv/distribution`.
  - Added `Join Our Family` CTA linking to `https://ezwaynetwork.com/`.
- Added React search module:
  - `resources/react/modules/search/SearchPage.tsx`
  - `resources/react/modules/search/searchApi.ts`
- Search page uses existing `/api/v3/get-search-data` endpoint and renders clean React result cards for:
  - Videos
  - Live TV
  - On Demand channels
- Updated `resources/react/App.tsx` so `/search` is SPA-owned.
- Verified:
  - `npm.cmd run react:build` passes.
  - `GET /search?q=ezway` returns 200.
  - `/api/v3/get-search-data?search=ezway&is_ajax=1&per_page=12` returns result HTML and On Demand data.
- No migrations were run and no database tables were altered.

### Distribution SPA Page And Navbar Search Size

- User reported the search icon looked too tiny and clarified `/distribution` already has previous Laravel module data.
- Updated `resources/react/components/AppHeader.tsx`:
  - Search is now a larger labeled navbar action.
  - Distribution now links to local `/distribution` instead of the external production URL.
- Added React Distribution module:
  - `resources/react/modules/distribution/DistributionPage.tsx`
  - `resources/react/modules/distribution/distributionApi.ts`
- Distribution page uses existing `/api/distribution` JSON data from `Modules/Frontend/Resources/data/distribution.json`.
- Updated `resources/react/App.tsx` so `/distribution` is SPA-owned.
- Verified:
  - `npm.cmd run react:build` passes.
  - `GET /distribution` returns 200.
  - `/api/distribution` returns 22 network/platform entries.
- No migrations were run and no database tables were altered.

### Navbar Search Collapse Fix

- User showed the navbar search still rendering as a tiny icon.
- Updated `resources/react/components/AppHeader.tsx`:
  - Replaced the header search control's shared button wrapper with a plain fixed-width anchor button.
  - Search now always shows icon plus `Search` text in the header.
- Verified:
  - `npm.cmd run react:build` passes.
  - `GET /distribution` returns 200.
- No migrations were run and no database tables were altered.

### Distribution Layout Parity Pass

- User pointed out the previous Laravel Distribution page had richer markup than the first React version.
- Reworked `resources/react/modules/distribution/DistributionPage.tsx` to follow the old Blade page structure:
  - Centered eZWay TV Distribution hero.
  - Gold reach bar.
  - Los Angeles Station section.
  - Channel 27.3 copy and RabbitEars coverage iframe.
  - Dense Network Partners grid using existing `/api/distribution` data.
  - Major platforms chip strip.
- Verified:
  - `npm.cmd run react:build` passes.
  - `GET /distribution` returns 200.
  - `/api/distribution` returns 22 network/platform entries.
- No migrations were run and no database tables were altered.

### Redis Cache Pass

- Switched local cache driver from file to Redis and updated `.env.example` to use `CACHE_DRIVER=redis` with Predis defaults.
- Started the Laragon Redis server locally and verified Laravel reports `cache.default` as `redis`.
- Added Redis-backed caching for repeated public SPA reads:
  - App settings lookup.
  - On Demand channel list, profile, and channel videos.
  - Distribution JSON payload.
  - Live TV channel schedules, with cache clearing after schedule create/update/delete.
  - Public video details payloads.
  - Cast/crew list payloads.
- Kept user-specific video detail requests uncached so continue-watch, watchlist, likes, and download flags stay live.
- Verified:
  - `php artisan config:clear` passes.
  - `php artisan cache:clear` passes after Redis is started.
  - `php artisan config:show cache.default` returns `redis`.
  - PHP lint passes for touched controllers/routes.
  - `npm.cmd run react:build` passes.
- No migrations were run and no database tables were altered.

### Distribution Partner Card Styling

- User requested Network Partners use a card style.
- Updated `resources/react/modules/distribution/DistributionPage.tsx`:
  - Replaced the continuous dense partner grid surface with individually separated cards.
  - Added card borders, rounded corners, shadow, hover lift, logo frame, and gold top accent.
- Verified:
  - `npm.cmd run react:build` passes.
  - `GET /distribution` returns 200.
- No migrations were run and no database tables were altered.

### Live TV Detail Player Reset Fix

- User reported `/livetv/trailblazers-tv` blinked and showed `Preparing live stream` twice.
- Updated `resources/react/modules/live-tv/LiveTvPage.tsx`:
  - Split player reset logic away from schedule/detail enrichment logic.
  - Player state now resets only when the channel id changes, not when the same channel receives fuller detail/schedule data.
- Verified:
  - `npm.cmd run react:build` passes.
  - `GET /livetv/trailblazers-tv` returns 200.
- No migrations were run and no database tables were altered.

### Live TV Full Schedule Collapse Toggle

- User requested Full Schedule be collapsible.
- Updated `resources/react/modules/live-tv/LiveTvPage.tsx`:
  - Now/Next remain visible.
  - Full Schedule is collapsed by default.
  - Added toggle button with current/upcoming program count and chevron state.
- Verified:
  - `npm.cmd run react:build` passes.
  - `GET /livetv/trailblazers-tv` returns 200.
- No migrations were run and no database tables were altered.

### React Test Site Setup And Current Frontend Pass

- User requested the live Laravel site be cloned for testing at `/var/www/react.ezway.tv` while keeping the database separate.
- Set up the test Laravel/React instance from the existing eZWay TV codebase, preserving Git history and using the `Laravel_React_Migration` branch after the GitHub repository was made public.
- Configured the test app to use a separate database, `ezwayott_test`, so frontend modernization work does not alter the live database.
- Brought the `react.ezway.tv` host online and resolved early server issues:
  - Fixed `403 Forbidden` nginx serving.
  - Fixed Cloudflare/nginx redirect loop.
  - Confirmed the test host returns HTTP 200 after cache/config cleanup.
- Added app-wide TanStack Query usage for React API reads and mutations:
  - Wrapped the React app in `QueryClientProvider`.
  - Migrated API usage beyond video detail, including header, home, videos, search, distribution, genres, on-demand, cast/crew, live TV, and video detail flows.
- Updated video detail sharing:
  - Added a broader share menu for major social networks, email, SMS, native device share, and copy link.
- Updated Git hygiene:
  - Added generated Vite build output under `public/build` to `.gitignore`.
  - Removed build artifacts from Git tracking while leaving generated files available for the deployed test site.
- Updated header and mobile navigation:
  - Removed the Profile action for now.
  - Added a responsive burger menu for mobile.
  - Included the current public site menu items: Home, Movies, TV Shows, Videos, On Demand, Live TV, Distribution, eZWay PPV, and Join Our Family.
  - Made mobile menu sections collapsible for Videos, On Demand, and Live TV.
  - Reworked the mobile drawer so it overlays the page instead of pushing into and overlapping hero/content areas.
- Reduced reload visual noise:
  - Replaced the default red theme flash with a neutral/blue fallback.
  - Changed the loading logo fallback to white branding text.
  - Removed the red `Loading from API` visual treatment from the home hero state.
- Current verification:
  - `npm run react:build` passed after the frontend changes.
  - Laravel config/cache cleanup completed during deployment checks.
  - Local status checks still show permission warnings for Laravel runtime folders owned by the web user, but tracked frontend files are readable and editable.
- Going forward, all major modernization work and verification notes should be recorded in this file.

### Mobile Drawer Width Overflow Fix

- User reported the mobile menu dropdown items were breaking the viewport width, especially long video titles inside the Videos section.
- Updated `resources/react/components/AppHeader.tsx`:
  - Constrained the mobile drawer to `100dvw`.
  - Added `w-full`, `min-w-0`, `max-w-full`, and `overflow-hidden` guards to the drawer shell, menu sections, dropdown wrappers, and list rows.
  - Kept thumbnails fixed-width and forced text columns to truncate inside the available row width.
  - Reduced the mobile search submit column from 68px to 64px to give the input more safe space on narrow screens.
- Updated `resources/react/styles.css`:
  - Added a page-level `overflow-x: hidden` guard to prevent accidental side-scroll from long API content.
- Verified:
  - `npm run react:build` passes.
- No migrations were run and no database tables were altered.

### Mobile Header Search Cleanup

- User requested removing the navbar search bar on mobile because the burger menu already includes search.
- Updated `resources/react/components/AppHeader.tsx`:
  - Hid the header search action on mobile.
  - Kept the header search visible on desktop/tablet widths.
- Updated `resources/react/styles.css`:
  - Changed the `.ez-header-search` helper so it does not override mobile hiding with `display: inline-flex !important`.
  - Restored `inline-flex` only at `md`/768px and above.
- Verified:
  - `npm run react:build` passes.
- No migrations were run and no database tables were altered.

### Mobile Header Search Hide Follow-Up

- User reported the mobile navbar search was still showing after the first cleanup.
- Updated `resources/react/components/AppHeader.tsx`:
  - Renamed the header search styling hook from `.ez-header-search` to `.ez-header-search-desktop` so old display rules cannot keep the mobile search visible.
- Updated `resources/react/styles.css`:
  - Scoped desktop search button sizing/display to `.ez-header-search-desktop`.
  - Added an explicit max-width mobile media rule hiding both the old and new search hooks below 768px.
- Verified:
  - `npm run react:build` passes and generated fresh Vite assets.
  - Built CSS contains `.ez-header-search-desktop` plus the mobile `display: none !important` rule.
- No migrations were run and no database tables were altered.

### Burger Menu Search Wiring Fix

- User reported the search inside the burger menu was not working and should trigger the main search behavior.
- Updated `resources/react/components/AppHeader.tsx`:
  - Wired the mobile drawer search form to `useSpaNavigate`.
  - Prevented the native form reload and now navigates directly to `/search?q=<term>`.
  - Closes the drawer after submitting the search.
- Updated `resources/react/modules/search/SearchPage.tsx`:
  - Added URL-query synchronization so the main search input, submitted query, results state, and active filter update when navigation changes the search URL.
  - This covers searching from the burger menu while already on the Search page.
- Verified:
  - `npm run react:build` passes and generated fresh Vite assets.
- No migrations were run and no database tables were altered.

### Mobile Video Detail Layout Reorder

- User reported the mobile video detail page felt disorganized because the title/details appeared before the video player.
- Updated `resources/react/modules/video-detail/VideoDetailPage.tsx`:
  - Reordered the mobile layout so the video player appears first.
  - Kept the desktop two-column order unchanged with title/details on the left and player on the right.
  - Reduced mobile hero padding/gap so the player and details sit together more cleanly.
  - Matched the loading skeleton order to the new mobile layout.
- Verified:
  - `npm run react:build` passes and generated fresh Vite assets.
- No migrations were run and no database tables were altered.

### Mobile Video Detail Title Size Adjustment

- User requested the video detail title be reduced a bit on mobile.
- Updated `resources/react/modules/video-detail/VideoDetailPage.tsx`:
  - Reduced the mobile title size from `text-3xl` to `text-2xl`.
  - Kept tablet and desktop title sizing unchanged.
- Verified:
  - `npm run react:build` passes and generated fresh Vite assets.
- No migrations were run and no database tables were altered.

### Mobile Live TV Detail Layout Reorder

- User requested the same mobile organization cleanup for live channel detail pages.
- Updated `resources/react/modules/live-tv/LiveTvPage.tsx`:
  - Reordered the mobile live channel detail layout so the player appears before the title/details.
  - Reduced the live channel detail title from `text-3xl` to `text-2xl` on mobile.
  - Kept the desktop two-column order and larger title sizing unchanged.
  - Reduced mobile hero padding/gap to match the video detail page treatment.
- Verified:
  - `npm run react:build` passes and generated fresh Vite assets.
- No migrations were run and no database tables were altered.

### Video Detail Share UI Cleanup

- User reported the video detail share popover looked broken and requested icon-based share actions instead of visible social network names.
- Updated `resources/react/modules/video-detail/VideoDetailPage.tsx`:
  - Reworked the share popover into a compact icon-only grid.
  - Kept accessible labels and browser tooltips through `aria-label`, `title`, and screen-reader-only text.
  - Reduced the popover width and anchored it safely on mobile so it does not spill off the viewport.
  - Added subtle per-network hover treatments while keeping the implementation dependency-free with existing lucide icons.
  - Kept device share and copy-link actions as icon buttons.
- Verified:
  - `npm run react:build` passes and generated fresh Vite assets.
- No migrations were run and no database tables were altered.

### Video Detail Share Brand Icon Fix

- User reported the icon and share site did not match after the first icon-only pass.
- Updated `resources/react/modules/video-detail/VideoDetailPage.tsx`:
  - Replaced generic lucide social icons with local inline brand marks for Facebook, X, WhatsApp, Telegram, LinkedIn, Reddit, and Pinterest.
  - Kept lucide icons for non-brand actions: email, SMS, device share, and copy link.
  - Avoided adding a new icon dependency.
- Verified:
  - `npm run react:build` passes and generated fresh Vite assets.
- No migrations were run and no database tables were altered.

### Homepage Hero Poster Image Priority

- User reported the homepage hero direction did not look good and requested using poster image artwork instead.
- Updated `resources/react/modules/home/HomePage.tsx`:
  - Kept the simpler homepage hero layout.
  - Changed hero artwork priority to use `poster_image` first, with `poster_tv_image` as fallback.
- Verified:
  - `npm run react:build` passes and generated fresh Vite assets.
- No migrations were run and no database tables were altered.

### Homepage Hero Black Gold Gradient Pass

- User requested using a nicer black/gold gradient instead of the previous poster-led hero direction.
- Updated `resources/react/modules/home/HomePage.tsx`:
  - Made the homepage hero primarily a black/gold gradient treatment.
  - Kept poster artwork as a subtle desktop supporting layer instead of the dominant background.
  - Replaced internal pilot copy with viewer-facing eZWay TV streaming copy.
  - Updated CTA labels to `Watch Now` and `Details`.
- Verified:
  - `npm run react:build` passes and generated fresh Vite assets.
- No migrations were run and no database tables were altered.

### Homepage Hero Logo Background Pass

- User requested using the TV logo instead of content pictures in the homepage hero.
- Updated `resources/react/modules/home/HomePage.tsx`:
  - Removed the hero poster/photo background layer.
  - Added the configured eZWay TV logo/mini-logo as a subtle oversized decorative brand layer on desktop.
  - Kept the black/gold gradient as the primary hero visual treatment.
- Verified:
  - `npm run react:build` passes and generated fresh Vite assets.
- No migrations were run and no database tables were altered.

### Homepage Hero Density And Brand Panel Pass

- User shared a screenshot showing the homepage hero felt too dull and empty.
- Updated `resources/react/modules/home/HomePage.tsx`:
  - Reduced the hero height from 82vh to 70vh to remove excess empty dark space.
  - Reworked the black/gold background into stronger angled bands.
  - Added a desktop logo feature panel with a gold accent and simple Live TV / On Demand / Videos service strip.
  - Moved hero content to vertical center alignment for better balance.
  - Pulled the first rail slightly closer to the hero.
- Verified:
  - `npm run react:build` passes and generated fresh Vite assets.
- No migrations were run and no database tables were altered.

### Homepage Hero Provided Image Pass

- User requested using a specific DigitalOcean Spaces image for the homepage hero.
- Updated `resources/react/modules/home/HomePage.tsx`:
  - Added the provided image URL as the fixed homepage hero visual.
  - Used the image as a subtle full-hero background layer and as the desktop feature card image.
  - Kept black/gold overlays for readability and brand consistency.
  - Removed the previous logo-panel hero treatment.
- Verified:
  - `npm run react:build` passes and generated fresh Vite assets.
- No migrations were run and no database tables were altered.

### Homepage Hero Simplified Image Pass

- User requested removing the square logo box and gold line from the homepage hero and using only a specific logo image.
- Updated `resources/react/modules/home/HomePage.tsx`:
  - Replaced the prior hero image URL with `https://ezwayott.sfo3.digitaloceanspaces.com/logos/image/caa4d6ec_3f9c_4f51_8e9c_95153c5d2b98_6a16d19e8d157.jpg`.
  - Removed the desktop feature card/square box.
  - Removed the angled gold stripe overlay.
  - Kept a dark readability overlay and bottom fade so text remains legible.
- Verified:
  - `npm run react:build` passes and generated fresh Vite assets.
- No migrations were run and no database tables were altered.

### Live TV Route 500 Env Fix

- User reported `https://react.ezway.tv/livetv/ezway-tv` returned HTTP 500 after the Live TV stream resolver work.
- Root cause:
  - Laravel could not parse `.env` because `MIX_ASSET_URL` had an invalid value with whitespace: `https://react.avbar ezway.tv`.
  - The invalid `.env` made all React web routes return 500 before Laravel could render the SPA view.
- Updated `.env`:
  - Corrected `MIX_ASSET_URL` to `https://react.ezway.tv`.
- Server cleanup:
  - Cleared Laravel config/cache.
  - Restored Laravel runtime ownership on `storage` and `bootstrap/cache` to `www-data:www-data`.
- Verified:
  - Public `https://react.ezway.tv/livetv/ezway-tv` returns HTTP 200.
  - Local origin with Cloudflare forwarding headers returns HTTP 200.
  - `php artisan about --only=environment` runs successfully.
  - `/api/v3/livetv-details?channel_id=ezway-tv` returns a valid HLS stream URL for eZWay TV.
- No migrations were run and no database tables were altered.

### Header Logo Loading Flash Fix

- User reported the header briefly showed the text logo during reload before the image logo appeared.
- Root cause:
  - `BrandLogo` rendered the text fallback while the branding API was still loading.
- Updated `resources/react/components/BrandLogo.tsx`:
  - Added a quiet fixed-size black/gold loading placeholder while branding is loading.
  - Kept the text fallback only for the real logo-failed/no-logo state after loading.
- Updated `resources/react/components/AppHeader.tsx`:
  - Passed header and mobile drawer placeholder sizes to prevent layout jump.
- Verified:
  - `npm run react:build` passes and generated fresh Vite assets.
- No migrations were run and no database tables were altered.

### Header Hardcoded Logo Fallback

- User provided a hardcoded white eZWay TV logo URL to prevent the header from ever flashing text branding.
- Updated `resources/react/components/BrandLogo.tsx`:
  - Added `https://ezwayott.sfo3.digitaloceanspaces.com/logos/image/ezwaytv_white_6a26f75c71a3d.png` as the immediate fallback logo.
  - The branding API logo still wins when available.
  - Text fallback now only appears if both the configured logo and hardcoded fallback image fail.
- Verified:
  - `npm run react:build` passes and generated fresh Vite assets.
- No migrations were run and no database tables were altered.

### Navbar Active Border State

- User requested changing the navbar active item to use a bottom border.
- Updated `resources/react/components/AppHeader.tsx`:
  - Replaced the filled white active nav pill with a gold bottom-border active state.
  - Kept inactive nav items transparent with subtle hover border/text changes.
- Verified:
  - `npm run react:build` passes and generated fresh Vite assets.
- No migrations were run and no database tables were altered.

### Mobile Navbar Premium Drawer Redesign

- User provided a reference screenshot for the mobile navbar/drawer design.
- Updated `resources/react/components/AppHeader.tsx`:
  - Rebuilt the mobile drawer with a premium black/gold gradient surface.
  - Enlarged the logo and close button treatment.
  - Reworked search into a large rounded input plus gold search button.
  - Added icon-led mobile nav rows with active gold bordered state.
  - Added mobile-only Movies and TV Shows rows to match the reference without changing the desktop nav.
  - Kept Videos, On Demand, and Live TV collapsible with preview items.
  - Added a polished `Join Our Family` callout card.
  - Added circular social shortcut buttons.
- Verified:
  - `npm run react:build` passes and generated fresh Vite assets.
- No migrations were run and no database tables were altered.

### Mobile Drawer Typography Tuning

- User requested decreasing the mobile navbar font size.
- Updated `resources/react/components/AppHeader.tsx`:
  - Reduced mobile nav row labels from `text-xl` to `text-lg`.
  - Reduced search input text from `text-lg` to `text-base`.
  - Reduced main nav chevrons/icons slightly.
  - Reduced `Join Our Family` card heading from `text-xl` to `text-lg`.
- Verified:
  - `npm run react:build` passes and generated fresh Vite assets.
- No migrations were run and no database tables were altered.

### Mobile Drawer Compact Typography Pass

- User said the mobile drawer was still too large and requested a much smaller treatment.
- Updated `resources/react/components/AppHeader.tsx`:
  - Reduced mobile logo, close button, search field, search button, row heights, labels, icons, chevrons, preview rows, join card, and social buttons.
  - Main mobile nav labels now use `text-sm`.
  - Search input now uses `text-sm`.
  - Drawer rows are reduced from 64px minimum height to 48px.
  - Join card and social buttons are slimmer.
- Verified:
  - `npm run react:build` passes and generated fresh Vite assets.
- No migrations were run and no database tables were altered.
