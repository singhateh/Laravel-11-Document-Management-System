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

    'max_carrier_size_mb' => (int) env('STEGOLOCK_MAX_CARRIER_MB', 20),

];
