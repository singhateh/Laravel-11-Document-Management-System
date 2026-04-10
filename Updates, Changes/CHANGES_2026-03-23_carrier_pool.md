# Carrier Pool Implementation - Week 1 Progress

## Overview
Implementation of the carrier pool feature for StegoLock, allowing users to upload carrier images once and reuse them across multiple encode operations without re-uploading.

## Problem Solved
Previously, users had to upload N carrier images every time they encoded a document. If a document required 25 segments, they would upload 25 images. If any failed PSNR validation, they had to start over. This made StegoLock impractical for large documents.

## Solution: Carrier Pool
Users upload images **once** into a personal pool. The system validates each image at upload time (PSNR, capacity). When encoding, the system automatically picks images from the pool — the user uploads nothing per operation.

## Implementation Status: ✅ COMPLETE

### Files Created

#### 1. Database Migration
**File:** `database/migrations/2026_03_23_000001_add_pool_columns_to_stego_carriers_table.php`

Added columns to `stego_carriers` table:
- `validation_status` (enum: pending, valid, invalid) - default: 'pending'
- `validation_error` (string, nullable) - stores validation failure reason
- `capacity_bytes` (unsigned big integer, nullable) - measured carrier capacity
- `is_in_use` (boolean) - default: false - locks carrier during active encode
- `validated_at` (timestamp, nullable) - when validation completed

Added composite index on `[uploaded_by, validation_status, is_in_use]` for efficient pool queries.

#### 2. Background Validation Job
**File:** `app/Jobs/ValidateCarrierJob.php`

- Implements `ShouldQueue` for background processing
- Measures carrier capacity using `StegoService::capacity()`
- For images, measures baseline PSNR using `StegoService::psnr()`
- Validates PSNR >= 40 dB threshold
- Updates carrier record with validation results
- Marks carrier as 'valid' or 'invalid' with error reason
- 3 retry attempts on failure

#### 3. Carrier Pool Controller
**File:** `app/Http/Controllers/Api/CarrierPoolController.php`

Endpoints:
- `POST /api/stego/carriers` - Upload carrier into pool
  - Validates file type (png, bmp, jpeg, jpg) and size (max 100MB)
  - Enforces quota: max 50 carriers per user, max 500MB total pool size
  - Stores file in `stego/carriers/{user_id}/` directory
  - Dispatches `ValidateCarrierJob` for background validation
  - Returns 202 Accepted with carrier_id and validation_status

- `GET /api/stego/carriers` - List pool carriers
  - Supports optional `status` filter (pending, valid, invalid)
  - Returns paginated list (20 per page)
  - Includes: id, name, file_type, mime_type, size, psnr, capacity_bytes, validation_status, validation_error, is_in_use, validated_at, created_at

- `DELETE /api/stego/carriers/{id}` - Remove carrier from pool
  - Prevents deletion if carrier is currently in use (returns 409)
  - Deletes file from storage
  - Deletes carrier record

#### 4. Carrier Pool Selector Service
**File:** `app/Services/Stego/CarrierPoolSelector.php`

Methods:
- `select(int $userId, int $requiredBytes): Collection`
  - Selects minimum set of valid carriers from pool
  - Uses greedy bin-packing: largest carriers first
  - Implements `lockForUpdate()` to prevent race conditions
  - Throws `RuntimeException` if pool has insufficient capacity

- `markInUse(Collection $carriers): void`
  - Marks selected carriers as `is_in_use = true`

- `release(Collection $carriers): void`
  - Releases carriers back to pool (`is_in_use = false`)

- `getAvailableCapacity(int $userId): int`
  - Returns total available bytes in user's pool

- `hasSufficientCapacity(int $userId, int $requiredBytes): bool`
  - Checks if pool has enough capacity

#### 5. Updated StegoCarrier Model
**File:** `app/Models/StegoCarrier.php`

Added to `$fillable`:
- `validation_status`
- `validation_error`
- `capacity_bytes`
- `is_in_use`
- `validated_at`

Added to `$casts`:
- `capacity_bytes` => 'integer'
- `is_in_use` => 'boolean'
- `validated_at` => 'datetime'

#### 6. Updated Configuration
**File:** `config/stegolock.php`

Added `carrier_pool` configuration:
```php
'carrier_pool' => [
    'max_carriers_per_user' => 50,
    'max_total_size_bytes' => 500 * 1024 * 1024, // 500MB
    'psnr_threshold' => 40.0,
],
```

#### 7. Updated API Routes
**File:** `routes/api.php`

Added routes within `stego` prefix:
- `GET /api/stego/carriers` - List pool carriers
- `POST /api/stego/carriers` - Upload carrier to pool
- `DELETE /api/stego/carriers/{id}` - Remove carrier from pool
- `POST /api/stego/preflight` - Check pool capacity before encoding

#### 8. Updated StegoDocumentService
**File:** `app/Services/Stego/StegoDocumentService.php`

Modified `encode()` method:
- Added `CarrierPoolSelector` dependency injection
- Made `$carrierPaths` parameter optional (nullable)
- When `$carrierPaths` is null, uses `CarrierPoolSelector::select()` to pick carriers from pool
- Calculates required bytes from ciphertext
- Selects carriers from pool based on capacity
- Updates existing carrier records when using pool carriers
- Falls back to creating new carrier records for direct path uploads

