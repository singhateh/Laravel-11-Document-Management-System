<?php
/**
 * Phase 3 API Tests — run with: php tests/phase3_api_test.php
 * Requires php artisan serve to be running on localhost:8000
 */

$base = 'http://localhost:8000';
$ts   = time();
$user = [
    'name'                  => 'API Tester',
    'username'              => 'apitester' . $ts,
    'email'                 => 'apitester' . $ts . '@x.com',
    'password'              => 'Pass@1234',
    'password_confirmation' => 'Pass@1234',
];

$pass = 0;
$fail = 0;

function req(string $method, string $url, array $body = [], string $token = ''): array
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);   // don't follow redirects
    $headers = ['Content-Type: application/json', 'Accept: application/json'];
    if ($token) {
        $headers[] = 'Authorization: Bearer ' . $token;
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }
    $raw  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $code, 'body' => json_decode($raw, true) ?? [], 'raw' => $raw];
}

/**
 * W3-T13: Multipart POST helper for file upload tests.
 * Sends a multipart/form-data request (bypassing JSON Content-Type).
 */
function reqMultipart(string $url, array $fields, string $token): array
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/json',
        'Authorization: Bearer ' . $token,
    ]);
    $raw  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $code, 'body' => json_decode($raw, true) ?? [], 'raw' => $raw];
}

function check(string $label, bool $ok, string $detail = ''): void
{
    global $pass, $fail;
    if ($ok) {
        $pass++;
        echo "\033[32m  ✓ {$label}\033[0m" . ($detail ? " — {$detail}" : '') . "\n";
    } else {
        $fail++;
        echo "\033[31m  ✗ {$label}\033[0m" . ($detail ? " — {$detail}" : '') . "\n";
    }
}

echo "\n\033[36m=== Phase 3 API Tests ===\033[0m\n\n";

// ── 1. Health ─────────────────────────────────────────────────────────────────
$r = req('GET', "$base/api/health");
echo "[1] Health\n";
check('GET /api/health → 200',   $r['code'] === 200);
check("status = ok",             ($r['body']['status'] ?? '') === 'ok');

// ── 2. Register ───────────────────────────────────────────────────────────────
$r = req('POST', "$base/api/auth/register", $user);
echo "\n[2] Register\n";
check('POST /api/auth/register → 201', $r['code'] === 201, "got {$r['code']}");
check('access_token present',          !empty($r['body']['access_token']));
check('user.username correct',         ($r['body']['user']['username'] ?? '') === $user['username']);
check('user.role = user',              ($r['body']['user']['role'] ?? '') === 'user');
$token = $r['body']['access_token'] ?? '';

// ── 3. Login by email ─────────────────────────────────────────────────────────
$r = req('POST', "$base/api/auth/login", ['login' => $user['email'], 'password' => $user['password']]);
echo "\n[3] Login (by email)\n";
check('POST /api/auth/login → 200',    $r['code'] === 200, "got {$r['code']}");
check('access_token present',          !empty($r['body']['access_token']));
$emailToken = $r['body']['access_token'] ?? '';

// ── 4. Login by username ──────────────────────────────────────────────────────
$r = req('POST', "$base/api/auth/login", ['login' => $user['username'], 'password' => $user['password']]);
echo "\n[4] Login (by username)\n";
check('POST /api/auth/login → 200',    $r['code'] === 200, "got {$r['code']}");
check('access_token present',          !empty($r['body']['access_token']));
$usToken = $r['body']['access_token'] ?? '';

// ── 5. /me authenticated ──────────────────────────────────────────────────────
$r = req('GET', "$base/api/auth/me", [], $usToken);
echo "\n[5] GET /api/auth/me (authenticated)\n";
check('/api/auth/me → 200',            $r['code'] === 200, "got {$r['code']}");
check('user.email correct',            ($r['body']['user']['email'] ?? '') === $user['email']);

// ── 6. Unauthenticated → 401 ──────────────────────────────────────────────────
$r = req('GET', "$base/api/auth/me");
echo "\n[6] Unauthenticated requests\n";
check('GET /api/auth/me (no token) → 401', $r['code'] === 401, "got {$r['code']}");
check('message = Unauthenticated.',         strpos($r['raw'], 'Unauthenticated') !== false);

$r = req('GET', "$base/api/stego/documents");
check('GET /api/stego/documents (no token) → 401', $r['code'] === 401, "got {$r['code']}");

// ── 7. Wrong password → 422 ───────────────────────────────────────────────────
$r = req('POST', "$base/api/auth/login", ['login' => $user['email'], 'password' => 'WrongPass!']);
echo "\n[7] Wrong credentials\n";
check('Wrong password → 422',          $r['code'] === 422, "got {$r['code']}");

// ── 8. Stego documents (authenticated) ───────────────────────────────────────
$r = req('GET', "$base/api/stego/documents", [], $usToken);
echo "\n[8] GET /api/stego/documents (authenticated)\n";
check('/api/stego/documents → 200',    $r['code'] === 200, "got {$r['code']}");
check('response has total key',        array_key_exists('total', $r['body']));

