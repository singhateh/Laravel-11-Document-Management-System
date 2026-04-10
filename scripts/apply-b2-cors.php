<?php

declare(strict_types=1);

use Aws\Exception\AwsException;
use Aws\S3\S3Client;

require __DIR__ . '/../vendor/autoload.php';

$rootDir = dirname(__DIR__);

if (class_exists(\Dotenv\Dotenv::class)) {
    \Dotenv\Dotenv::createImmutable($rootDir)->safeLoad();
}

function envValue(string $key, ?string $default = null): ?string
{
    $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

    if ($value === false || $value === null || $value === '') {
        return $default;
    }

    return (string) $value;
}

function fail(string $message, int $code = 1): never
{
    fwrite(STDERR, "ERROR: {$message}" . PHP_EOL);
    exit($code);
}

$apply = in_array('--apply', $argv, true);
$corsFileArg = null;

foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--file=')) {
        $corsFileArg = substr($arg, 7);
    }
}

$corsFilePath = $corsFileArg ?: $rootDir . '/docs/b2-cors-policy.json';

if (!is_file($corsFilePath)) {
    fail("CORS file not found: {$corsFilePath}");
}

$rawJson = file_get_contents($corsFilePath);
if ($rawJson === false) {
    fail("Unable to read CORS file: {$corsFilePath}");
}

$decoded = json_decode($rawJson, true);
if (!is_array($decoded)) {
    fail("Invalid JSON in CORS file: {$corsFilePath}");
}

// Accept both S3 format ({"CORSRules": [...]}) and legacy array ([{...}]).
if (isset($decoded['CORSRules']) && is_array($decoded['CORSRules'])) {
    $corsRules = $decoded['CORSRules'];
} elseif (array_is_list($decoded)) {
    $corsRules = $decoded;
} else {
    fail('CORS JSON must be either {"CORSRules": [...]} or a top-level array of rules.');
}

$bucket = envValue('B2_BUCKET');
$keyId = envValue('B2_KEY_ID');
$appKey = envValue('B2_APPLICATION_KEY');
$region = envValue('B2_REGION', 'ca-east-006');
$endpoint = envValue('B2_ENDPOINT', 'https://s3.ca-east-006.backblazeb2.com');
$usePathStyle = filter_var(envValue('B2_USE_PATH_STYLE_ENDPOINT', 'true'), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
$sslVerify = filter_var(envValue('B2_SSL_VERIFY', 'true'), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

if ($bucket === null) {
    fail('B2_BUCKET is missing.');
}
if ($keyId === null) {
    fail('B2_KEY_ID is missing.');
}
if ($appKey === null) {
    fail('B2_APPLICATION_KEY is missing.');
}
if ($usePathStyle === null) {
    $usePathStyle = true;
}
if ($sslVerify === null) {
    $sslVerify = true;
}

$config = [
    'version' => 'latest',
    'region' => $region,
    'endpoint' => $endpoint,
    'use_path_style_endpoint' => $usePathStyle,
    'credentials' => [
        'key' => $keyId,
        'secret' => $appKey,
    ],
    'http' => [
        'verify' => $sslVerify,
    ],
];

$client = new S3Client($config);

fwrite(STDOUT, 'B2 Endpoint: ' . $endpoint . PHP_EOL);
fwrite(STDOUT, 'Bucket: ' . $bucket . PHP_EOL);
fwrite(STDOUT, 'Mode: ' . ($apply ? 'APPLY' : 'DRY-RUN') . PHP_EOL);
fwrite(STDOUT, 'CORS Rules File: ' . $corsFilePath . PHP_EOL . PHP_EOL);

$payload = [
    'CORSRules' => $corsRules,
];

fwrite(STDOUT, 'Resolved CORS payload:' . PHP_EOL);
fwrite(STDOUT, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL . PHP_EOL);

if (!$apply) {
    fwrite(STDOUT, "No remote changes made. Re-run with --apply to update bucket CORS." . PHP_EOL);
    exit(0);
}

try {
    $client->putBucketCors([
        'Bucket' => $bucket,
        'CORSConfiguration' => $payload,
    ]);

    fwrite(STDOUT, 'putBucketCors: OK' . PHP_EOL);

    $result = $client->getBucketCors([
        'Bucket' => $bucket,
    ]);

    $confirmed = $result->toArray();

    fwrite(STDOUT, PHP_EOL . 'Confirmed bucket CORS:' . PHP_EOL);
    fwrite(STDOUT, json_encode($confirmed, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
} catch (AwsException $e) {
    $awsMessage = $e->getAwsErrorMessage() ?: $e->getMessage();
    fail('AWS/B2 request failed: ' . $awsMessage, 2);
} catch (Throwable $e) {
    fail('Unexpected failure: ' . $e->getMessage(), 3);
}
