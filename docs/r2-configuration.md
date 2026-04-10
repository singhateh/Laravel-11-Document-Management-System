# Cloudflare R2 Configuration for StegoLock

This document provides the configuration files and settings needed to use Cloudflare R2 storage with StegoLock.

## Configuration Files

### 1. Laravel Filesystem Configuration

Add the following disk configuration to `config/filesystems.php`:

```php
// config/filesystems.php
'r2' => [
    'driver' => 's3',
    'key' => env('R2_ACCESS_KEY_ID'),
    'secret' => env('R2_SECRET_ACCESS_KEY'),
    'region' => 'auto', // R2 uses 'auto' region
    'bucket' => env('R2_BUCKET'),
    'url' => env('R2_URL'),
    'endpoint' => env('R2_ENDPOINT'),
    'use_path_style_endpoint' => true,
    'throw' => false,
],
```

### 2. Environment Variables

Add these variables to your `.env` file:

```env
# Cloudflare R2 Storage Configuration
R2_ACCESS_KEY_ID=your-access-key-id
R2_SECRET_ACCESS_KEY=your-secret-access-key
R2_BUCKET=stegolock-storage
R2_URL=https://<account-id>.r2.cloudflarestorage.com/stegolock-storage
R2_ENDPOINT=https://<account-id>.r2.cloudflarestorage.com
R2_USE_PATH_STYLE_ENDPOINT=true
```

### 3. Custom Configuration File (Optional)

If you want a separate configuration file for R2, create `config/r2.php`:

```php
<?php

return [
    'default' => env('R2_DISK', 'r2'),
    
    'disks' => [
        'r2' => [
            'driver' => 's3',
            'key' => env('R2_ACCESS_KEY_ID'),
            'secret' => env('R2_SECRET_ACCESS_KEY'),
            'region' => 'auto',
            'bucket' => env('R2_BUCKET'),
            'url' => env('R2_URL'),
            'endpoint' => env('R2_ENDPOINT'),
            'use_path_style_endpoint' => env('R2_USE_PATH_STYLE_ENDPOINT', true),
            'throw' => false,
        ],
    ],
    
    'prefix' => env('R2_PREFIX', 'stego'),
    'visibility' => env('R2_VISIBILITY', 'private'),
    'temp_url_expiry' => env('R2_TEMP_URL_EXPIRY', 60),
    'cache_control' => env('R2_CACHE_CONTROL', 'max-age=3600'),
];
```

## Environment Variable Reference

| Variable | Description | Example |
|----------|-------------|---------|
| R2_ACCESS_KEY_ID | Your Cloudflare R2 access key ID | `AKIAEXAMPLE123456789` |
| R2_SECRET_ACCESS_KEY | Your Cloudflare R2 secret access key | `wJalrXUtnFEMI/K7MDENG+bPxRfiCYEXAMPLEKEY` |
| R2_BUCKET | The name of your R2 bucket | `stegolock-storage` |
| R2_URL | The full URL to your R2 bucket | `https://1234567890.r2.cloudflarestorage.com/stegolock-storage` |
| R2_ENDPOINT | The R2 endpoint URL | `https://1234567890.r2.cloudflarestorage.com` |
| R2_USE_PATH_STYLE_ENDPOINT | Whether to use path-style endpoints | `true` |
| R2_PREFIX | Optional prefix for all object keys | `stego` |
| R2_VISIBILITY | Default visibility for uploaded files | `private` |
| R2_TEMP_URL_EXPIRY | Default temporary URL expiry in minutes | `60` |
| R2_CACHE_CONTROL | Default Cache-Control header | `max-age=3600` |

## Usage Examples

### Using the Storage Facade

```php
use Illuminate\Support\Facades\Storage;

// Upload a file
Storage::disk('r2')->put('carriers/1/image.png', $fileContents);

// Download a file
$contents = Storage::disk('r2')->get('carriers/1/image.png');

// Check if file exists
$exists = Storage::disk('r2')->exists('carriers/1/image.png');

// Get temporary URL
$url = Storage::disk('r2')->temporaryUrl(
    'carriers/1/image.png',
    now()->addMinutes(60)
);
```

### Using the CloudStorageService

To update the `CloudStorageService` to use R2, change the disk constant:

```php
// app/Services/Stego/CloudStorageService.php
private const DISK = 'r2';
```

## Troubleshooting

### Common Issues

1. **Connection Failures**:
   - Verify your R2 credentials are correct
   - Check that your Cloudflare account is active
   - Ensure your endpoint URL is properly formatted

2. **Permission Errors**:
   - Check that your API token has the necessary permissions
   - Verify the bucket name in your configuration

3. **CORS Issues**:
   - Configure CORS in your R2 bucket settings
   - Allow your domain to access the bucket

### Testing the Configuration

Create a simple test script to verify your R2 setup:

```php
<?php

require __DIR__.'/vendor/autoload.php';

use Illuminate\Support\Facades\Storage;

// Check if R2 disk is configured
if (!config('filesystems.disks.r2')) {
    die("R2 disk not configured in config/filesystems.php\n");
}

// Test disk connection
try {
    $disk = Storage::disk('r2');
    
    // Check if bucket is accessible
    $disk->put('test.txt', 'Hello R2!');
    
    if ($disk->exists('test.txt')) {
        $content = $disk->get('test.txt');
        echo "Successfully connected to R2!\n";
        echo "Test file contents: {$content}\n";
        
        // Clean up test file
        $disk->delete('test.txt');
    } else {
        echo "Failed to verify R2 connection\n";
    }
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
}
```
