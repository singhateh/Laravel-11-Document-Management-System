<?php

declare(strict_types=1);

use App\Services\Stego\CloudStorageService;
use Illuminate\Contracts\Console\Kernel;

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

/**
 * @param array<int, array<string, mixed>> $results
 */
function add_result(array &$results, string $name, bool $passed, string $details, ?string $error = null): void
{
    $results[] = [
        'step' => $name,
        'passed' => $passed,
        'details' => $details,
        'error' => $error,
        'timestamp' => date(DATE_ATOM),
    ];
}

$results = [];
$startedAt = microtime(true);
$runId = date('Ymd_His') . '_' . bin2hex(random_bytes(3));
$testPrefix = 'stego/integration-tests/' . $runId;
$summary = [
    'run_id' => $runId,
    'date' => date(DATE_ATOM),
    'disk' => (string) config('stegolock.storage.disk', 'local'),
    'app_env' => (string) config('app.env'),
    'app_url' => (string) config('app.url'),
];

$localUploadPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'stegolock_cloud_upload_' . $runId . '.txt';
$localDownloadPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'stegolock_cloud_download_' . $runId . '.txt';

$uploadContent = "StegoLock cloud roundtrip test\nrun_id={$runId}\ntime=" . date(DATE_ATOM) . "\n";
file_put_contents($localUploadPath, $uploadContent);

$service = app(CloudStorageService::class);

try {
    $diskName = (string) config('stegolock.storage.disk', 'local');
    $diskConfig = (array) config("filesystems.disks.{$diskName}", []);

    if ($diskConfig === []) {
        add_result(
            $results,
            'config.disk_exists',
            false,
            "Disk '{$diskName}' not found in filesystems.disks.",
            'Missing disk configuration'
        );
        throw new RuntimeException('Active StegoLock disk is not configured.');
    }

    add_result(
        $results,
        'config.disk_exists',
        true,
        "Disk '{$diskName}' is configured with driver '{$diskConfig['driver']}'."
    );

    if ($diskName === 'b2') {
        $required = ['key', 'secret', 'bucket', 'endpoint'];
        $missing = [];

        foreach ($required as $field) {
            if (empty($diskConfig[$field])) {
                $missing[] = $field;
            }
        }

        if ($missing !== []) {
            add_result(
                $results,
                'config.b2_required_fields',
                false,
                'Missing required B2 disk fields: ' . implode(', ', $missing),
                'B2 configuration incomplete'
            );
            throw new RuntimeException('B2 configuration missing required fields.');
        }

        add_result(
            $results,
            'config.b2_required_fields',
            true,
            'B2 disk has required fields: key, secret, bucket, endpoint.'
        );
    }

    $fileKey = $service->carrierKey(9999, 'roundtrip_file_' . $runId . '.txt');
    $contentKey = $service->segmentKey(9999, (int) date('His')) . '_content_' . $runId . '.txt';

    $uploadResult = $service->uploadFile($localUploadPath, $fileKey);
    add_result(
        $results,
        'upload.file',
        true,
        'uploadFile succeeded with key ' . $uploadResult['s3_key']
    );

    $existsAfterUpload = $service->exists($fileKey);
    add_result(
        $results,
        'exists.after_upload',
        $existsAfterUpload,
        $existsAfterUpload ? 'Object exists after upload.' : 'Object does not exist after upload.',
        $existsAfterUpload ? null : 'exists() returned false after upload'
    );

    $fetchedContent = $service->getContents($fileKey);
    $contentMatches = hash('sha256', $fetchedContent) === hash('sha256', $uploadContent);
    add_result(
        $results,
        'get_contents.hash_match',
        $contentMatches,
        $contentMatches ? 'Fetched content hash matches uploaded file hash.' : 'Fetched content hash mismatch.',
        $contentMatches ? null : 'getContents returned altered payload'
    );

    $service->download($fileKey, $localDownloadPath);
    $downloaded = is_file($localDownloadPath) ? (string) file_get_contents($localDownloadPath) : '';
    $downloadMatches = hash('sha256', $downloaded) === hash('sha256', $uploadContent);
    add_result(
        $results,
        'download.hash_match',
        $downloadMatches,
        $downloadMatches ? 'download() file hash matches uploaded file hash.' : 'download() file hash mismatch.',
        $downloadMatches ? null : 'Downloaded payload mismatch'
    );

    $publicUrl = $service->url($fileKey);
    add_result(
        $results,
        'url.generation',
        $publicUrl !== '',
        'Generated URL: ' . $publicUrl,
        $publicUrl !== '' ? null : 'url() returned empty string'
    );

    $tempUrl = $service->temporaryUrl($fileKey, now()->addMinutes(5));
    add_result(
        $results,
        'temporary_url.generation',
        $tempUrl !== '',
        'Generated temporary URL/path: ' . $tempUrl,
        $tempUrl !== '' ? null : 'temporaryUrl() returned empty string'
    );

    $uploadContentResult = $service->uploadContent('payload_' . $runId, $contentKey);
    $uploadedContentMatch = $service->getContents($contentKey) === 'payload_' . $runId;
    add_result(
        $results,
        'upload_content.roundtrip',
        $uploadedContentMatch,
        'uploadContent succeeded with key ' . $uploadContentResult['s3_key'],
        $uploadedContentMatch ? null : 'uploadContent/getContents mismatch'
    );

    $service->deleteMany([$fileKey, $contentKey]);
    $fileDeleted = !$service->exists($fileKey);
    $contentDeleted = !$service->exists($contentKey);
    $bothDeleted = $fileDeleted && $contentDeleted;
    add_result(
        $results,
        'delete_many.cleanup',
        $bothDeleted,
        $bothDeleted ? 'Both test objects were deleted.' : 'One or more test objects still exist.',
        $bothDeleted ? null : 'deleteMany cleanup incomplete'
    );

    // Idempotency check: deleting a missing key should not throw.
    $service->delete($fileKey);
    add_result(
        $results,
        'delete.idempotent',
        true,
        'delete() on missing key completed without exception.'
    );
} catch (Throwable $e) {
    add_result(
        $results,
        'test.unhandled_exception',
        false,
        'Test run aborted due to exception.',
        $e->getMessage()
    );
}

