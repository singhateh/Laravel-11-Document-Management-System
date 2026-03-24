<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Steganography Driver
    |--------------------------------------------------------------------------
    |
    | Controls which steganography engine is used for LSB operations.
    |
    |   'python' — Uses the Python stegano library via subprocess (recommended).
    |              Requires Python + stegano + Pillow to be installed:
    |                  pip install stegano Pillow
    |
    |   'php'    — Uses the built-in PHP GD-based LSB implementation.
    |              No external dependencies required.
    |
    */

    'driver' => env('STEGO_DRIVER', 'python'),

    /*
    |--------------------------------------------------------------------------
    | Python Executable
    |--------------------------------------------------------------------------
    |
    | Path to the Python interpreter. Use 'python' or 'python3' if it is on
    | your system PATH, or provide the full absolute path for Windows:
    |
    |   Example (Windows):
    |       C:\Users\USER\AppData\Local\Programs\Python\Python312\python.exe
    |
    */

    'python_path' => env('PYTHON_PATH', 'python'),

    /*
    |--------------------------------------------------------------------------
    | Python Script Path
    |--------------------------------------------------------------------------
    |
    | Absolute path to the stego_lsb.py script. Defaults to the python/
    | directory at the project root.
    |
    */

    'python_script' => env(
        'PYTHON_SCRIPT_PATH',
        base_path('python' . DIRECTORY_SEPARATOR . 'stego_lsb.py')
    ),

    /*
    |--------------------------------------------------------------------------
    | Python Process Timeout
    |--------------------------------------------------------------------------
    |
    | Maximum number of seconds to wait for the Python subprocess to complete.
    | Increase this for very large carrier images.
    |
    */

    'python_timeout' => (int) env('PYTHON_TIMEOUT', 60),

    /*
    |--------------------------------------------------------------------------
    | Master Key Derivation (MKD) Iterations
    |--------------------------------------------------------------------------
    |
    | PBKDF2-SHA256 iteration count for deriving the master key from the
    | user's password. Higher = more secure but slower.
    |
    */

    'mkd_iterations' => (int) env('STEGOLOCK_MKD_ITERATIONS', 100_000),

    /*
    |--------------------------------------------------------------------------
    | Document Encryption Key (DEK) Iterations
    |--------------------------------------------------------------------------
    |
    | PBKDF2-SHA256 iteration count for per-document key derivation.
    |
    */

    'dek_iterations' => (int) env('STEGOLOCK_DEK_ITERATIONS', 10_000),

    /*
    |--------------------------------------------------------------------------
    | Maximum Carrier File Size (MB)
    |--------------------------------------------------------------------------
    |
    | Mirrors the controller validation rule 'max:20480' (20 MB).
    | Used for informational capacity checks.
    |
    */

    'max_carrier_size_mb' => (int) env('STEGOLOCK_MAX_CARRIER_MB', 100),

    /*
    |--------------------------------------------------------------------------
    | Carrier Pool Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for the carrier pool feature that allows users to upload and
    | validate carrier images once, then reuse them across multiple encode
    | operations without re-uploading.
    |
    */

    'carrier_pool' => [
        // Maximum number of carriers a user can have in their pool
        'max_carriers_per_user' => (int) env('STEGOLOCK_MAX_CARRIERS_PER_USER', 50),

        // Maximum total size of all carriers in a user's pool (in bytes)
        // Default: 500 MB
        'max_total_size_bytes' => (int) env('STEGOLOCK_MAX_POOL_SIZE_BYTES', 500 * 1024 * 1024),

        // PSNR threshold for carrier validation (in dB)
        // Carriers below this threshold are marked as invalid
        'psnr_threshold' => (float) env('STEGOLOCK_PSNR_THRESHOLD', 40.0),

        // Additional encode-time quality guard for bin-packing concentration.
        // If average PSNR across used image carriers drops below this value, encode fails.
        'encode_average_psnr_threshold' => (float) env('STEGOLOCK_ENCODE_AVG_PSNR_THRESHOLD', 41.0),
    ],

];