#### 9. Preflight Check Endpoint
**File:** `app/Http/Controllers/Api/StegoDocumentController.php`

Added `preflight(Request $request)` method:
- Validates `document_id` exists
- Reads document plaintext to estimate size
- Queries available carriers from pool (valid, not in use)
- Calculates total available bytes
- Returns JSON response:
  ```json
  {
    "can_encode": true/false,
    "required_bytes": 1234567,
    "available_bytes": 5000000,
    "valid_carriers": 5,
    "message": "Sufficient carrier capacity available."
  }
  ```

## Key Features

### 1. Background Validation
- Carriers are validated asynchronously after upload
- Upload response is instant (202 Accepted)
- Validation includes PSNR measurement for images
- Only pre-validated carriers can be used for encoding

### 2. Quota Management
- Maximum 50 carriers per user
- Maximum 500MB total pool size per user
- Prevents resource exhaustion

### 3. Concurrency Safety
- `lockForUpdate()` prevents race conditions
- Two simultaneous encode requests cannot select the same carriers
- Carriers are locked during encode operation

### 4. Carrier Reuse
- Carriers remain in pool after encoding
- Can be reused across multiple encode operations
- Only released when explicitly deleted or when encode fails

### 5. Preflight Check
- Users can verify pool capacity before encoding
- Prevents failed encode attempts
- Provides clear feedback on capacity status

## API Usage Examples

### Upload Carrier to Pool
```bash
POST /api/stego/carriers
Content-Type: multipart/form-data

carrier: [image file]
name: "My Carrier Image"
```

Response (202):
```json
{
  "carrier_id": 123,
  "validation_status": "pending",
  "message": "Carrier queued for validation. Check status before encoding."
}
```

### List Pool Carriers
```bash
GET /api/stego/carriers?status=valid
```

Response (200):
```json
{
  "data": [
    {
      "id": 123,
      "name": "My Carrier Image",
      "file_type": "png",
      "mime_type": "image/png",
      "size": 2048576,
      "psnr": 42.5,
      "capacity_bytes": 1500000,
      "validation_status": "valid",
      "validation_error": null,
      "is_in_use": false,
      "validated_at": "2026-03-23T10:30:00Z",
      "created_at": "2026-03-23T10:29:45Z"
    }
  ],
  "links": {...},
  "meta": {...}
}
```

### Preflight Check
```bash
POST /api/stego/preflight
Content-Type: application/json

{
  "document_id": 456
}
```

Response (200):
```json
{
  "can_encode": true,
  "required_bytes": 1234567,
  "available_bytes": 5000000,
  "valid_carriers": 5,
  "message": "Sufficient carrier capacity available."
}
```

### Encode Using Pool
```bash
POST /api/stego/encode
Content-Type: application/json

{
  "document_id": 456
}
```

Note: No `carriers` field needed - system automatically selects from pool.

## Database Schema Changes

### stego_carriers Table (New Columns)
```sql
ALTER TABLE stego_carriers ADD COLUMN validation_status ENUM('pending', 'valid', 'invalid') DEFAULT 'pending';
ALTER TABLE stego_carriers ADD COLUMN validation_error VARCHAR(255) NULL;
ALTER TABLE stego_carriers ADD COLUMN capacity_bytes BIGINT UNSIGNED NULL;
ALTER TABLE stego_carriers ADD COLUMN is_in_use BOOLEAN DEFAULT FALSE;
ALTER TABLE stego_carriers ADD COLUMN validated_at TIMESTAMP NULL;
ALTER TABLE stego_carriers ADD INDEX idx_pool_query (uploaded_by, validation_status, is_in_use);
```

## Testing Recommendations

1. **Upload Flow**
   - Upload valid carrier image → should return 202 with pending status
   - Wait for validation → status should change to valid
   - Upload invalid file type → should return 422

2. **Pool Management**
   - List carriers → should show all user's carriers
   - Filter by status → should show only matching carriers
   - Delete carrier → should remove from pool
   - Delete carrier in use → should return 409

3. **Encoding**
   - Preflight check with sufficient capacity → should return can_encode: true
   - Preflight check with insufficient capacity → should return can_encode: false
   - Encode with pool → should automatically select carriers
   - Encode with insufficient pool → should throw RuntimeException

4. **Concurrency**
   - Two simultaneous encode requests → should not select same carriers
   - Carrier should be locked during encode operation

## Next Steps (Week 2 & 3)

### Week 2: Bin-Packing Segmentation
- Update `SegmentationService::split()` to fill largest carriers first
- Reduces number of carriers needed per encode

### Week 3: System Default Carriers
- Seed system carriers for new users
- Allow fallback to system carriers when user pool is insufficient

## Benefits

1. **Improved UX**: Users upload carriers once, reuse across multiple encodes
2. **Faster Encoding**: No upload delay during encode operation
3. **Better Validation**: Carriers validated at upload time, not encode time
4. **Resource Management**: Quotas prevent abuse
5. **Concurrency Safety**: Locking prevents race conditions
6. **Transparency**: Preflight check shows capacity status before encoding

## Conclusion

Week 1 implementation is complete. The carrier pool feature is fully functional and ready for testing. Users can now upload carrier images once, have them validated in the background, and reuse them across multiple encode operations without re-uploading.
