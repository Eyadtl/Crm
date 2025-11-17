<?php

return [
    'stateful' => explode(',', env('SANCTUM_STATEFUL_DOMAINS', 'localhost,localhost:5173')),

    'guard' => ['web'],

    'expiration' => env('SANCTUM_EXPIRATION', 120),

    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', ''),

    'middleware' => [
        'verify_csrf_token' => \App\Http\Middleware\VerifyCsrfToken::class,
        'ensure_front_cookie' => \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
    ],

    'personal_access_token_model' => \App\Models\PersonalAccessToken::class,
];
