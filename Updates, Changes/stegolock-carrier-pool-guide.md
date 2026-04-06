# StegoLock — Carrier Pool Implementation Guide

> **Problem:** Users must upload N carrier images every time they encode a document.
> If a document requires 25 segments, they upload 25 images — and if any fail PSNR validation,
> they start over. This makes StegoLock impractical for large documents.
>
> **Solution:** Three layered fixes applied in order of priority.

---

## Overview of the Three Fixes

| # | Fix | What it solves | Priority |
|---|-----|---------------|----------|
| 1 | Carrier Pool | Eliminates per-operation upload friction | Immediate |
| 2 | Bin-Packing Segmentation | Reduces how many carriers are needed | Short-term |
| 3 | System Default Carriers | Unblocks new users with empty pools | Long-term |

---

## Fix 1 — Carrier Pool

### What it does

Users upload images **once** into a personal pool. The system validates each image at upload time (PSNR, capacity). When encoding, the system automatically picks images from the pool — the user uploads nothing per operation.

### Step 1 — Add pool management columns to `stego_carriers`

```php
// database/migrations/xxxx_add_pool_columns_to_stego_carriers.php
Schema::table('stego_carriers', function (Blueprint $table) {
    $table->enum('validation_status', ['pending', 'valid', 'invalid'])
          ->default('pending')
          ->after('psnr');
    $table->string('validation_error')->nullable()->after('validation_status');
    $table->unsignedBigInteger('capacity_bytes')->nullable()->after('validation_error');
    $table->boolean('is_in_use')->default(false)->after('capacity_bytes');
    $table->timestamp('validated_at')->nullable()->after('is_in_use');

    $table->index(['uploaded_by', 'validation_status', 'is_in_use']);
});
```

> **Why:** The pool needs to track whether each carrier is validated, how much data it can hold,
> and whether it is currently locked by an active encode job.

---

### Step 2 — Create the background validation job

When a carrier is uploaded, do not validate it inline. Dispatch a job so the upload response is instant.

```php
// app/Jobs/ValidateCarrierJob.php
class ValidateCarrierJob implements ShouldQueue
{
    use Dispatchable, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public readonly int $carrierId) {}

    public function handle(PsnrService $psnrService, CapacityService $capacityService): void
    {
        $carrier = StegCarrier::findOrFail($this->carrierId);

        try {
            $filePath = Storage::path($carrier->file_path);

            // Images only — measure actual PSNR
            if (str_starts_with($carrier->mime_type, 'image/')) {
                $psnr = $psnrService->measureBaseline($filePath);

                if ($psnr < 40.0) {
                    $this->markInvalid($carrier, "PSNR {$psnr} dB is below the 40 dB threshold");
                    return;
                }

                $carrier->psnr = $psnr;
            }

            $carrier->capacity_bytes    = $capacityService->measure($filePath, $carrier->mime_type);
            $carrier->validation_status = 'valid';
            $carrier->validated_at      = now();
            $carrier->save();

        } catch (Throwable $e) {
            $this->markInvalid($carrier, $e->getMessage());
        }
    }

    private function markInvalid(StegCarrier $carrier, string $reason): void
    {
        $carrier->validation_status = 'invalid';
        $carrier->validation_error  = $reason;
        $carrier->validated_at      = now();
        $carrier->save();
    }
}
```

> **Why PSNR at upload time, not encode time:** If validation happens during encoding and a carrier
> fails, the whole encode fails. Moving it to upload time means only pre-validated carriers ever
> enter the pool — encode cannot fail due to a bad carrier.

---

### Step 3 — Create the pool controller

