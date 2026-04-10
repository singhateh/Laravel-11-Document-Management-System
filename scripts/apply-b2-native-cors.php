<?php

declare(strict_types=1);

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

function jsonRequest(string $method, string $url, array $headers = [], ?array $payload = null, bool $sslVerify = true): array
{
    $ch = curl_init($url);
    if ($ch === false) {
        fail('Unable to initialize cURL.');
    }

    $httpHeaders = $headers;

    if ($payload !== null) {
        $httpHeaders[] = 'Content-Type: application/json';
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_SLASHES));
    }

    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $httpHeaders);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $sslVerify);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $sslVerify ? 2 : 0);

    $raw = curl_exec($ch);

    if ($raw === false) {
        $err = curl_error($ch);
        curl_close($ch);
        fail('cURL request failed: ' . $err, 2);
    }

    $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);

    $body = substr($raw, $headerSize);
    $decoded = json_decode($body, true);

    return [
        'status' => $status,
        'bodyRaw' => $body,
        'json' => is_array($decoded) ? $decoded : null,
    ];
}

function methodToNativeOperation(string $method): string
{
    return match (strtoupper(trim($method))) {
        'PUT' => 's3_put',
        'GET' => 's3_get',
        'HEAD' => 's3_head',
        'DELETE' => 's3_delete',
        default => strtolower(trim($method)),
    };
}

function toNativeCorsRules(array $decoded): array
{
    if (isset($decoded['CORSRules']) && is_array($decoded['CORSRules'])) {
        $rules = $decoded['CORSRules'];

        return array_values(array_map(function (array $rule, int $index): array {
            $allowedOrigins = $rule['AllowedOrigins'] ?? [];
            $allowedMethods = $rule['AllowedMethods'] ?? [];
            $allowedHeaders = $rule['AllowedHeaders'] ?? [];
            $exposeHeaders = $rule['ExposeHeaders'] ?? [];
            $maxAge = $rule['MaxAgeSeconds'] ?? 3600;

            if (!is_array($allowedOrigins) || !is_array($allowedMethods)) {
                fail('Invalid S3 CORS rule shape at index ' . $index . '.');
            }

            $allowedOperations = array_values(array_map('methodToNativeOperation', $allowedMethods));

            return [
                'corsRuleName' => 'native-upload-rule-' . ($index + 1),
                'allowedOrigins' => array_values($allowedOrigins),
                'allowedHeaders' => is_array($allowedHeaders) ? array_values($allowedHeaders) : ['*'],
                'allowedOperations' => $allowedOperations,
                'exposeHeaders' => is_array($exposeHeaders) ? array_values($exposeHeaders) : ['ETag'],
                'maxAgeSeconds' => (int) $maxAge,
            ];
        }, $rules, array_keys($rules)));
    }

    if (array_is_list($decoded)) {
        // Assume already-native corsRules list.
        return $decoded;
    }

    fail('Unsupported CORS JSON shape. Expected {"CORSRules": [...]} or native rules array.');
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
    fail('CORS file not found: ' . $corsFilePath);
}

$decoded = json_decode((string) file_get_contents($corsFilePath), true);
if (!is_array($decoded)) {
    fail('Invalid JSON in CORS file: ' . $corsFilePath);
}

$nativeCorsRules = toNativeCorsRules($decoded);

$keyId = envValue('B2_KEY_ID');
$appKey = envValue('B2_APPLICATION_KEY');
$bucketName = envValue('B2_BUCKET');
$sslVerify = filter_var(envValue('B2_SSL_VERIFY', 'true'), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
if ($sslVerify === null) {
    $sslVerify = true;
}

if ($keyId === null) {
    fail('B2_KEY_ID is missing.');
}
if ($appKey === null) {
    fail('B2_APPLICATION_KEY is missing.');
}
if ($bucketName === null) {
    fail('B2_BUCKET is missing.');
}

fwrite(STDOUT, 'Bucket: ' . $bucketName . PHP_EOL);
fwrite(STDOUT, 'Mode: ' . ($apply ? 'APPLY' : 'DRY-RUN') . PHP_EOL);
fwrite(STDOUT, 'CORS Rules File: ' . $corsFilePath . PHP_EOL . PHP_EOL);

fwrite(STDOUT, 'Resolved native corsRules payload:' . PHP_EOL);
fwrite(STDOUT, json_encode($nativeCorsRules, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL . PHP_EOL);

if (!$apply) {
    fwrite(STDOUT, "No remote changes made. Re-run with --apply to update native bucket CORS." . PHP_EOL);
    exit(0);
}

$authHeader = 'Authorization: Basic ' . base64_encode($keyId . ':' . $appKey);
$auth = jsonRequest('GET', 'https://api.backblazeb2.com/b2api/v4/b2_authorize_account', [$authHeader], null, $sslVerify);

if ($auth['status'] !== 200 || !is_array($auth['json'])) {
    fail('b2_authorize_account failed (HTTP ' . $auth['status'] . '). Body: ' . $auth['bodyRaw'], 2);
}

$authJson = $auth['json'];
$accountId = $authJson['accountId'] ?? null;
$storageApi = $authJson['apiInfo']['storageApi'] ?? null;
$apiUrl = is_array($storageApi) ? ($storageApi['apiUrl'] ?? null) : ($authJson['apiUrl'] ?? null);
$authToken = is_array($storageApi)
    ? ($storageApi['authorizationToken'] ?? ($authJson['authorizationToken'] ?? null))
    : ($authJson['authorizationToken'] ?? null);

if (!is_string($accountId) || !is_string($apiUrl) || !is_string($authToken)) {
    fail('Unexpected authorize response: missing accountId/apiUrl/authorizationToken.', 2);
}

$list = jsonRequest(
    'POST',
    rtrim($apiUrl, '/') . '/b2api/v4/b2_list_buckets',
    ['Authorization: ' . $authToken],
    [
        'accountId' => $accountId,
        'bucketName' => $bucketName,
    ],
    $sslVerify
);

if ($list['status'] !== 200 || !is_array($list['json'])) {
    fail('b2_list_buckets failed (HTTP ' . $list['status'] . '). Body: ' . $list['bodyRaw'], 2);
}

$buckets = $list['json']['buckets'] ?? [];
if (!is_array($buckets) || count($buckets) === 0) {
    fail('Bucket not found in b2_list_buckets response: ' . $bucketName, 2);
}

$bucket = $buckets[0];
$bucketId = $bucket['bucketId'] ?? null;
$revision = $bucket['revision'] ?? null;

if (!is_string($bucketId) || !is_int($revision)) {
    fail('Unexpected bucket payload: missing bucketId or revision.', 2);
}

$update = jsonRequest(
    'POST',
    rtrim($apiUrl, '/') . '/b2api/v4/b2_update_bucket',
    ['Authorization: ' . $authToken],
    [
        'accountId' => $accountId,
        'bucketId' => $bucketId,
        'ifRevisionIs' => $revision,
        'corsRules' => $nativeCorsRules,
    ],
    $sslVerify
);

if ($update['status'] !== 200 || !is_array($update['json'])) {
    fail('b2_update_bucket failed (HTTP ' . $update['status'] . '). Body: ' . $update['bodyRaw'], 2);
}

fwrite(STDOUT, 'b2_update_bucket: OK' . PHP_EOL . PHP_EOL);

$updatedRules = $update['json']['corsRules'] ?? [];
fwrite(STDOUT, 'Confirmed native corsRules:' . PHP_EOL);
fwrite(STDOUT, json_encode($updatedRules, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
