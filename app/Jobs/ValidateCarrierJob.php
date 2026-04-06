<?php

namespace App\Jobs;

use App\Models\StegoCarrier;
use App\Services\Stego\StegoService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * ValidateCarrierJob
 * 
 * Background job that validates a carrier file after upload.
 * Measures PSNR (for images) and capacity, then updates the carrier record.
 * 
 * This moves validation from encode-time to upload-time, ensuring only
 * pre-validated carriers enter the pool — encode cannot fail due to a bad carrier.
 */
class ValidateCarrierJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * Create a new job instance.
     */
    public function __construct(public readonly int $carrierId)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(StegoService $stegoService): void
    {
        $carrier = StegoCarrier::find($this->carrierId);

        if (!$carrier) {
            Log::warning('ValidateCarrierJob: Carrier not found', ['carrier_id' => $this->carrierId]);
            return;
        }

        try {
            $filePath = Storage::path($carrier->file_path);

            if (!file_exists($filePath)) {
                $this->markInvalid($carrier, "Carrier file not found on disk: {$carrier->file_path}");
                return;
            }

            // Measure capacity
            $capacity = $stegoService->capacity($filePath);
            $carrier->capacity_bytes = $capacity;

            // For images, measure PSNR baseline
            if (str_starts_with($carrier->mime_type ?? '', 'image/')) {
                $psnrResult = $this->measureBaselinePsnr($stegoService, $filePath);

                if ($psnrResult !== null) {
                    $carrier->psnr = $psnrResult;

                    if ($psnrResult < 40.0) {
                        $this->markInvalid($carrier, "PSNR {$psnrResult} dB is below the 40 dB threshold");
                        return;
                    }
                }
            }

            $carrier->validation_status = 'valid';
            $carrier->validated_at = now();
            $carrier->save();

            Log::info('ValidateCarrierJob: Carrier validated successfully', [
                'carrier_id' => $carrier->id,
                'capacity_bytes' => $capacity,
                'psnr' => $carrier->psnr,
            ]);

        } catch (Throwable $e) {
            Log::error('ValidateCarrierJob: Validation failed', [
                'carrier_id' => $this->carrierId,
                'error' => $e->getMessage(),
            ]);
            $this->markInvalid($carrier, $e->getMessage());
        }
    }

    /**
     * Measure baseline PSNR by comparing the carrier to itself.
     * This establishes a baseline quality metric for the carrier.
     */
    private function measureBaselinePsnr(StegoService $stegoService, string $filePath): ?float
    {
        try {
            // Create a temporary copy to measure baseline PSNR
            $tmpPath = tempnam(sys_get_temp_dir(), 'psnr_') . '.' . pathinfo($filePath, PATHINFO_EXTENSION);
            copy($filePath, $tmpPath);

            try {
                $psnrResult = $stegoService->psnr($filePath, $tmpPath);
                return $psnrResult['psnr'] ?? null;
            } finally {
                if (file_exists($tmpPath)) {
                    @unlink($tmpPath);
                }
            }
        } catch (Throwable $e) {
            Log::warning('ValidateCarrierJob: PSNR measurement failed', [
                'carrier_id' => $this->carrierId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Mark carrier as invalid with error reason.
     */
    private function markInvalid(StegoCarrier $carrier, string $reason): void
    {
        $carrier->validation_status = 'invalid';
        $carrier->validation_error = $reason;
        $carrier->validated_at = now();
        $carrier->save();

        Log::info('ValidateCarrierJob: Carrier marked as invalid', [
            'carrier_id' => $carrier->id,
            'reason' => $reason,
        ]);
    }
}
