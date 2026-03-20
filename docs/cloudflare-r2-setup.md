# Cloudflare R2 Setup Guide for StegoLock

This guide provides detailed instructions for setting up Cloudflare R2 storage for the StegoLock capstone project. R2 is a cost-effective S3-compatible storage solution with no egress fees, making it ideal for storing large steganography carrier images.

## Table of Contents

1. [Prerequisites](#prerequisites)
2. [Creating an R2 Bucket](#creating-an-r2-bucket)
3. [Generating API Credentials](#generating-api-credentials)
4. [Configuration Files](#configuration-files)
5. [Environment Variables](#environment-variables)
6. [Testing the Connection](#testing-the-connection)
7. [Troubleshooting](#troubleshooting)

## Prerequisites

- A Cloudflare account (free tier available)
- Basic understanding of Cloudflare dashboard
- Command line access (for testing)

## Creating an R2 Bucket

1. Log in to your Cloudflare dashboard
2. Select your account (or create one if you don't have it)
3. Click on "R2" in the left sidebar
4. Click "Create bucket"
5. Enter a unique bucket name (e.g., `stegolock-storage`)
6. Choose a storage class (Standard is recommended for most cases)
7. Click "Create bucket"

## Generating API Credentials

1. In the R2 dashboard, click on "Manage R2 API Tokens"
2. Click "Create API Token"
3. Enter a token name (e.g., `stegolock-r2-token`)
4. Under "Permissions", select the following:
   - Object Read & Write
   - Bucket List
   - Bucket Read
5. Under "Bucket Access", select "All buckets" or specify your stegolock bucket
6. Under "TTL", set the token expiration (optional, but recommended for security)
7. Click "Create API Token"
8. Save the `Access Key ID` and `Secret Access Key` in a secure location

## Configuration Files

### filesystems.php Configuration

Create a new disk configuration in `config/filesystems.php`:

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

### Environment Variable Template

Add the following variables to your `.env` file:

```env
# Cloudflare R2 Storage Configuration
R2_ACCESS_KEY_ID=your-access-key-id
R2_SECRET_ACCESS_KEY=your-secret-access-key
R2_BUCKET=stegolock-storage
R2_URL=https://<account-id>.r2.cloudflarestorage.com/stegolock-storage
R2_ENDPOINT=https://<account-id>.r2.cloudflarestorage.com
R2_USE_PATH_STYLE_ENDPOINT=true
```

## Testing the Connection

Create a simple test script to verify the R2 connection:

```php
<?php

require __DIR__.'/vendor/autoload.php';

use Aws\S3\S3Client;
use Aws\Exception\AwsException;

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Create S3 client (R2)
$client = new S3Client([
    'version' => 'latest',
    'region' => 'auto',
    'endpoint' => $_ENV['R2_ENDPOINT'],
    'use_path_style_endpoint' => true,
    'credentials' => [
        'key' => $_ENV['R2_ACCESS_KEY_ID'],
        'secret' => $_ENV['R2_SECRET_ACCESS_KEY'],
    ],
]);

// Test bucket existence
try {
    $result = $client->headBucket([
        'Bucket' => $_ENV['R2_BUCKET'],
    ]);
    
    echo "Successfully connected to R2 bucket: " . $_ENV['R2_BUCKET'] . "\n";
    
    // List objects in bucket
    $objects = $client->listObjectsV2([
        'Bucket' => $_ENV['R2_BUCKET'],
    ]);
    
    echo "Number of objects in bucket: " . $objects['KeyCount'] . "\n";
} catch (AwsException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Status code: " . $e->getStatusCode() . "\n";
}
```

## Troubleshooting

### Common Issues

1. **Connection Errors**:
   - Verify that your Cloudflare account is active
   - Check that the endpoint URL is correct (format: `https://<account-id>.r2.cloudflarestorage.com`)
   - Ensure your API tokens are valid and not expired

2. **Permission Errors**:
   - Verify that the API token has the correct permissions (Object Read & Write, Bucket List, Bucket Read)
   - Check that the bucket name in your configuration matches the actual bucket name

3. **CORS Issues (for frontend)**:
   - In the R2 bucket settings, configure CORS to allow your domain
   - Example CORS policy:
     ```json
     [
         {
             "AllowedHeaders": ["*"],
             "AllowedMethods": ["GET", "PUT", "POST", "DELETE"],
             "AllowedOrigins": ["https://your-domain.com"],
             "ExposeHeaders": ["ETag"]
         }
     ]
     ```

## Cost Considerations

R2 offers a generous free tier:
- 10 GB of storage
- 10 million read operations
- 1 million write operations
- 100 GB of data transfer in

Additional usage is billed at:
- $0.015 per GB of storage per month
- $0.004 per 10,000 read operations
- $0.04 per 10,000 write operations
- **No egress fees**

## Security Best Practices

1. **Use short-lived API tokens**: Set expiration dates for your API tokens
2. **Restrict bucket access**: Limit API token permissions to specific buckets
3. **Enable versioning**: Protect against accidental deletions
4. **Monitor usage**: Set up alerts for unexpected spikes in usage
5. **Encrypt data at rest**: R2 automatically encrypts data, but you can also add client-side encryption

## Resources

- [Cloudflare R2 Documentation](https://developers.cloudflare.com/r2/)
- [R2 API Reference](https://developers.cloudflare.com/r2/api/s3-compatibility/)
- [Laravel Filesystem Documentation](https://laravel.com/docs/filesystem)
