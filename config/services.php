<?php

declare(strict_types=1);

return [
    'ai_worker' => [
        'url' => env('AI_WORKER_URL', 'http://ai-worker:8000'),
    ],
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect_uri' => env('GOOGLE_REDIRECT_URI', '/admin/google-photos/callback'),
    ],
];
