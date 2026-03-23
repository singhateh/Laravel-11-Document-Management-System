# Architecture Changes

## StegoLock Implementation Analysis

Based on analysis of the StegoLock architecture document and the actual implementation in the project, here's a comprehensive breakdown of what's implemented, the differences, and changes.

---

## ✅ **What's Implemented (Matches Architecture)**

### **1. Five Normalized Services (100% Implemented)**

All five services from the architecture are implemented in [`app/Services/Stego/`](app/Services/Stego):

| Service | File | Status |
|---------|------|--------|
| **Cryptography Service** | [`CryptoService.php`](app/Services/Stego/CryptoService.php) | ✅ Complete |
| **Steganography Service** | [`StegoService.php`](app/Services/Stego/StegoService.php) | ✅ Complete |
| **Segmentation Service** | [`SegmentationService.php`](app/Services/Stego/SegmentationService.php) | ✅ Complete |
| **Cloud Storage Service** | [`CloudStorageService.php`](app/Services/Stego/CloudStorageService.php) | ✅ Complete |
| **Persistence Service** | [`PersistenceService.php`](app/Services/Stego/PersistenceService.php) | ✅ Complete |

### **2. Master Key Derivation (MKD) System ✓**

- **Implemented in**: [`CryptoService::deriveMasterKey()`](app/Services/Stego/CryptoService.php:51)
- **Algorithm**: PBKDF2-SHA256 with 100,000 iterations (configurable via [`config/stegolock.php`](config/stegolock.php:75))
- **DEK Derivation**: [`CryptoService::deriveDEK()`](app/Services/Stego/CryptoService.php:91) - 10,000 iterations
- **UX Solution**: Users authenticate once per session; Master Key stored server-side in session

### **3. AES-256-GCM Encryption ✓**

- **Implemented in**: [`CryptoService`](app/Services/Stego/CryptoService.php)
- **Specs**: 256-bit keys, 12-byte IV, 128-bit auth tag
- **Tampering Detection**: Authentication tag validation on decryption

### **4. Quality Metrics ✓**

- **PSNR**: Calculated for image carriers (threshold ≥ 40 dB)
- **SNR**: Calculated for audio carriers (threshold ≥ 35 dB)
- **Implementation**: [`StegoService::psnr()`](app/Services/Stego/StegoService.php:99)

### **5. Role-Based Access Control ✓**

- **Roles**: Owner, Viewer, Admin
- **Policy**: [`StegoDocumentPolicy`](app/Policies/StegoDocumentPolicy.php) enforces access
- **Sharing**: [`StegoDocumentGrant`](app/Models/StegoDocumentGrant.php) model for granting viewer access

### **6. Database Schema ✓**

All tables implemented via migrations:
- [`stego_documents`](database/migrations/2026_02_20_000004_create_stego_documents_table.php)
- [`stego_carriers`](database/migrations/2026_02_20_000002_create_stego_carriers_table.php)
- [`stego_segments`](database/migrations/2026_02_20_000003_create_stego_segments_table.php)
- [`stego_document_grants`](database/migrations/2026_03_06_000001_create_stego_document_grants_table.php)
- [`access_logs`](database/migrations/2024_01_01_000006_create_access_logs_table.php)

### **7. Segmentation ✓**

- **Split**: [`SegmentationService::segment()`](app/Services/Stego/SegmentationService.php:36)
- **Reassemble**: [`SegmentationService::reassemble()`](app/Services/Stego/SegmentationService.php:80) with hash verification

### **8. Orchestrator Service ✓**

- **Implemented**: [`StegoDocumentService`](app/Services/Stego/StegoDocumentService.php) coordinates all 5 services
- **Encode workflow**: Crypto → Segmentation → Stego → Cloud → Persistence
- **Decode workflow**: Persistence → Cloud → Stego → Segmentation → Crypto

---

## 🔄 **Key Differences & Changes from Architecture**

### **1. Cloud Storage: Local Disk for Testing, Cloudflare R2 for Production**

| Environment | Storage Solution | Purpose |
|-------------|------------------|---------|
| **Testing/Development** | Local disk (`storage/app/`) | No external dependencies; instant setup |
| **Production (Final)** | Cloudflare R2 | S3-compatible; cost-effective; global edge |

**Implementation Details**:

- **Current**: [`CloudStorageService.php`](app/Services/Stego/CloudStorageService.php) uses Laravel's local disk
- **Production Path**: Cloudflare R2 (S3-compatible API)
  - No egress fees (unlike AWS S3)
  - Global edge network for low latency
  - S3-compatible API (easy migration from local)
  - Built-in DDoS protection

**Configuration for Production**:
```env
# Cloudflare R2 Configuration (Production)
AWS_ACCESS_KEY_ID=your_r2_access_key
AWS_SECRET_ACCESS_KEY=your_r2_secret_key
AWS_DEFAULT_REGION=auto
AWS_BUCKET=your-bucket-name
AWS_ENDPOINT=https://<account_id>.r2.cloudflarestorage.com
AWS_USE_PATH_STYLE_ENDPOINT=true
```

