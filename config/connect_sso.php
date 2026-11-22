<?php

return [
    'show_login' => (bool) env('CONNECT_SSO_SHOW_LOGIN', false),
    // Public Connect destination is resolved from Core platform settings.
    'client_id' => env('CONNECT_SSO_CLIENT_ID', 'ezway-tv'),
    'secret' => env('CONNECT_SSO_SECRET'),
    'timeout' => (int) env('CONNECT_SSO_TIMEOUT', 10),
];
