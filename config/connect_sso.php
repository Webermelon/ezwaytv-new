<?php

return [
    'show_login' => (bool) env('CONNECT_SSO_SHOW_LOGIN', false),
    'url' => rtrim((string) env('CONNECT_SSO_URL', 'https://ezwayconnect.com'), '/'),
    'client_id' => env('CONNECT_SSO_CLIENT_ID', 'ezway-tv'),
    'secret' => env('CONNECT_SSO_SECRET'),
    'timeout' => (int) env('CONNECT_SSO_TIMEOUT', 10),
];
