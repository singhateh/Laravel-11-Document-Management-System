<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$origin = $argv[1] ?? 'http://test-caps.test';
$sslVerifyRaw = $_ENV['B2_SSL_VERIFY'] ?? $_SERVER['B2_SSL_VERIFY'] ?? getenv('B2_SSL_VERIFY') ?: 'true';
$sslVerify = filter_var($sslVerifyRaw, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
if ($sslVerify === null) {
    $sslVerify = true;
}

$disk = Illuminate\Support\Facades\Storage::disk('b2');
$signed = call_user_func(
    [$disk, 'temporaryUploadUrl'],
    'uploads/debug/preflight-test.pdf',
    now()->addMinutes(10),
    ['ContentType' => 'application/pdf']
);

$url = '';
if (is_array($signed)) {
    $url = (string) ($signed['url'] ?? $signed[0] ?? '');
}

if ($url === '') {
    fwrite(STDERR, "ERROR: Unable to generate signed URL.\n");
    exit(1);
}

$ch = curl_init($url);
if ($ch === false) {
    fwrite(STDERR, "ERROR: Unable to initialize cURL.\n");
    exit(1);
}

$headers = [
    'Origin: ' . $origin,
    'Access-Control-Request-Method: PUT',
    'Access-Control-Request-Headers: content-type',
];

curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'OPTIONS');
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $sslVerify);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $sslVerify ? 2 : 0);

$raw = curl_exec($ch);
if ($raw === false) {
    $err = curl_error($ch);
    curl_close($ch);
    fwrite(STDERR, 'ERROR: ' . $err . "\n");
    exit(2);
}

$status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
curl_close($ch);

$rawHeaders = substr($raw, 0, $headerSize);

echo "Origin: {$origin}\n";
echo "Signed URL host: " . parse_url($url, PHP_URL_HOST) . "\n";
echo "Preflight status: {$status}\n\n";

echo "Response headers:\n";
echo trim($rawHeaders) . "\n";