```php
// app/Http/Controllers/Stego/CarrierPoolController.php
class CarrierPoolController extends Controller
{
    // Upload a carrier into the pool
    public function store(StoreCarrierRequest $request): JsonResponse
    {
        $file = $request->file('carrier');

        // Enforce quota before storing
        $currentCount = StegCarrier::where('uploaded_by', auth()->id())->count();
        $currentSize  = StegCarrier::where('uploaded_by', auth()->id())->sum('size');
        $maxCarriers  = config('stegolock.carrier_pool.max_carriers_per_user');
        $maxSize      = config('stegolock.carrier_pool.max_total_size_bytes');

        if ($currentCount >= $maxCarriers) {
            return response()->json([
                'message' => "Pool limit reached ({$maxCarriers} carriers). Remove unused carriers first.",
            ], 422);
        }

        if (($currentSize + $file->getSize()) > $maxSize) {
            $maxMb = $maxSize / 1024 / 1024;
            return response()->json([
                'message' => "Pool storage limit of {$maxMb}MB would be exceeded.",
            ], 422);
        }

        $path    = Storage::putFile('stego/carriers/' . auth()->id(), $file);
        $carrier = StegCarrier::create([
            'name'              => $request->input('name', $file->getClientOriginalName()),
            'file_path'         => $path,
            'file_type'         => $file->extension(),
            'mime_type'         => $file->getMimeType(),
            'size'              => $file->getSize(),
            'uploaded_by'       => auth()->id(),
            'validation_status' => 'pending',
        ]);

        ValidateCarrierJob::dispatch($carrier->id);

        return response()->json([
            'carrier_id'        => $carrier->id,
            'validation_status' => 'pending',
            'message'           => 'Carrier queued for validation. Check status before encoding.',
        ], 202);
    }

    // List pool carriers with optional status filter
    public function index(Request $request): JsonResponse
    {
        $carriers = StegCarrier::where('uploaded_by', auth()->id())
            ->select([
                'id', 'name', 'file_type', 'mime_type', 'size',
                'psnr', 'capacity_bytes', 'validation_status',
                'validation_error', 'is_in_use', 'validated_at', 'created_at',
            ])
            ->when($request->query('status'), fn($q, $s) => $q->where('validation_status', $s))
            ->latest()
            ->paginate(20);

        return response()->json($carriers);
    }

    // Remove a carrier from the pool
    public function destroy(int $id): JsonResponse
    {
        $carrier = StegCarrier::where('id', $id)
            ->where('uploaded_by', auth()->id())
            ->firstOrFail();

        if ($carrier->is_in_use) {
            return response()->json([
                'message' => 'Carrier is currently in use by an active stego document.',
            ], 409);
        }

        Storage::delete($carrier->file_path);
        $carrier->delete();

        return response()->json(['message' => 'Carrier removed from pool.']);
    }
}
```

---

### Step 4 — Create the carrier selector service

This is the core of Fix 1. It replaces the user's manual carrier upload during encode.

```php
// app/Services/Stego/CarrierPoolSelector.php
class CarrierPoolSelector
{
    /**
     * Select the minimum set of valid carriers from the pool
     * that can collectively hold the required bytes.
     * Carriers are sorted by capacity descending (greedy bin-packing).
     */
    public function select(int $userId, int $requiredBytes): Collection
    {
        // lockForUpdate prevents two concurrent encodes selecting the same carriers
        $carriers = StegCarrier::where('uploaded_by', $userId)
            ->where('validation_status', 'valid')
            ->where('is_in_use', false)
            ->whereNotNull('capacity_bytes')
            ->orderByDesc('capacity_bytes')
            ->lockForUpdate()
            ->get();

        $selected    = collect();
        $accumulated = 0;

        foreach ($carriers as $carrier) {
            if ($accumulated >= $requiredBytes) break;
            $selected->push($carrier);
            $accumulated += $carrier->capacity_bytes;
        }

        if ($accumulated < $requiredBytes) {
            $shortfall = $requiredBytes - $accumulated;
            throw new InsufficientCarrierPoolException(
                "Pool has {$accumulated} bytes available but {$requiredBytes} bytes needed. " .
                "Upload approximately " . ceil($shortfall / 500000) . " more carrier image(s)."
            );
        }

        return $selected;
    }

    public function markInUse(Collection $carriers): void
    {
        StegCarrier::whereIn('id', $carriers->pluck('id'))->update(['is_in_use' => true]);
    }

    public function release(Collection $carriers): void
    {
        StegCarrier::whereIn('id', $carriers->pluck('id'))->update(['is_in_use' => false]);
    }
}
```

