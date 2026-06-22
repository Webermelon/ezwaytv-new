# Database Audit

Date: 2026-06-09

## Purpose

This document records the database baseline for the Laravel + React modernization. The frontend migration must not alter existing tables unless explicitly approved.

## Current Rule

- Do not run migrations automatically.
- Do not alter existing tables.
- Do not drop or rename columns.
- Do not change indexes without approval.
- Treat pending migrations as review items, not implementation permission.

## Known Environment

From the read-only scan on 2026-06-08:

- Database driver: MySQL
- MySQL version: 8.0.30
- Database name: `ezwayott`
- Table count: 106
- Approximate database size: 10.22 MB
- Main application user table: `users`
- Queue driver: database
- Session driver: file

On 2026-06-09, MySQL was temporarily not accepting connections from the shell. It later came back online and the schema refresh was completed.

## Known Large / Active Tables

From the 2026-06-08 read-only database summary:

- `stat_page_views`: about 3.12 MB
- `notifications`: about 1.61 MB
- `stat_play_events`: about 1.52 MB
- `entertainment_talent_mapping`: about 320 KB
- `videos`: about 176 KB
- `filemanagers`: about 176 KB
- `pages`: about 144 KB
- `entertainments`: about 112 KB
- `episodes`: about 96 KB
- `jobs`: about 96 KB

## Important Domain Tables

Content and playback:

- `entertainments`
- `videos`
- `seasons`
- `episodes`
- `live_tv_channel`
- `live_tv_category`
- `livetvs`
- `continue_watch`
- `watchlists`
- `likes`
- `reviews`
- `entertainment_views`
- `stat_page_views`
- `stat_play_events`

Commerce:

- `plan`
- `planlimitation`
- `planlimitation_mapping`
- `subscriptions`
- `subscriptions_transactions`
- `pay_per_views`
- `payperviewstransactions`
- `coupons`
- `coupon_subscription_plan`
- `user_coupon_redeem`

Users and auth:

- `users`
- `user_profiles`
- `user_multi_profiles`
- `user_providers`
- `personal_access_tokens`
- `devices`
- `tv_login_sessions`
- `web_qr_sessions`
- `sessions`

CMS and configuration:

- `settings`
- `setting`
- `mobile_settings`
- `pages`
- `faqs`
- `seo`
- `banners`
- `ad_banner_slides`
- `ads`
- `video_ads`
- `vast_ads_setting`
- `custom_ads_setting`

Taxonomy and metadata:

- `genres`
- `categories`
- `video_category_mapping`
- `cast_crew`
- `languages`
- `countries`
- `states`
- `cities`
- `currencies`
- `taxes`
- `constants`

## Users Table Snapshot

From the 2026-06-08 read-only `php artisan db:table users` scan:

- Columns: 28
- Engine: InnoDB
- Collation: `utf8mb4_unicode_ci`
- Primary key: `id`
- Unique index: `users_email_unique` on `email`
- Soft delete index: `users_deleted_at_index` on `deleted_at`

Notable columns:

- `id`
- `username`
- `first_name`
- `last_name`
- `email`
- `mobile`
- `login_type`
- `file_url`
- `gender`
- `date_of_birth`
- `email_verified_at`
- `password`
- `is_banned`
- `is_subscribe`
- `country_code`
- `status`
- `is_network_user`
- `network_user_id`
- `last_notification_seen`
- `address`
- `user_type`
- `pin`
- `otp`
- `is_parental_lock_enable`
- `remember_token`
- `created_at`
- `updated_at`
- `deleted_at`

## Genres Table Snapshot

From the 2026-06-09 read-only `php artisan db:table genres` scan:

- Columns: 12
- Size: 32 KB
- Engine: InnoDB
- Collation: `utf8mb4_unicode_ci`
- Primary key: `id`
- Compound index: `genres_id_deleted_at_index` on `id, deleted_at`

Notable columns:

- `id`
- `name`
- `slug`
- `file_url`
- `description`
- `status`
- `created_by`
- `updated_by`
- `deleted_by`
- `created_at`
- `updated_at`
- `deleted_at`

## Core Content Table Snapshots

### `entertainments`

- Columns: 55
- Size: 112 KB
- Primary key: `id`
- Important indexes:
  - `entertainments_type_index`
  - `entertainments_status_index`
  - `entertainments_release_date_index`
  - `entertainments_id_status_release_date_index`
  - `entertainments_id_status_release_date_deleted_at_index`
- Notable domains:
  - media identity: `name`, `slug`, `tmdb_id`, `type`
  - images: `thumbnail_url`, `poster_url`, `poster_tv_url`, `seo_image`
  - playback: `video_upload_type`, `video_url_input`, `video_quality_url`, `bunny_video_url`, `bunny_trailer_url`
  - access: `movie_access`, `plan_id`, `price`, `purchase_type`, `access_duration`, `available_for`
  - SEO: `meta_title`, `meta_keywords`, `meta_description`, `canonical_url`

