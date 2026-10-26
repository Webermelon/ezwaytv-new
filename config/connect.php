<?php

return [
    'url' => rtrim((string) env('CONNECT_URL', 'https://ezwayconnect.com'), '/'),
    'sso_client_id' => env('CONNECT_SSO_CLIENT_ID', 'ezway-tv'),
    'sso_secret' => env('CONNECT_SSO_SECRET'),
    'sso_timeout' => (int) env('CONNECT_SSO_TIMEOUT', 10),
];