> **Why `lockForUpdate`:** Without it, two simultaneous encode requests can both select
> the same carriers. The lock ensures only one transaction claims them at a time.

---

### Step 5 — Update `StegoDocumentService::encode()` to use the pool

Remove the `$carriers` parameter. The pool provides them automatically.

```php
// app/Services/Stego/StegoDocumentService.php
public function encode(int $userId, int $documentId): StegDocument
{
    return DB::transaction(function () use ($userId, $documentId) {

        $document  = Document::findOrFail($documentId);
        $plaintext = $this->storage->readPlaintext($document);

        // 1. Encrypt
        $encrypted       = $this->crypto->encrypt($plaintext);
        $ciphertextBytes = strlen(base64_decode($encrypted['ciphertext']));

        // 2. Select carriers from pool — throws InsufficientCarrierPoolException if pool is short
        $carriers = $this->poolSelector->select($userId, $ciphertextBytes);
        $this->poolSelector->markInUse($carriers);

        // 3. Create stego document record
        $stegoDoc = StegDocument::create([
            'document_id'       => $documentId,
            'user_id'           => $userId,
            'status'            => 'pending',
            'ciphertext'        => $encrypted['ciphertext'],
            'stego_iv'          => $encrypted['iv'],
            'stego_auth_tag'    => $encrypted['auth_tag'],
            'stego_hash_sha256' => $encrypted['hash'],
            'stego_dek_salt'    => $encrypted['dek_salt'],
            'stego_dek_iter'    => $encrypted['dek_iter'],
        ]);

        // 4. Segment and embed
        try {
            $segments = $this->segmentation->split(
                $encrypted['ciphertext'],
                $carriers->pluck('capacity_bytes')->all()
            );

            foreach ($segments as $index => $chunk) {
                $carrier    = $carriers[$index];
                $outputPath = $this->embedder->embed($carrier->file_path, $chunk, $carrier->mime_type);

                StegSegment::create([
                    'stego_document_id' => $stegoDoc->id,
                    'stego_carrier_id'  => $carrier->id,
                    'segment_index'     => $index,
                    'encrypted_chunk'   => $chunk,
                    'chunk_hash'        => hash('sha256', $chunk),
                    's3_key'            => $outputPath,
                ]);
            }

            $stegoDoc->update(['status' => 'ready']);

        } catch (Throwable $e) {
            // Release carriers back to pool so they are not permanently locked
            $this->poolSelector->release($carriers);
            $stegoDoc->update(['status' => 'failed', 'failed_reason' => $e->getMessage()]);
            throw $e;
        }

        return $stegoDoc;
    });
}
```

> **Important:** Carriers are released (`is_in_use = false`) only when the stego document
> is decoded or deleted — not immediately after encode. This preserves the carrier-segment
> link needed for future decoding.

---

### Step 6 — Add the preflight check endpoint

Let users verify their pool is sufficient before committing to an encode.

```php
// app/Http/Controllers/Stego/StegoDocumentController.php
public function preflight(Request $request): JsonResponse
{
    $request->validate(['document_id' => ['required', 'integer', 'exists:documents,id']]);

    $document      = Document::findOrFail($request->document_id);
    $estimatedBytes = $this->estimateCiphertextSize($document);

    $poolBytes = StegCarrier::where('uploaded_by', auth()->id())
        ->where('validation_status', 'valid')
        ->where('is_in_use', false)
        ->sum('capacity_bytes');

    $sufficient = $poolBytes >= $estimatedBytes;

    return response()->json([
        'estimated_bytes_required' => $estimatedBytes,
        'pool_available_bytes'     => $poolBytes,
        'sufficient'               => $sufficient,
        'shortfall_bytes'          => $sufficient ? 0 : ($estimatedBytes - $poolBytes),
        'message'                  => $sufficient
            ? 'Pool has sufficient capacity. Ready to encode.'
            : 'Insufficient pool capacity. Upload more carriers.',
    ]);
}
```