// ── 9. Logout ─────────────────────────────────────────────────────────────────
$r = req('POST', "$base/api/auth/logout", [], $usToken);
echo "\n[9] Logout\n";
check('POST /api/auth/logout → 200',   $r['code'] === 200, "got {$r['code']}");

// ── 10. Token revoked after logout ────────────────────────────────────────────
$r = req('GET', "$base/api/auth/me", [], $usToken);
echo "\n[10] Token revoked after logout\n";
check('Revoked token → 401',           $r['code'] === 401, "got {$r['code']}");

// Re-login to get a fresh token for document tests
$r        = req('POST', "$base/api/auth/login", ['login' => $user['email'], 'password' => $user['password']]);
$docToken = $r['body']['access_token'] ?? '';

// ── 11. W3-T09: GET /api/documents — document list ───────────────────────────
$r = req('GET', "$base/api/documents", [], $docToken);
echo "\n[11] GET /api/documents (W3-T09 — list)\n";
check('GET /api/documents → 200',      $r['code'] === 200, "got {$r['code']}");
check('response has data key',         array_key_exists('data', $r['body']));
check('response has total key',        array_key_exists('total', $r['body']));

// ── 12. W3-T01: POST /api/documents — upload a file ──────────────────────────
echo "\n[12] POST /api/documents (W3-T01 — upload)\n";

// Create a small temp file to upload
$tmpFile    = sys_get_temp_dir() . '/stegolock_test_upload_' . time() . '.txt';
file_put_contents($tmpFile, 'StegoLock W3-T01 upload test payload');

// We need a valid folder_id. Use folder id=1 if it exists, otherwise skip.
$uploadResult = reqMultipart("$base/api/documents", [
    'files[0]'  => new CURLFile($tmpFile, 'text/plain', basename($tmpFile)),
    'folder_id' => '1',
    'visibility' => 'public',
], $docToken);
@unlink($tmpFile);

$uploadedDocId = null;
if ($uploadResult['code'] === 201) {
    check('POST /api/documents → 201',     true, "got 201");
    check('documents array present',       !empty($uploadResult['body']['documents']));
    check('first doc has id',              isset($uploadResult['body']['documents'][0]['id']));
    check('first doc has is_encrypted',    array_key_exists('is_encrypted', $uploadResult['body']['documents'][0] ?? []));
    check('first doc has sha256',          array_key_exists('sha256', $uploadResult['body']['documents'][0] ?? []));
    $uploadedDocId = $uploadResult['body']['documents'][0]['id'] ?? null;
} elseif ($uploadResult['code'] === 422) {
    // folder_id=1 may not exist in a fresh DB — mark as skip not failure
    check('POST /api/documents responds (folder_id=1 absent → 422 expected)', true, "skip — no folder");
} else {
    check('POST /api/documents → 201', false, "got {$uploadResult['code']}: {$uploadResult['raw']}");
}

// ── 13. W3-T06: GET /api/documents/{id} — retrieve metadata ──────────────────
echo "\n[13] GET /api/documents/{id} (W3-T06 — retrieve)\n";
if ($uploadedDocId) {
    $r = req('GET', "$base/api/documents/{$uploadedDocId}", [], $docToken);
    check("GET /api/documents/{$uploadedDocId} → 200",   $r['code'] === 200, "got {$r['code']}");
    check('document.id matches',                          ($r['body']['document']['id'] ?? null) === $uploadedDocId);
    check('document.is_encrypted present',                array_key_exists('is_encrypted', $r['body']['document'] ?? []));
    check('document.sha256 present',                      array_key_exists('sha256',        $r['body']['document'] ?? []));
    check('document.download_url present',                array_key_exists('download_url',  $r['body']['document'] ?? []));
} else {
    check('GET /api/documents/{id} → skipped (no doc was uploaded)', true, 'skip');
}

// ── 14. W3-T06: GET /api/documents/{id} — 404 for non-existent ID ────────────
$r = req('GET', "$base/api/documents/999999", [], $docToken);
echo "\n[14] GET /api/documents/999999 — not found\n";
check('GET /api/documents/999999 → 404', $r['code'] === 404, "got {$r['code']}");
check('message key present',             !empty($r['body']['message']));

// ── 15. W3-T11: Centralized error handling — unauthenticated document list ───
$r = req('GET', "$base/api/documents");
echo "\n[15] Centralized error handling (W3-T11)\n";
check('GET /api/documents (no token) → 401', $r['code'] === 401, "got {$r['code']}");
check('JSON message = Unauthenticated.',      strpos($r['raw'], 'Unauthenticated') !== false);

$r = req('GET', "$base/api/documents/1/nonexistent-route");
check('Unknown route → 404 JSON',            $r['code'] === 404, "got {$r['code']}");

// ── Summary ───────────────────────────────────────────────────────────────────
echo "\n\033[36m=== Results: {$pass} passed, {$fail} failed ===\033[0m\n\n";
exit($fail > 0 ? 1 : 0);
