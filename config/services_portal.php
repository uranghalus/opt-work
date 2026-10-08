<?php

return array_merge(
    require __DIR__.'/services.php', // If this existed
    [
        'optigate_portal' => [
            'url' => env('WEB_PORTAL_URL'),
            'token' => env('WEB_PORTAL_TOKEN'),
            'verify' => env('WEB_PORTAL_VERIFY_SSL', true),
        ],
    ]
);