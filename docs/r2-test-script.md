# R2 Connection Test Script

This test script verifies your Cloudflare R2 storage connection is working properly.

## Test Script

```php
<?php

/**
 * Test script to verify Cloudflare R2 storage connection
 * This script is not integrated into the main StegoLock project
 * It serves as a standalone tool to test the R2 configuration
 */

require __DIR__.'/../vendor/autoload.php';

use Aws\S3\S3Client;
use Aws\Exception\AwsException;
use Illuminate\Support\Env;

// Load environment variables
if (file_exists(__DIR__.'/../.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__.'/../');
    $dotenv->load();
}

// Check required environment variables
$requiredEnvVars = [
    'R2_ACCESS_KEY_ID',
    'R2_SECRET_ACCESS_KEY',
    'R2_BUCKET',
    'R2_ENDPOINT',
    'R2_URL',
];

foreach ($requiredEnvVars as $var) {
    if (!Env::has($var)) {
        die("Error: Environment variable {$var} is not set. Please check your .env file.\n");
    }
}

echo "✅ All required environment variables are set.\n";
echo "Testing Cloudflare R2 connection...\n";

// Create S3 client for R2
try {
    $client = new S3Client([
        'version' => 'latest',
        'region' => 'auto',
        'endpoint' => Env::get('R2_ENDPOINT'),
        'use_path_style_endpoint' => true,
        'credentials' => [
            'key' => Env::get('R2_ACCESS_KEY_ID'),
            'secret' => Env::get('R2_SECRET_ACCESS_KEY'),
        ],
    ]);

    echo "✅ R2 client initialized successfully.\n";
} catch (\Exception $e) {
    die("❌ Error initializing R2 client: " . $e->getMessage() . "\n");
}

// Test bucket existence
try {
    $client->headBucket([
        'Bucket' => Env::get('R2_BUCKET'),
    ]);

    echo "✅ R2 bucket '". Env::get('R2_BUCKET') ."' exists and is accessible.\n";
} catch (AwsException $e) {
    if ($e->getStatusCode() === 404) {
        die("❌ Error: R2 bucket '". Env::get('R2_BUCKET') ."' not found. Check your bucket name and permissions.\n");
    } elseif ($e->getStatusCode() === 403) {
        die("❌ Error: Access denied to R2 bucket '". Env::get('R2_BUCKET') ."'. Check your credentials and permissions.\n");
    } else {
        die("❌ Error accessing R2 bucket: " . $e->getMessage() . "\n");
    }
}

// List objects in bucket
try {
    $result = $client->listObjectsV2([
        'Bucket' => Env::get('R2_BUCKET'),
    ]);

    echo "✅ Found " . $result['KeyCount'] . " objects in bucket '". Env::get('R2_BUCKET') ."'.\n";
} catch (AwsException $e) {
    die("❌ Error listing objects: " . $e->getMessage() . "\n");
}

// Test uploading and downloading a small file
$testFilename = 'test-r2-connection.txt';
$testContent = "This is a test file to verify R2 connection. Generated on " . date('c');

try {
    $client->putObject([
        'Bucket' => Env::get('R2_BUCKET'),
        'Key' => $testFilename,
        'Body' => $testContent,
        'ContentType' => 'text/plain',
    ]);

    echo "✅ Test file uploaded successfully.\n";
} catch (AwsException $e) {
    die("❌ Error uploading test file: " . $e->getMessage() . "\n");
}

try {
    $result = $client->getObject([
        'Bucket' => Env::get('R2_BUCKET'),
        'Key' => $testFilename,
    ]);

    if ($result['Body']->getContents() === $testContent) {
        echo "✅ Test file downloaded and verified successfully.\n";
    } else {
        die("❌ Error: Downloaded test file content does not match uploaded content.\n");
    }
} catch (AwsException $e) {
    die("❌ Error downloading test file: " . $e->getMessage() . "\n");
}

// Clean up test file
try {
    $client->deleteObject([
        'Bucket' => Env::get('R2_BUCKET'),
        'Key' => $testFilename,
    ]);

    echo "✅ Test file deleted successfully.\n";
} catch (AwsException $e) {
    die("❌ Error deleting test file: " . $e->getMessage() . "\n");
}

echo "\n🎉 R2 connection test completed successfully!\n";
echo "Your Cloudflare R2 storage is ready to use with StegoLock.\n";

// Display configuration information
echo "\nConfiguration Summary:\n";
echo "----------------------\n";
echo "Endpoint: " . Env::get('R2_ENDPOINT') . "\n";
echo "Bucket: " . Env::get('R2_BUCKET') . "\n";
echo "URL: " . Env::get('R2_URL') . "\n";

// Check if AWS SDK is installed
if (class_exists('Aws\S3\S3Client')) {
    echo "AWS SDK: ✅ Installed\n";
} else {
    echo "AWS SDK: ❌ Not installed. Run 'composer require aws/aws-sdk-php' to install.\n";
}

// Check if PHP version is sufficient
if (version_compare(PHP_VERSION, '7.4.0', '>=')) {
    echo "PHP Version: ✅ " . PHP_VERSION . " (meets requirements)\n";
} else {
    echo "PHP Version: ❌ " . PHP_VERSION . " (requires PHP >= 7.4.0)\n";
}
```

## How to Use

1. Save this script as `test-r2-connection.php` in the `scripts` directory
2. Ensure all required environment variables are set in your `.env` file
3. Run the script from the command line:
   ```bash
   cd /path/to/stegolock
   php scripts/test-r2-connection.php
   ```

## What the Script Does

1. **Verifies Environment Variables**: Checks if all required R2 configuration variables are set
2. **Initializes R2 Client**: Creates an S3 client instance configured for R2
3. **Tests Bucket Access**: Verifies that the configured bucket exists and is accessible
4. **Lists Objects**: Retrieves and displays the number of objects in the bucket
5. **Tests Upload/Download**: Uploads a test file, downloads it, and verifies the content matches
6. **Cleans Up**: Deletes the test file from your bucket
7. **Provides Feedback**: Displays a summary of the test results

## Troubleshooting

If you encounter any issues:

1. **Environment Variable Errors**: Check that all required variables are set in your `.env` file
2. **Connection Errors**: Verify that your R2 endpoint and credentials are correct
3. **Access Denied**: Ensure your API token has the necessary permissions to access the bucket
4. **Bucket Not Found**: Double-check the bucket name in your configuration

This script serves as a standalone test tool and does not integrate with the main StegoLock application. It's recommended to run this test before making any changes to your application code.