### `videos`

- Columns: 53
- Size: 176 KB
- Primary key: `id`
- Foreign key:
  - `creator_channel_id` references `creator_channels.id`, on delete set null
- Notable domains:
  - media identity: `name`, `slug`, `type`
  - images: `poster_url`, `thumbnail_url`, `poster_tv_url`, `seo_image`
  - playback: `video_upload_type`, `video_url_input`, `bunny_video_url`
  - access: `access`, `plan_id`, `price`, `purchase_type`, `access_duration`, `available_for`
  - creator workflow: `creator_channel_id`

### `episodes`

- Columns: 56
- Size: 96 KB
- Primary key: `id`
- Important indexes:
  - `episodes_entertainment_id_index`
  - `episodes_season_id_index`
  - `episodes_status_index`
  - `episodes_deleted_at_index`
- Notable domains:
  - parent links: `entertainment_id`, `season_id`
  - episode identity: `name`, `slug`, `tmdb_id`, `tmdb_season`, `episode_number`
  - images: `poster_url`, `poster_tv_url`, `seo_image`
  - playback: `video_upload_type`, `video_url_input`, `video_quality_url`, `bunny_video_url`, `bunny_trailer_url`
  - access: `access`, `plan_id`, `price`, `purchase_type`, `access_duration`, `available_for`

### `live_tv_channel`

- Columns: 20
- Size: 48 KB
- Primary key: `id`
- Notable domains:
  - channel identity: `name`, `slug`, `category_id`
  - images: `poster_url`, `thumb_url`, `poster_tv_url`
  - access: `access`, `plan_id`
  - engagement: `stream_views`, `stream_plays`
  - live chat: `enable_live_chat`

## Commerce Table Snapshots

### `subscriptions`

- Columns: 26
- Size: 16 KB
- Primary key: `id`
- Notable domains:
  - user and plan: `user_id`, `plan_id`, `name`, `identifier`, `level`, `plan_type`
  - timing: `start_date`, `end_date`, `duration`, `type`
  - payment: `amount`, `discount_percentage`, `tax_amount`, `coupon_discount`, `total_amount`, `payment_id`
  - device: `device_id`

Subscription webhook behavior:

- `/select-plan` creates a local `pending` subscription before external payment confirmation.
- `POST /api/subscription/webhook` changes the existing local subscription status after external confirmation.
- Activating a subscription sets other active subscriptions for the same user to `deactivated`.
- Webhook status handling does not require a migration or new columns.

### `subscriptions_transactions`

Webhook transaction behavior:

- `/select-plan` creates a pending transaction with `payment_type` set to `external_webhook`.
- The webhook updates the transaction for the same `subscriptions_id`.
- The webhook stores the raw incoming payload in `other_transactions_details`.
- Paid/active webhook statuses set `payment_status` to `paid`; other supported statuses are stored as their local mapped status.

### `pay_per_views`

- Columns: 13
- Size: 48 KB
- Primary key: `id`
- Foreign keys:
  - `user_id` references `users.id`, on delete cascade
  - `movie_id` references `entertainments.id`, on delete cascade
- Notable domains:
  - user and content: `user_id`, `movie_id`, `type`
  - pricing: `content_price`, `price`, `discount_percentage`
  - access window: `first_play_date`, `view_expiry_date`, `access_duration`, `available_for`

## CMS Table Snapshot

### `pages`

- Columns: 14
- Size: 144 KB
- Primary key: `id`
- Unique index: `pages_slug_unique` on `slug`
- Notable domains:
  - page identity: `name`, `slug`, `content_type`, `sequence`
  - content: `description`, `embed_code`
  - status and audit: `status`, `created_by`, `updated_by`, `deleted_by`

## Pending Migrations

Known pending migrations from the 2026-06-08 scan:

- `2026_06_03_000001_create_music_video_submissions_table`
- `2026_06_03_000002_add_channel_and_purchase_fields_to_music_video_submissions_table`
- `2026_06_03_000003_add_soft_deletes_to_music_video_submissions_table`
- `2026_06_08_000001_add_channel_id_to_statistics_events`

These must not be run until explicitly approved.

## Refresh Checklist

When deeper schema detail is needed, refresh this document with:

```bash
php artisan db:show
php artisan migrate:status
php artisan db:table users
php artisan db:table entertainments
php artisan db:table videos
php artisan db:table episodes
php artisan db:table seasons
php artisan db:table live_tv_channel
php artisan db:table subscriptions
php artisan db:table pay_per_views
```

Also generate relationship notes from:

- model `$fillable`
- model relationships
- migration foreign keys
- soft delete usage
- resource transformers

## Modernization Notes

- React migration should consume existing API fields as-is.
- Frontend TypeScript types should be generated from observed API responses and model/resource contracts.
- Schema changes should be handled as a separate backend task, never as a side effect of shadcn redesign.
- High-write tables such as statistics and notifications should be treated carefully during dashboard/API work.
