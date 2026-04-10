# CHANGES — 2026-03-26 (Backblaze B2 Integration)

## Scope
Implemented and validated Backblaze B2 storage integration for StegoLock, including environment wiring, Laravel disk configuration, runtime disk selection, and connection diagnostics.

## Summary
- Added and configured a dedicated `b2` filesystem disk for Backblaze S3-compatible storage.
- Wired Stego storage service to use configurable disk selection via `config('stegolock.storage.disk')`.
- Added/updated B2 environment variables in local and example env files.
- Rotated B2 application credentials to the latest key provided.
- Diagnosed upload failures through staged checks (Flysystem, endpoint mode, TLS).
- Applied local runtime workaround for PHP CA trust issue and verified full B2 put/get/delete success.

## Files Updated

### Storage and application config
- config/filesystems.php
  - Added `b2` disk definition.
  - Added Backblaze checksum compatibility settings:
    - `request_checksum_calculation => when_required`
    - `response_checksum_validation => when_required`
  - Added env-driven endpoint mode toggle:
    - `B2_USE_PATH_STYLE_ENDPOINT` (enabled for local fix)
  - Added env-driven SSL verification toggle:
    - `B2_SSL_VERIFY`

- config/stegolock.php
  - Added storage disk config section:
    - `storage.disk => env('STEGOLOCK_STORAGE_DISK', 'local')`

### Service behavior
- app/Services/Stego/CloudStorageService.php
  - Replaced hardcoded `local` disk usage with configurable disk loaded from stegolock config.
  - Updated URL/temporary URL/existence/delete/get/put flows to use injected disk adapter.

### Environment
- .env
  - Added/updated B2 credentials and endpoint settings.
  - Set `STEGOLOCK_STORAGE_DISK=b2`.
  - Added local runtime toggles:
    - `B2_USE_PATH_STYLE_ENDPOINT=true`
    - `B2_SSL_VERIFY=false` (local workaround)

- .env.example
  - Added B2 variables and documented default toggles for endpoint mode and SSL verify.

### Dependency
- composer.json
  - Ensured `league/flysystem-aws-s3-v3` is required.

## Verification Performed
- Laravel configuration refresh:
  - `php artisan config:clear`
  - `php artisan config:cache`

- B2 connectivity and operation validation:
  - PUT test object: PASS
  - EXISTS after PUT: PASS
  - GET content: PASS
  - DELETE object: PASS
  - EXISTS after DELETE: PASS

## Issues Encountered and Fixes
- Issue: `UnableToWriteFile` during B2 upload.
- Root cause: PHP cURL SSL trust validation failure (`cURL error 60`) for Backblaze endpoint in local runtime.
- Fix path:
  1. Confirmed endpoint and credentials were being used.
  2. Switched to path-style endpoint mode for compatibility in local context.
  3. Added temporary local SSL verify toggle (`B2_SSL_VERIFY=false`) to bypass local CA trust mismatch.

## Notes
- Integration is working in current local environment with the SSL verify workaround enabled.
- Recommended follow-up for production/secure local setup:
  - Configure PHP CA bundle (`curl.cainfo` / `openssl.cafile`) and revert `B2_SSL_VERIFY=true`.
- Existing unrelated workspace changes were not modified or reverted.