> **Note:** The preflight does not estimate PSNR — that was already measured at upload time
> and stored in the pool. The preflight only checks capacity.

---

### Step 7 — Add quota config and routes

```php
// config/stegolock.php
return [
    'carrier_pool' => [
        'max_carriers_per_user'  => 50,
        'max_total_size_bytes'   => 500 * 1024 * 1024, // 500MB
    ],
];
```

```php
// routes/api.php
Route::middleware('auth:sanctum')->prefix('stego')->group(function () {
    Route::get   ('carriers',              [CarrierPoolController::class, 'index']);
    Route::post  ('carriers',              [CarrierPoolController::class, 'store']);
    Route::delete('carriers/{id}',         [CarrierPoolController::class, 'destroy']);
    Route::post  ('documents/preflight',   [StegoDocumentController::class, 'preflight']);
    Route::post  ('documents/encode',      [StegoDocumentController::class, 'encode']);
});
```

---

## Fix 2 — Bin-Packing Segmentation

### What it does

Instead of splitting the ciphertext into fixed equal chunks (one per carrier), the system fills the largest carriers first. A high-resolution image that can hold 5MB might absorb what 5 smaller images would — reducing the number of carriers needed per encode.

### Step 1 — Update `SegmentationService::split()` to bin-pack

```php
// app/Services/Stego/SegmentationService.php
public function split(string $base64Ciphertext, array $capacityBytes): array
{
    $rawData    = base64_decode($base64Ciphertext, true);
    $dataLength = strlen($rawData);

    // Sort capacities descending so we fill largest carriers first
    arsort($capacityBytes);

    $segments = [];
    $offset   = 0;

    foreach ($capacityBytes as $carrierIndex => $capacity) {
        if ($offset >= $dataLength) break;

        // Take as much as this carrier can hold
        $chunkSize = min($capacity, $dataLength - $offset);
        $chunk     = substr($rawData, $offset, $chunkSize);

        $segments[$carrierIndex] = base64_encode($chunk);
        $offset += $chunkSize;
    }

    if ($offset < $dataLength) {
        throw new RuntimeException(
            "Combined carrier capacity is insufficient for the ciphertext size."
        );
    }

    // Re-index segments sequentially for consistent stego_segments.segment_index
    return array_values($segments);
}
```

> **Security note:** Bin-packing does not weaken segmentation security as long as no single
> carrier holds the complete ciphertext. If your document is small enough to fit in one carrier,
> enforce a minimum segment count policy in `recommendedSegmentCount()`.

---

## Fix 3 — System Default Carriers

### What it does

New users have an empty pool and cannot encode anything. System carriers are a built-in fallback set of pre-validated images owned by the application that any user can borrow when their pool is insufficient.

### Step 1 — Seed system carriers

```php
// database/seeders/SystemCarrierSeeder.php
class SystemCarrierSeeder extends Seeder
{
    public function run(): void
    {
        $systemUserId = User::where('role', 'admin')->first()->id;

        $carriers = [
            ['name' => 'system-carrier-1.png', 'path' => 'stego/system/carrier-1.png'],
            ['name' => 'system-carrier-2.png', 'path' => 'stego/system/carrier-2.png'],
            // Add enough to cover typical document sizes
        ];

        foreach ($carriers as $c) {
            StegCarrier::firstOrCreate(['file_path' => $c['path']], [
                'name'              => $c['name'],
                'file_type'         => 'png',
                'mime_type'         => 'image/png',
                'size'              => Storage::size($c['path']),
                'uploaded_by'       => $systemUserId,
                'validation_status' => 'valid',
                'validated_at'      => now(),
                'capacity_bytes'    => app(CapacityService::class)
                                        ->measure(Storage::path($c['path']), 'image/png'),
                'is_in_use'         => false,
            ]);
        }
    }
}
```

