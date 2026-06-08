# Statistics Booster Notes

This document captures how Content Booster, Graph Spike Boost, and Watch Time Boost currently work.

## 1) Graph Spike Boost: When It Triggers

Spike injection is applied when the boosted target is greater than raw data total for a dataset.

Flow used in chart/breakdown endpoints:
1. Build raw dataset from real records.
2. Compute target total with `boostedTargetTotal(rawTotal, metric)`.
3. Compute extra delta: `extra = target - raw`.
4. If `extra > 0`, distribute with `injectSpikeBoost(...)` for realistic spikes.

Main methods:
- `chart()` for views/plays time series
- `devices()`
- `countries()`
- `platforms()`
- `traffic()`

Helper methods:
- `boostedTargetTotal(int $rawTotal, string $metric)`
- `injectSpikeBoost(array $series, int $extra, string $seed, array $labelBias = [])`

## 2) What Is Included In Boosted Target

`boostedTargetTotal(...)` includes:
- Content boosts from `stat_content_boosts`:
  - `SUM(boost_plays)` for plays metric
  - `SUM(boost_views)` for views metric
- Then applies global boost settings via `applyBoost(...)` if enabled:
  - `boost_enabled`
  - `boost_multiplier`
  - `boost_fixed_plays`
  - `boost_fixed_views`

Important:
- Content Booster alone can trigger spikes even if global boost is off.
- Global boost affects totals and can further increase spike delta.

## 3) Watch Time Booster (Individual + Total)

Watch-time boost was added using a new column:
- `stat_content_boosts.boost_watch_seconds`

Used in:
- Content booster entry save/history/stats
- Overview total watch hours (`watch_hours`) in Statistics dashboard

Overview behavior:
- Real watch seconds from `stat_play_events.watch_seconds`
- Plus content boost watch seconds sum
- Converted to hours and returned as `watch_hours`

## 3.1) Unique Visitor Booster (Individual + Total)

Unique visitor boost was added using a new column:
- `stat_content_boosts.boost_unique_visitors`

Used in:
- Content booster entry save/history/stats
- Content booster item display cards (real vs display unique visitors)
- Overview unique visitors total in Statistics dashboard

Overview behavior:
- Real unique visitors from distinct `stat_page_views.ip_address`
- Plus content boost unique visitors sum
- Result is returned in `unique_visitors`

## 4) Why Watch Time Could Show 0

Two common reasons:
1. Migration not applied, so `boost_watch_seconds` column does not exist.
   - In this case code safely falls back to 0 to avoid 500 errors.
2. Input unit mismatch (seconds vs minutes).
   - Booster UI now takes watch time in minutes and converts to seconds before submit.

## 5) Required Migration

Run this once to enable watch-time persistence:

```bash
php artisan migrate --path=Modules/Statistics/database/migrations/2026_04_24_000004_add_boost_watch_seconds_to_stat_content_boosts.php --force
```

Status check:

```bash
php artisan migrate:status --path=Modules/Statistics/database/migrations/2026_04_24_000004_add_boost_watch_seconds_to_stat_content_boosts.php
```

Expected status: `Ran`.

Run this once to enable unique-visitor boost persistence:

```bash
php artisan migrate --path=Modules/Statistics/database/migrations/2026_04_24_000005_add_boost_unique_visitors_to_stat_content_boosts.php --force
```

Status check:

```bash
php artisan migrate:status --path=Modules/Statistics/database/migrations/2026_04_24_000005_add_boost_unique_visitors_to_stat_content_boosts.php
```

Expected status: `Ran`.

## 6) Current UI Notes

In Content Booster page:
- Watch input is minutes (admin-friendly).
- Hidden payload sent to backend is `boost_watch_seconds`.
- Display formatter shows:
  - `h m` for hour-level values
  - `m s` for minute-level values
  - `s` for sub-minute values

This avoids showing misleading `0h 0m` for small values.

## 7) Scope Clarification

Graph spike logic currently affects plays/views datasets and breakdown charts.
Watch-time booster affects total watch metrics and item watch display/history, not a separate watch-time spike chart.

## 8) Country Bias Tuning (Client-Facing)

Country spike distribution was tuned for client-facing realism:
- USA is weighted higher for boosted country traffic.
- Bangladesh (BD) is weighted lower to reduce developer-origin bias in boosted results.
- Unknown traffic is slightly downweighted.

Configured country bias map:
- `US`, `USA`, `UNITED STATES` => higher weight
- `BD`, `BANGLADESH` => lower weight
- `UNKNOWN` => lower weight

Implementation note:
- Label-bias matching is case-insensitive in spike injection.

## 9) Current Baseline Status

Applied and active:
- Watch-time boost migration: `2026_04_24_000004_add_boost_watch_seconds_to_stat_content_boosts`
- Unique visitor boost migration: `2026_04_24_000005_add_boost_unique_visitors_to_stat_content_boosts`
- On Demand channel analytics migration: `2026_06_08_000001_add_channel_id_to_statistics_events`
- Content Booster supports plays, views, watch-time, and unique visitors.
- Overview totals include plays/views/watch-time/unique-visitor content boosts.
- Graph spike boost is active for chart and traffic breakdown endpoints.
- On Demand channel reporting includes:
  - `ondemand_channel` profile views.
  - `ondemand_video` views/plays with `channel_id` when traffic comes from an On Demand channel.
  - Normal `video` views/plays for videos assigned through `author_channel_video`, so general video page traffic still rolls up into the owning On Demand channel report.
  - `ondemand_channel` Content Booster entries, which add channel-level views, plays, watch time, and unique visitors to the On Demand dashboard row.

Use this file as the baseline before new changes.