$durationMs = (int) round((microtime(true) - $startedAt) * 1000);
$passCount = count(array_filter($results, static fn (array $r): bool => (bool) $r['passed']));
$failCount = count($results) - $passCount;
$summary['duration_ms'] = $durationMs;
$summary['pass_count'] = $passCount;
$summary['fail_count'] = $failCount;
$summary['status'] = $failCount === 0 ? 'PASS' : 'FAIL';
$summary['test_prefix'] = $testPrefix;

$report = [
    'summary' => $summary,
    'results' => $results,
];

$reportsDir = __DIR__ . '/../storage/logs/cloud-tests';
if (!is_dir($reportsDir)) {
    mkdir($reportsDir, 0775, true);
}

$reportPath = $reportsDir . '/cloud-storage-roundtrip-' . $runId . '.json';
file_put_contents($reportPath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

if (is_file($localUploadPath)) {
    @unlink($localUploadPath);
}
if (is_file($localDownloadPath)) {
    @unlink($localDownloadPath);
}

echo "CloudStorageService Roundtrip Test\n";
echo "Run ID: {$runId}\n";
echo "Disk: {$summary['disk']}\n";
echo "Status: {$summary['status']}\n";
echo "Pass: {$summary['pass_count']} | Fail: {$summary['fail_count']}\n";
echo "Duration: {$summary['duration_ms']} ms\n";
echo "Report: {$reportPath}\n\n";

foreach ($results as $result) {
    $mark = $result['passed'] ? '[PASS]' : '[FAIL]';
    echo $mark . ' ' . $result['step'] . ' - ' . $result['details'] . "\n";
    if (!empty($result['error'])) {
        echo '       Error: ' . $result['error'] . "\n";
    }
}

exit($failCount === 0 ? 0 : 1);