### Step 2 — Update `CarrierPoolSelector` to fall back to system carriers

```php
public function select(int $userId, int $requiredBytes, bool $allowSystemFallback = true): Collection
{
    $userCarriers = $this->queryValidCarriers($userId, $requiredBytes);

    $accumulated = $userCarriers->sum('capacity_bytes');

    if ($accumulated >= $requiredBytes) {
        return $userCarriers; // User pool is sufficient — no fallback needed
    }

    if (!$allowSystemFallback) {
        throw new InsufficientCarrierPoolException("Pool insufficient. Upload more carriers.");
    }

    // Fill the gap with system carriers
    $shortfall       = $requiredBytes - $accumulated;
    $systemCarriers  = $this->queryValidCarriers(
        $this->getSystemUserId(),
        $shortfall
    );

    if (($accumulated + $systemCarriers->sum('capacity_bytes')) < $requiredBytes) {
        throw new InsufficientCarrierPoolException(
            "Pool insufficient even with system carriers. Contact support."
        );
    }

    // Notify the user that system carriers were used
    // (implement via event/notification as appropriate)
    event(new SystemCarriersUsed($userId, $systemCarriers->count()));

    return $userCarriers->merge($systemCarriers);
}

private function queryValidCarriers(int $userId, int $requiredBytes): Collection
{
    return StegCarrier::where('uploaded_by', $userId)
        ->where('validation_status', 'valid')
        ->where('is_in_use', false)
        ->whereNotNull('capacity_bytes')
        ->orderByDesc('capacity_bytes')
        ->lockForUpdate()
        ->get();
}
```

---

## Complete Flow After All Three Fixes

```
[One-time setup]
User uploads images → PSNR + capacity validated in background → stored in pool as valid

[Every encode]
User selects document
        ↓
  POST /stego/documents/preflight
  → Is pool sufficient?
        ↓ Yes                          ↓ No
        ↓                    Use system carriers to fill gap
        ↓                              ↓
  POST /stego/documents/encode
  → Selector picks minimum images (bin-packing)
  → Locks selected carriers (is_in_use = true)
  → Splits ciphertext into fewest possible segments
  → Embeds each segment
  → Marks stego document as ready
        ↓
  User downloads stego document
        ↓
  Carriers released back to pool (is_in_use = false)
```

---

## What Each Fix Contributes

| Scenario | Without fixes | With Fix 1 only | With Fix 1+2 | With Fix 1+2+3 |
|---|---|---|---|---|
| Returning user, large document | Upload 25 images every time | 0 uploads, pool auto-selects | Pool needs fewer carriers | Same |
| Returning user, pool too small | Upload more, retry encode | Clear error, top up pool | Less likely to hit this | System fills gap |
| Brand new user | Hard block | Hard block | Hard block | System carriers unblock them |

---

## Checklist

- [ ] Run migration for `stego_carriers` pool columns
- [ ] Create and register `ValidateCarrierJob`
- [ ] Create `CarrierPoolController` with store / index / destroy
- [ ] Create `CarrierPoolSelector` service
- [ ] Update `StegoDocumentService::encode()` — remove carrier parameter, wire pool selector
- [ ] Add preflight endpoint
- [ ] Update `SegmentationService::split()` for bin-packing
- [ ] Seed system carriers
- [ ] Update `CarrierPoolSelector` with system carrier fallback
- [ ] Add quota config to `stegolock.php`
- [ ] Register routes
- [ ] Release carriers on stego document delete/decode
