<?php

return [
    'enabled' => env('TV_LOG_CAPTURE_ENABLED', false),
    'spool' => env('TV_LOG_SPOOL', storage_path('app/tv-logs')),
];
