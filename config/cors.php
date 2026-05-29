<?php

return [
    'paths'                    => ['api/*'],
    'allowed_methods'          => ['*'],
    // Keep explicit origins rather than wildcard so Authorization headers
    // are never echoed to unknown third-party sites.
    'allowed_origins'          => [
        'http://127.0.0.1:8000',
        'http://localhost:8000',
        'http://localhost:3000',
    ],
    'allowed_origins_patterns' => [],
    'allowed_headers'          => ['*'],
    'exposed_headers'          => [],
    'max_age'                  => 0,
    'supports_credentials'     => false,
];