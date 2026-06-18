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

    'ezway_getpaid' => [
        'checkout_url' => env('EZWAY_NETWORK_GETPAID_CHECKOUT_URL'),
        'checkout_urls' => [
            '1.99' => env('EZWAY_NETWORK_GETPAID_CHECKOUT_URL_199', 'https://ezwaynetwork.com/ezway-tv-checkout/?item=38475'),
            '199.99' => env('EZWAY_NETWORK_GETPAID_CHECKOUT_URL_19999', 'https://ezwaynetwork.com/ezway-tv-checkout/?item=38376'),
        ],
        'webhook_secret' => env('EZWAY_NETWORK_GETPAID_WEBHOOK_SECRET'),
        'return_url' => env('EZWAY_NETWORK_GETPAID_RETURN_URL', env('APP_URL') . '/payment-history'),
        'cancel_url' => env('EZWAY_NETWORK_GETPAID_CANCEL_URL', env('APP_URL') . '/subscription-plan'),
    ],



];
