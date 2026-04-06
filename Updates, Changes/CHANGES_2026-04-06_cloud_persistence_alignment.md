# CHANGES — 2026-04-06 (Cloud Persistence Alignment for Stego Encode/Decode)

## Scope
Implemented production runtime fixes (not test-only) to align Stego encode/decode persistence with cloud storage requirements:

1. ciphertext is uploaded via `CloudStorageService` and `stego_documents.s3_key` stores a real cloud object key
2. decode reads ciphertext from cloud when configured
3. segment/derived carrier keys are consistently persisted for retrieval and audits

---

## Step-by-Step Implementation

## Step 1 — Upload ciphertext via CloudStorageService

### Changes
- Updated `StegoDocumentService::encode()` to replace local-only ciphertext write with cloud upload.
- Replaced `storeCiphertextOnLocalDisk()` with `uploadCiphertextToCloud()`.
- New key format uses `CloudStorageService::documentKey($userId, $stegoDocumentId) . '.enc'`.

### Files
- `app/Services/Stego/StegoDocumentService.php`

### Outcome
- `stego_documents.s3_key` now stores a true cloud key path.
- Ciphertext persistence follows configured StegoLock disk (`b2`, `local`, etc.) via `CloudStorageService`.

---

## Step 2 — Decode reads ciphertext from cloud when configured

### Changes
- Updated `loadCiphertextForDecode()` to attempt cloud retrieval first:
  - `CloudStorageService::getContents($stegoDoc->s3_key)`
- Added backward-compatible fallback to legacy local-path behavior for older records.

### Files
- `app/Services/Stego/StegoDocumentService.php`

### Outcome
- Decode path now uses cloud object retrieval for new records.
- Legacy records remain decodable without migration.

---

## Step 3 — Persist segment/derived carrier keys consistently

### Changes
- During encode, each uploaded stego artifact key is now persisted into `stego_segments.s3_key`.
- Previously this field was always set to `null`.

### Files
- `app/Services/Stego/StegoDocumentService.php`

### Outcome
- Segment-level cloud key traceability is available for retrieval diagnostics and audit trails.
- Works for both manual carriers and pool-derived carriers.

---

## Additional Consistency Improvement

### Changes
- Updated computed `s3_url` accessors to resolve against configured StegoLock storage disk context.
- Local disk returns local path; non-local disks use configured disk URL + object key.

### Files
- `app/Models/StegoDocument.php`
- `app/Models/StegoCarrier.php`

### Outcome
- URL/path derivation no longer assumes local-only behavior for cloud-backed records.

---

## Validation Performed

- `php -l app/Services/Stego/StegoDocumentService.php`
- `php -l app/Models/StegoDocument.php`
- `php -l app/Models/StegoCarrier.php`
- VS Code diagnostics check (`get_errors`) on all modified files: no errors found

### End-to-End Runtime Verification (Encode + Decode + B2 Existence)

Executed one real encode/decode cycle through `StegoDocumentService` and immediately checked object existence on the configured StegoLock disk (`b2`).

Observed runtime result:

- `disk`: `b2`
- `stego_document_id`: `2`
- `stego_document_status`: `ready`
- `decode_matches_plaintext`: `true`
- `document_s3_key`: `stego/documents/1/2.enc`
- `document_exists_on_disk`: `true`
- `segment_count`: `1`
- `missing_segments`: `0`
- segment key sample: `stego/carriers/1/doc2_seg0_ek8tg6LTVKERUPKlkRflvPGqUY7GTqXd37ZD0ajT.png` (`exists=true`)

Conclusion:

- Ciphertext is now persisted with a real cloud key.
- Decode succeeds using the same runtime path and key material.
- Segment/derived carrier keys are persisted and resolvable on B2.

---

## Notes

- This update targets runtime behavior and persistence paths used by encode/decode flows.
- Legacy data compatibility was preserved in decode via fallback local read logic.
- Existing queue + job architecture remains unchanged.
