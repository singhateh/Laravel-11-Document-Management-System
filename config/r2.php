<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cloudflare R2 Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains configuration options for Cloudflare R2 storage.
    | R2 is an S3-compatible storage service with no egress fees.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Default Disk
    |--------------------------------------------------------------------------
    |
    | Specifies the default disk to use for R2 storage operations.
    |
    */

    'default' => env('R2_DISK', 'r2'),

    /*
    |--------------------------------------------------------------------------
    | Disk Configurations
    |--------------------------------------------------------------------------
    |
    | Here you may configure the disks for your R2 storage.
    | Each disk has its own configuration options.
    |
    */

    'disks' => [

        'r2' => [
            'driver' => 's3',
            'key' => env('R2_ACCESS_KEY_ID'),
            'secret' => env('R2_SECRET_ACCESS_KEY'),
            'region' => 'auto', // R2 uses 'auto' region
            'bucket' => env('R2_BUCKET'),
            'url' => env('R2_URL'),
            'endpoint' => env('R2_ENDPOINT'),
            'use_path_style_endpoint' => env('R2_USE_PATH_STYLE_ENDPOINT', true),
            'throw' => false,
        ],

        // You can add additional R2 disks here if needed
        // 'r2-public' => [
        //     'driver' => 's3',
        //     'key' => env('R2_PUBLIC_ACCESS_KEY_ID'),
        //     'secret' => env('R2_PUBLIC_SECRET_ACCESS_KEY'),
        //     'region' => 'auto',
        //     'bucket' => env('R2_PUBLIC_BUCKET'),
        //     'url' => env('R2_PUBLIC_URL'),
        //     'endpoint' => env('R2_PUBLIC_ENDPOINT'),
        //     'use_path_style_endpoint' => true,
        //     'throw' => false,
        // ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Path Prefix
    |--------------------------------------------------------------------------
    |
    | This prefix is added to all R2 object keys to organize your storage.
    | For example, 'stego/' would prefix all keys with stego/.
    |
    */

    'prefix' => env('R2_PREFIX', 'stego'),

    /*
    |--------------------------------------------------------------------------
    | Visibility
    |--------------------------------------------------------------------------
    |
    | Specifies the default visibility for uploaded files.
    | Can be 'private' or 'public'.
    |
    */

    'visibility' => env('R2_VISIBILITY', 'private'),

    /*
    |--------------------------------------------------------------------------
    | Temporary URL Expiry
    |--------------------------------------------------------------------------
    |
    | Default expiry time (in minutes) for temporary URLs.
    |
    */

    'temp_url_expiry' => env('R2_TEMP_URL_EXPIRY', 60),

    /*
    |--------------------------------------------------------------------------
    | Cache Control
    |--------------------------------------------------------------------------
    |
    | Default Cache-Control header for uploaded files.
    |
    */

    'cache_control' => env('R2_CACHE_CONTROL', 'max-age=3600'),

    /*
    |--------------------------------------------------------------------------
    | Presigned URL Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration options for generating presigned URLs.
    |
    */

    'presigned' => [
        'expiry' => env('R2_PRESIGNED_EXPIRY', 3600),
        'options' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Batch Operations
    |--------------------------------------------------------------------------
    |
    | Configuration options for batch operations.
    |
    */

    'batch' => [
        'chunk_size' => env('R2_BATCH_CHUNK_SIZE', 1000),
        'concurrency' => env('R2_BATCH_CONCURRENCY', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | Retry Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration options for retry logic.
    |
    */

    'retry' => [
        'max_attempts' => env('R2_RETRY_MAX_ATTEMPTS', 3),
        'delay' => env('R2_RETRY_DELAY', 1000),
        'backoff' => env('R2_RETRY_BACKOFF', 2),
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration options for logging.
    |
    */

    'logging' => [
        'enabled' => env('R2_LOGGING_ENABLED', false),
        'channel' => env('R2_LOGGING_CHANNEL', 'stack'),
        'level' => env('R2_LOGGING_LEVEL', 'info'),
    ],
