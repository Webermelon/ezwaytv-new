<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'facebook' => [
        'client_id' => env('FACEBOOK_CLIENT_ID'),
        'client_secret' => env('FACEBOOK_CLIENT_SECRET'),
        'redirect' => env('FACEBOOK_REDIRECT'),
    ],

    'github' => [
        'client_id' => env('GITHUB_CLIENT_ID'),
        'client_secret' => env('GITHUB_CLIENT_SECRET'),
        'redirect' => env('GITHUB_REDIRECT'),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' =>  env('GOOGLE_CLIENT_SECRET'),
        'redirect' =>   env('GOOGLE_REDIRECT_URI' ),
    ],

    'apple' => [
        'client_id' => env('APPLE_CLIENT_ID'),
        'client_secret' => env('APPLE_CLIENT_SECRET'),
        'redirect' => env('APPLE_REDIRECT_URI'),
        'team_id' => env('APPLE_TEAM_ID'),
        'key_id' => env('APPLE_KEY_ID'),
        'private_key' => env('APPLE_PRIVATE_KEY'),
    ],

    'wordpress_sso' => [
        'secret' => env('WORDPRESS_SSO_SECRET'),
        'issuer' => env('WORDPRESS_SSO_ISSUER', env('NETWORK_WP_SITEURL')),
    ],

    'subscription_webhook' => [
        'secret' => env('SUBSCRIPTION_WEBHOOK_SECRET'),
    ],



    'core_private_api' => [
        'token' => env('CORE_PRIVATE_API_TOKEN'),
        'secret' => env('CORE_PRIVATE_API_SECRET'),
    ],

    'core_api' => [
        'base_url' => env('CORE_API_URL', 'https://ezwaycore.webermelon.dev'),
        'token' => env('CORE_API_TOKEN'),
        'host' => env('CORE_API_HOST'),
        'tv_package_slug' => env('CORE_TV_PACKAGE_SLUG', 'tv-subscription-monthly'),
    ],

    'media_compressor' => [
        'enabled' => env('MEDIA_COMPRESSOR_API_ENABLED', false),
        'url' => env('MEDIA_COMPRESSOR_API_URL', 'https://media-compressor.ezway.io'),
        'token' => env('MEDIA_COMPRESSOR_API_TOKEN'),
        'webhook_secret' => env('MEDIA_COMPRESSOR_WEBHOOK_SECRET'),
        'webhook_url' => env('MEDIA_COMPRESSOR_WEBHOOK_URL', env('APP_URL').'/webhooks/media'),
        'bucket' => env('MEDIA_COMPRESSOR_BUCKET', env('MEDIA_COMPRESSOR_DEFAULT_BUCKET')),
        'default_converter' => env('MEDIA_COMPRESSOR_DEFAULT_CONVERTER', 'webp'),
        'timeout' => env('MEDIA_COMPRESSOR_TIMEOUT', env('MEDIA_COMPRESSOR_API_TIMEOUT', 60)),
        'download_timeout' => env('MEDIA_COMPRESSOR_DOWNLOAD_TIMEOUT', 120),
        'image_wait_seconds' => env('MEDIA_COMPRESSOR_IMAGE_WAIT_SECONDS', 8),
        'fallback_on_failure' => env('MEDIA_COMPRESSOR_FALLBACK_ON_FAILURE', false),
        'video_direct_upload_threshold' => env('MEDIA_COMPRESSOR_VIDEO_DIRECT_UPLOAD_THRESHOLD', 8388608),
        'video_remote_chunk_upload_enabled' => env('MEDIA_COMPRESSOR_VIDEO_REMOTE_CHUNK_UPLOAD_ENABLED', false),
        'video_chunk_size' => env('MEDIA_COMPRESSOR_VIDEO_CHUNK_SIZE', 16777216),
        'video_chunk_concurrency' => env('MEDIA_COMPRESSOR_VIDEO_CHUNK_CONCURRENCY', 4),
        'paths' => [
            'images' => env('MEDIA_COMPRESSOR_IMAGES_PATH', '/api/v1/images'),
            'videos' => env('MEDIA_COMPRESSOR_VIDEOS_PATH', '/api/v1/videos'),
            'job' => env('MEDIA_COMPRESSOR_JOB_PATH', '/api/v1/jobs/{job}'),
            'video_upload_init' => env('MEDIA_COMPRESSOR_VIDEO_UPLOAD_INIT_PATH', '/api/v1/videos/uploads/init'),
            'video_upload_chunk' => env('MEDIA_COMPRESSOR_VIDEO_UPLOAD_CHUNK_PATH', '/api/v1/videos/uploads/{upload}/chunks'),
            'video_upload_complete' => env('MEDIA_COMPRESSOR_VIDEO_UPLOAD_COMPLETE_PATH', '/api/v1/videos/uploads/{upload}/complete'),
        ],
    ],

];
