<?php

declare(strict_types=1);

return [
    'admin' => [
        'email' => env('ADMIN_EMAIL'),
        'password' => env('ADMIN_PASSWORD'),
        'name' => env('ADMIN_NAME', 'Admin'),
    ],
    'initial_media' => [
        'path' => env('INITIAL_MEDIA_PATH'),
        'name' => env('INITIAL_MEDIA_NAME', 'Photos'),
    ],
];
