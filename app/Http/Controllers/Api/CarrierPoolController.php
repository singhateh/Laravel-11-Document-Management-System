<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ValidateCarrierJob;
use App\Models\StegoCarrier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * CarrierPoolController
 * 
 * Manages the carrier pool — a personal collection of pre-validated carrier images
 * that users upload once and reuse across multiple encode operations.
 * 
 * Routes:
 *   GET    /api/stego/carriers      — List pool carriers with optional status filter
 *   POST   /api/stego/carriers      — Upload a carrier into the pool
 *   DELETE /api/stego/carriers/{id}  — Remove a carrier from the pool
 */
class CarrierPoolController extends Controller
{
    /**
     * Upload a carrier into the pool.
     * 
     * The carrier is stored immediately, then dispatched to a background job
     * for validation (PSNR + capacity measurement). The upload response is instant.
     * 
     * @param Request $request
     * @return JsonResponse 202 Accepted with carrier_id and validation_status
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'carrier' => ['required', 'file', 'mimes:png,bmp,jpeg,jpg', 'max:102400'],
            'name' => ['nullable', 'string', 'max:255'],
        ]);

        $user = Auth::user();
        $file = $request->file('carrier');

        // Enforce quota before storing
        $maxCarriers = config('stegolock.carrier_pool.max_carriers_per_user', 50);
        $maxSize = config('stegolock.carrier_pool.max_total_size_bytes', 500 * 1024 * 1024);

        $currentCount = StegoCarrier::where('uploaded_by', $user->id)->count();
        $currentSize = StegoCarrier::where('uploaded_by', $user->id)->sum('size');

        if ($currentCount >= $maxCarriers) {
            return response()->json([
                'message' => "Pool limit reached ({$maxCarriers} carriers). Remove unused carriers first.",
            ], 422);
        }

        if (($currentSize + $file->getSize()) > $maxSize) {
            $maxMb = round($maxSize / 1024 / 1024);
            return response()->json([
                'message' => "Pool storage limit of {$maxMb}MB would be exceeded.",
            ], 422);
        }

        // Store carrier file
        $path = Storage::putFile('stego/carriers/' . $user->id, $file);

        // Create carrier record with pending validation status
        $carrier = StegoCarrier::create([
            'name' => $request->input('name', $file->getClientOriginalName()),
            'file_path' => $path,
            'file_type' => $file->getClientOriginalExtension(),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'uploaded_by' => $user->id,
            'validation_status' => 'pending',
        ]);

        // Dispatch background validation job
        ValidateCarrierJob::dispatch($carrier->id);

        return response()->json([
            'carrier_id' => $carrier->id,
            'validation_status' => 'pending',
            'message' => 'Carrier queued for validation. Check status before encoding.',
        ], 202);
    }

    /**
     * List pool carriers with optional status filter.
     * 
     * @param Request $request
     * @return JsonResponse Paginated list of carriers
     */
    public function index(Request $request): JsonResponse
    {
        $carriers = StegoCarrier::where('uploaded_by', Auth::id())
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

    /**
     * Remove a carrier from the pool.
     * 
     * Carriers that are currently in use by an active encode operation cannot be removed.
     * 
     * @param int $id Carrier ID
     * @return JsonResponse 200 on success, 409 if carrier is in use
     */
    public function destroy(int $id): JsonResponse
    {
        $carrier = StegoCarrier::where('id', $id)
            ->where('uploaded_by', Auth::id())
            ->firstOrFail();

        if ($carrier->is_in_use) {
            return response()->json([
                'message' => 'Carrier is currently in use by an active stego document.',
            ], 409);
        }

        // Delete file from storage
        Storage::delete($carrier->file_path);

        // Delete carrier record
        $carrier->delete();

        return response()->json(['message' => 'Carrier removed from pool.']);
    }
}
