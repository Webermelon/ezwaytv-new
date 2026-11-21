<?php

return [
    'loopback' => (bool) env('VOD_LOOPBACK', false),
    'enabled' => (bool) env('VOD_CHANNELS_ENABLED', false),
    'sync_secret' => env('VOD_SYNC_SECRET', ''),
];
