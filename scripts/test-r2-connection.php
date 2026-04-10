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
