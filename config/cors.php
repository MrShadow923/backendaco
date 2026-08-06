<?php
return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['*'],
    'allowed_origins' => [
        'https://frontendaco-otug.vercel.app',
        'https://frontendaco-otug-luuzxtsfg-donny7.vercel.app',
    ],
    // This regex allows ANY Vercel preview URL automatically
    'allowed_origins_patterns' => [
        '/^https:\/\/.*\.vercel\.app$/'
    ],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => false,
];