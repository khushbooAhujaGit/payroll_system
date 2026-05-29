<?php

use Illuminate\Support\Str;

return [
    'default' => env('CACHE_STORE', 'file'),    // file works without Redis

    'stores' => [
        'array' => [
            'driver' => 'array',
            'serialize' => false,
        ],
        'file' => [
            'driver' => 'file',
            'path'   => storage_path('framework/cache/data'),
            'lock_path' => storage_path('framework/cache/data'),
        ],
        'redis' => [
            'driver'     => 'redis',
            'connection' => env('REDIS_CACHE_CONNECTION', 'cache'),
            'lock_connection' => 'default',
        ],
        'database' => [
            'driver' => 'database',
            'table'  => 'cache',
            'connection' => null,
            'lock_connection' => null,
        ],
    ],

    'prefix' => env('CACHE_PREFIX', Str::slug(env('APP_NAME', 'hrms'), '_').'_cache_'),
];