**Impact**:
- ✅ **Testing**: Simple local storage; no cloud setup required
- ✅ **Production**: Cloudflare R2 provides scalability, durability, and cost efficiency
- ✅ **Migration**: S3-compatible API means minimal code changes

### **2. Authentication: Sanctum Instead of JWT**

| Architecture | Implementation |
|--------------|----------------|
| JWT tokens (tymon/jwt-auth) | Laravel Sanctum personal access tokens |
| JWT signed with server secret | Sanctum token-based auth |
| 8-hour token expiry | Configurable token expiry |

**Implementation**: [`AuthController`](app/Http/Controllers/Api/AuthController.php) uses Sanctum

### **3. Master Key Storage: Session-Based**

| Architecture | Implementation |
|--------------|----------------|
| Master Key Token (JWT) returned to client | Master Key stored in server-side session |
| Client sends JWT with each request | Session cookie manages authentication |
| JWT contains user_id, role, exp | Session stores `stego_mkd` (Master Key) |

**Implementation**: [`HasStegoEncoding::resolveSessionKey()`](app/Http/Concerns/HasStegoEncoding.php:39)

### **4. Steganography Drivers: Dual Implementation**

| Architecture | Implementation |
|--------------|----------------|
| Single LSB implementation | Two drivers: Python (default) & PHP |
| Python stegano library | Configurable via `STEGO_DRIVER` env var |
| - | PHP GD-based fallback (no dependencies) |

**Implementation**: [`StegoService`](app/Services/Stego/StegoService.php) supports both drivers

### **5. Database Schema: Enhanced & Normalized**

**Additional fields not in architecture**:
- `stego_documents.compressed` - Compression flag
- `stego_documents.status` - Processing status (pending/processing/completed/failed)
- `stego_documents.decoding_status` - Decode job status
- `stego_documents.failed_reason` - Error tracking
- `stego_carriers.psnr` - Quality metric storage
- `stego_segments.encrypted_chunk` - Base64-encoded chunks stored in DB

**3NF Fix**: `s3_url` is now a computed attribute (derived from `s3_key`) rather than stored

### **6. Queue-Based Processing**

| Architecture | Implementation |
|--------------|----------------|
| Synchronous processing | Asynchronous jobs via Laravel Queue |
| - | [`EncodeStegoDocumentJob`](app/Jobs/EncodeStegoDocumentJob.php) |
| - | [`DecodeStegoDocumentJob`](app/Jobs/DecodeStegoDocumentJob.php) |

**Benefit**: Non-blocking UI; better for large files

### **7. Carrier Capacity Calculation**

| Architecture | Implementation |
|--------------|----------------|
| Fixed 2 MB segments | Dynamic capacity-based segmentation |
| - | [`StegoService::capacity()`](app/Services/Stego/StegoService.php) calculates per-carrier |
| - | Cached by file hash for performance |

---

## 📊 **Architecture vs Implementation Summary**

| Component | Architecture | Implementation | Status |
|-----------|--------------|----------------|--------|
| **5 Services** | 5 microservices | 5 service classes | ✅ Match |
| **MKD** | PBKDF2 100k iterations | PBKDF2 100k iterations | ✅ Match |
| **Encryption** | AES-256-GCM | AES-256-GCM | ✅ Match |
| **Quality Metrics** | PSNR/SNR thresholds | PSNR/SNR with thresholds | ✅ Match |
| **RBAC** | Owner/Viewer/Admin | Owner/Viewer/Admin | ✅ Match |
| **Cloud Storage** | AWS S3 | Local (testing) / Cloudflare R2 (production) | ⚠️ Changed |
| **Auth** | JWT | Sanctum | ⚠️ Changed |
| **Master Key** | JWT token | Server session | ⚠️ Changed |
| **Processing** | Synchronous | Async jobs | ⚠️ Enhanced |
| **Stego Driver** | Python only | Python + PHP | ⚠️ Enhanced |

---

## 🎯 **Key Takeaways**

1. **Core cryptographic architecture is fully implemented** - MKD, AES-256-GCM, DEK derivation all match the spec

2. **Five-service boundary is maintained** - Clean separation of concerns as designed

3. **Pragmatic changes for Laravel ecosystem**:
   - Sanctum instead of JWT (already included in Laravel)
   - Local storage for testing; Cloudflare R2 for production (simpler, cost-effective)
   - Session-based Master Key (more secure than client-side JWT)

4. **Enhanced with production features**:
   - Async job processing
   - Dual steganography drivers
   - Better error tracking and status management

5. **Database schema is more robust** - Added status tracking, compression flags, and proper 3NF normalization

6. **Cloud Storage Strategy**:
   - **Testing/Development**: Local disk storage (`storage/app/`) - no external dependencies
   - **Production (Final)**: Cloudflare R2 - S3-compatible, no egress fees, global edge network

The implementation faithfully follows the architectural vision while making practical adjustments for the Laravel framework and simplifying deployment complexity. The dual storage approach (local for testing, Cloudflare R2 for production) provides flexibility while maintaining the ability to scale globally.
