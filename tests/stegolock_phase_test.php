<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== Phase 2: Model Relationships ===" . PHP_EOL;
try {
    $user = App\Models\User::factory()->create([
        'name'     => 'Test User',
        'username' => 'testuser_phase2_' . time(),
        'email'    => 'test_phase2_' . time() . '@test.com',
        'role'     => 'owner',
    ]);
    echo "✓ User created with username/role fields" . PHP_EOL;
    echo "  id={$user->id}, username={$user->username}, role={$user->role}" . PHP_EOL;

    $rg = $user->roleGrants;
    echo "✓ roleGrants() relationship works (count: " . $rg->count() . ")" . PHP_EOL;

    $al = $user->accessLogs;
    echo "✓ accessLogs() relationship works (count: " . $al->count() . ")" . PHP_EOL;

    $sd = $user->stegoDocuments;
    echo "✓ stegoDocuments() relationship works (count: " . $sd->count() . ")" . PHP_EOL;

    echo "✓ isAdmin()=" . var_export($user->isAdmin(), true) . ", isOwner()=" . var_export($user->isOwner(), true) . PHP_EOL;

    // Verify stego model queries don't throw
    App\Models\StegoDocument::query()->toSql();
    echo "✓ StegoDocument query OK" . PHP_EOL;
    App\Models\StegoCarrier::query()->toSql();
    echo "✓ StegoCarrier query OK" . PHP_EOL;
    App\Models\StegoSegment::query()->toSql();
    echo "✓ StegoSegment query OK" . PHP_EOL;

    // Clean up
    $user->delete();
} catch (\Throwable $e) {
    echo "✗ FAIL: " . $e->getMessage() . PHP_EOL;
    echo $e->getTraceAsString() . PHP_EOL;
}

echo PHP_EOL . "=== Phase 4: CryptoService ===" . PHP_EOL;
try {
    $crypto = new App\Services\Stego\CryptoService();

    $mkd = $crypto->deriveMasterKey('MyPassword123');
    echo "✓ deriveMasterKey() — key length: " . strlen($mkd['masterKey']) . " (expect 64 hex chars)" . PHP_EOL;

    $dek = $crypto->deriveDEK($mkd['masterKey'], 'doc-001');
    echo "✓ deriveDEK() — key length: " . strlen($dek['dek']) . " (expect 64 hex chars)" . PHP_EOL;

    // Ensure same inputs produce same DEK (deterministic)
    $dek2 = $crypto->deriveDEK($mkd['masterKey'], 'doc-001', $dek['salt']);
    echo "✓ DEK is deterministic: " . var_export($dek['dek'] === $dek2['dek'], true) . PHP_EOL;

    $enc = $crypto->encrypt('Hello StegoLock!', $dek['dek']);
    echo "✓ encrypt() — ciphertext: " . substr($enc['ciphertext'], 0, 24) . "..." . PHP_EOL;

    $plain = $crypto->decrypt($enc['ciphertext'], $dek['dek'], $enc['iv'], $enc['auth_tag']);
    echo "✓ decrypt() — recovered: '{$plain}'" . PHP_EOL;

    $hash = $crypto->hashDocument('Hello StegoLock!');
    echo "✓ hashDocument() — hash: " . substr($hash, 0, 16) . "..." . PHP_EOL;
    echo "✓ verifyHash (correct): " . var_export($crypto->verifyHash('Hello StegoLock!', $hash), true) . PHP_EOL;
    echo "✓ verifyHash (tampered): " . var_export($crypto->verifyHash('Tampered!', $hash), true) . " (expect false)" . PHP_EOL;

    // Tampered auth_tag should throw
    try {
        $crypto->decrypt($enc['ciphertext'], $dek['dek'], $enc['iv'], str_repeat('00', 16));
        echo "✗ Expected exception for bad auth_tag but none thrown" . PHP_EOL;
    } catch (\Exception $e) {
        echo "✓ Tampered auth_tag correctly throws: " . $e->getMessage() . PHP_EOL;
    }
} catch (\Throwable $e) {
    echo "✗ FAIL: " . $e->getMessage() . PHP_EOL;
}

echo PHP_EOL . "=== Phase 4: SegmentationService ===" . PHP_EOL;
try {
    $seg = new App\Services\Stego\SegmentationService();

    $chunks = $seg->segment('ABCDEFGHIJKLMNOPQRSTUVWXYZ', 3);
    echo "✓ segment() — produced " . count($chunks) . " chunks" . PHP_EOL;
    foreach ($chunks as $c) {
        echo "  index={$c['index']} chunk='{$c['chunk']}' hash=" . substr($c['hash'], 0, 8) . "..." . PHP_EOL;
    }

    $reassembled = $seg->reassemble($chunks);
    echo "✓ reassemble() — result: '{$reassembled}'" . PHP_EOL;
    echo "✓ round-trip match: " . var_export($reassembled === 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', true) . PHP_EOL;

    // Tampered chunk hash should throw
    $tampered = $chunks;
    $tampered[0]['hash'] = str_repeat('00', 32);
    try {
        $seg->reassemble($tampered);
        echo "✗ Expected exception for tampered hash but none thrown" . PHP_EOL;
    } catch (\Exception $e) {
        echo "✓ Tampered chunk hash correctly throws: " . $e->getMessage() . PHP_EOL;
    }

    // Missing segment should throw
    try {
        $seg->reassemble([$chunks[0], $chunks[2]]); // skip index 1
        echo "✗ Expected exception for missing segment but none thrown" . PHP_EOL;
    } catch (\Exception $e) {
        echo "✓ Missing segment correctly throws: " . $e->getMessage() . PHP_EOL;
    }
} catch (\Throwable $e) {
    echo "✗ FAIL: " . $e->getMessage() . PHP_EOL;
}

echo PHP_EOL . "=== Phase 4: StegoService (PNG LSB) ===" . PHP_EOL;
try {
    $stego = new App\Services\Stego\StegoService();

    // Create a small test PNG using GD
    $img = imagecreatetruecolor(100, 100);
    imagefill($img, 0, 0, imagecolorallocate($img, 200, 150, 100));
    $carrierPath = sys_get_temp_dir() . '/stego_test_carrier.png';
    $outputPath  = sys_get_temp_dir() . '/stego_test_output.png';
    imagepng($img, $carrierPath);
    imagedestroy($img);

    $capacity = $stego->capacity($carrierPath);
    echo "✓ capacity() — {$capacity} bytes available in 100x100 PNG" . PHP_EOL;

    $secret = 'StegoLock secret payload!';
    $stego->embed($carrierPath, $secret, $outputPath);
    echo "✓ embed() — written to " . basename($outputPath) . PHP_EOL;

    $extracted = $stego->extract($outputPath);
    echo "✓ extract() — recovered: '{$extracted}'" . PHP_EOL;
    echo "✓ round-trip match: " . var_export($extracted === $secret, true) . PHP_EOL;

    @unlink($carrierPath);
    @unlink($outputPath);
} catch (\Throwable $e) {
    echo "✗ FAIL: " . $e->getMessage() . PHP_EOL;
}

echo PHP_EOL . "=== All tests complete ===" . PHP_EOL;
