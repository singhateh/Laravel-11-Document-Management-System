# StegoLock — Backblaze B2 Setup & Laravel Integration Guide

> **Note:** B2 is S3-compatible, so the integration is nearly identical to the R2 guide
> with a few key differences called out explicitly. No extra Laravel package needed.

---

## Part 1 — Backblaze Account & Bucket Setup

### Step 1 — Create a Backblaze Account

1. Go to [backblaze.com](https://www.backblaze.com) and click **Sign Up**
2. Fill in your name, email, and password — **no credit card required**
3. Verify your email address

---

### Step 2 — Enable B2 Cloud Storage

1. After logging in, click **B2 Cloud Storage** in the left sidebar
2. If prompted, click **Enable B2 Cloud Storage** on your account
3. The B2 section will now show **Buckets** and **Application Keys** in the sidebar

---

### Step 3 — Create a Bucket

1. In the left sidebar under **B2 Cloud Storage**, click **Buckets**
2. Click **Create a Bucket**
3. Fill in the form:

| Field | Value |
|---|---|
| Bucket Name | `stegolock-storage` (must be globally unique — try adding your username if taken) |
| Files in Bucket | **Private** ← important, keep this private |
| Default Encryption | Disabled (StegoLock handles its own encryption) |
| Object Lock | Disabled |

4. Click **Create a Bucket**
5. After creation, click on the bucket name and find the **Endpoint** — it looks like:
   ```
   s3.us-west-004.backblazeb2.com
   ```
   **Copy this — you need it for Laravel config.** The region is the part between `s3.` and `.backblazeb2.com` (e.g. `us-west-004`).

> **Bucket names are globally unique across all B2 accounts.** If `stegolock-storage`
> is taken, try `stegolock-yourname` or similar.

---

### Step 4 — Create an Application Key

> ⚠️ **Do not use the Master Application Key** for your app.
> The master key is not supported by the S3-Compatible API and is a security risk.
> Always create a scoped application key.

1. In the left sidebar, click **Application Keys**
2. Click **Add a New Application Key**
3. Fill in the form:

| Field | Value |
|---|---|
| Name of Key | `stegolock-app-key` |
| Allow Access to Bucket | Select your `stegolock-storage` bucket specifically |
| Type of Access | **Read and Write** |
| Allow List All Bucket Names | ✅ **Check this box** — required for S3-compatible API |
| File Name Prefix | Leave blank |
| Duration | Leave blank (no expiry) |

4. Click **Create New Key**
5. A blue panel will appear showing two values — **copy them immediately**, they will not be shown again:

```
keyID:          xxxxxxxxxxxxxxxxxxxx       ← this is your Access Key ID
applicationKey: xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx  ← this is your Secret Access Key
```

---

## Part 2 — Laravel Integration

### Step 1 — Install the AWS S3 Flysystem package

```bash
composer require league/flysystem-aws-s3-v3 "^3.0"
```

---

### Step 2 — Add B2 credentials to `.env`

```env
# Backblaze B2
B2_KEY_ID=your_key_id_from_step_4
B2_APPLICATION_KEY=your_application_key_from_step_4
B2_REGION=us-west-004
B2_BUCKET=stegolock-storage
B2_ENDPOINT=https://s3.us-west-004.backblazeb2.com
B2_URL=https://stegolock-storage.s3.us-west-004.backblazeb2.com

# Tell StegoLock which disk to use
STEGOLOCK_STORAGE_DISK=b2
```

> Replace `us-west-004` with the actual region shown in your bucket's endpoint URL.
> Replace `stegolock-storage` with your actual bucket name if you used a different one.

---

### Step 3 — Register the B2 disk in `config/filesystems.php`

```php
// config/filesystems.php
'disks' => [

    'local' => [
        'driver' => 'local',
        'root'   => storage_path('app'),
    ],

    'b2' => [
        'driver'   => 's3',
        'key'      => env('B2_KEY_ID'),
        'secret'   => env('B2_APPLICATION_KEY'),
        'region'   => env('B2_REGION'),
        'bucket'   => env('B2_BUCKET'),
        'url'      => env('B2_URL'),
        'endpoint' => env('B2_ENDPOINT'),

        // B2 does NOT require path-style endpoints (unlike R2 which requires true)
        'use_path_style_endpoint' => false,

        // Surface errors instead of silently returning false
        'throw' => true,

        // ⚠️ REQUIRED for B2 compatibility with Laravel/Flysystem (2025)
        // B2 rejects the x-amz-checksum-crc32 header that Laravel sends by default.
        // Without these two lines, PUT and upload requests will fail with 400 Bad Request.
        'request_checksum_calculation'  => 'when_required',
        'response_checksum_validation'  => 'when_required',
    ],

],
```

> **The two checksum lines are critical.** As of early 2025, Backblaze B2 rejects
> requests containing the `x-amz-checksum-crc32` header that Laravel's Flysystem
> sends by default. Without `request_checksum_calculation => 'when_required'`,
> all file uploads will return a `400 Bad Request` error. This is a known B2-specific
> issue not present in R2 or S3.

---

### Step 4 — Update `config/stegolock.php`

```php
// config/stegolock.php
return [
    'storage' => [
        'disk' => env('STEGOLOCK_STORAGE_DISK', 'b2'),
    ],

    'carrier_pool' => [
        'max_carriers_per_user' => 50,
        'max_total_size_bytes'  => 500 * 1024 * 1024, // 500MB
    ],
];
```

---

### Step 5 — Verify the connection

Run this in `php artisan tinker` to confirm the disk is connected before touching
any application code:

```php
// In tinker
Storage::disk('b2')->put('test/connection-check.txt', 'hello from stegolock');
// Should return true

Storage::disk('b2')->exists('test/connection-check.txt');
// Should return true

Storage::disk('b2')->get('test/connection-check.txt');
// Should return "hello from stegolock"

Storage::disk('b2')->delete('test/connection-check.txt');
// Clean up
```

If any of these throw an error, check:
1. Your `B2_KEY_ID` and `B2_APPLICATION_KEY` are correct (not the master key)
2. Your `B2_REGION` matches the endpoint exactly
3. The application key has **Allow List All Bucket Names** checked
4. The two checksum lines are present in `config/filesystems.php`

---

### Step 6 — Confirm `StegoStorageService` uses the config disk

If you followed the R2 integration guide, your `StegoStorageService` already reads
from `config('stegolock.storage.disk')`. No changes needed — just the `.env` value
`STEGOLOCK_STORAGE_DISK=b2` is enough to switch from local to B2.

```php
// app/Services/Storage/StegoStorageService.php
// This is already correct — shown here for reference only
public function __construct()
{
    $this->disk = Storage::disk(config('stegolock.storage.disk'));
}
```

---

## Part 3 — Key Differences vs R2

If you previously followed the R2 guide, here is exactly what changes:

| | Cloudflare R2 | Backblaze B2 |
|---|---|---|
| `use_path_style_endpoint` | `true` (required) | `false` |
| Checksum headers fix | Not needed | ✅ Required (2025) |
| Endpoint format | `https://<ACCOUNT_ID>.r2.cloudflarestorage.com` | `https://s3.<region>.backblazeb2.com` |
| URL format | `https://<ACCOUNT_ID>.r2.cloudflarestorage.com/<bucket>` | `https://<bucket>.s3.<region>.backblazeb2.com` |
| Free storage | 10GB | 10GB |
| Egress fees | None | Free up to 3× stored data/month |
| Credit card required | Yes | No |

---

## Part 4 — Temporary Signed URLs (for serving files)

Since your bucket is **private**, you cannot serve carrier files or stego outputs
with a direct URL. Use temporary signed URLs. B2 supports these via the S3-Compatible API.

Your `StegoStorageService::temporaryUrl()` already handles this:

```php
public function temporaryUrl(string $path, int $minutesValid = 15): string
{
    return $this->disk->temporaryUrl($path, now()->addMinutes($minutesValid));
}
```

Use it in your controllers when returning file access to the user:

```php
// Carrier preview
$url = $this->storage->temporaryUrl($carrier->file_path, minutesValid: 15);

// Stego output segment download
$url = $this->storage->temporaryUrl($segment->s3_key, minutesValid: 30);
```

---

## Part 5 — Local Development Without B2

When developing locally, you don't want to hit B2 for every file operation.
Switch back to local storage with a single `.env` change:

```env
# .env.local or just change locally
STEGOLOCK_STORAGE_DISK=local
```

No code changes needed. This is why the disk abstraction in `StegoStorageService`
exists — local and B2 are interchangeable at the config level.

---

## Checklist

- [ ] Create Backblaze account (no credit card)
- [ ] Enable B2 Cloud Storage
- [ ] Create **private** bucket, note the endpoint URL and region
- [ ] Create application key (not master key) with **Allow List All Bucket Names** checked
- [ ] Copy Key ID and Application Key before closing the panel
- [ ] `composer require league/flysystem-aws-s3-v3`
- [ ] Add B2 credentials to `.env`
- [ ] Add `b2` disk to `config/filesystems.php` with checksum fix
- [ ] Set `STEGOLOCK_STORAGE_DISK=b2` in `.env`
- [ ] Run tinker connection test — all 3 operations pass
- [ ] Confirm `StegoStorageService` uses `config('stegolock.storage.disk')`
