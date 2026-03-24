<?php

namespace App\Services\Stego;

use App\Models\StegoCarrier;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * CarrierPoolSelector
 *
 * Selects the minimum set of valid carriers from a user's pool
 * that can collectively hold the required bytes.
 *
 * Uses greedy bin-packing: largest carriers first to minimize the number of carriers needed.
 * Implements lockForUpdate to prevent race conditions during concurrent encode requests.
 *
 * Supports system carrier fallback for new users with empty pools.
 */
class CarrierPoolSelector
{
    /**
     * Select carriers from the pool that can hold the required bytes.
     *
     * @param int $userId User ID
     * @param int $requiredBytes Total bytes needed
     * @param bool $allowSystemFallback Whether to fall back to system carriers if user pool is insufficient
     * @return Collection Selected carriers
     * @throws \RuntimeException If pool has insufficient capacity
     */
    public function select(int $userId, int $requiredBytes, bool $allowSystemFallback = true): Collection
    {
        // First, try to select from user's own pool
        $userCarriers = $this->queryValidCarriers($userId, $requiredBytes);
        $accumulated = $userCarriers->sum('capacity_bytes');

        // If user pool is sufficient, return it
        if ($accumulated >= $requiredBytes) {
            return $userCarriers;
        }

        // If system fallback is disabled, throw exception
        if (!$allowSystemFallback) {
            $shortfall = $requiredBytes - $accumulated;
            throw new \RuntimeException(
                "Pool has {$accumulated} bytes available but {$requiredBytes} bytes needed. " .
                "Upload approximately " . ceil($shortfall / 500000) . " more carrier image(s)."
            );
        }

        // Try to fill the gap with system carriers
        $shortfall = $requiredBytes - $accumulated;
        $systemCarriers = $this->queryValidCarriers($this->getSystemUserId(), $shortfall);

        // Check if system carriers can fill the gap
        $totalAvailable = $accumulated + $systemCarriers->sum('capacity_bytes');
        if ($totalAvailable < $requiredBytes) {
            throw new \RuntimeException(
                "Pool insufficient even with system carriers. " .
                "User pool: {$accumulated} bytes, System pool: {$systemCarriers->sum('capacity_bytes')} bytes, " .
                "Required: {$requiredBytes} bytes. Contact support."
            );
        }

        // Log that system carriers are being used
        Log::info('CarrierPoolSelector: Using system carriers to fill gap', [
            'user_id' => $userId,
            'user_pool_bytes' => $accumulated,
            'system_carriers_used' => $systemCarriers->count(),
            'system_carriers_bytes' => $systemCarriers->sum('capacity_bytes'),
            'required_bytes' => $requiredBytes,
        ]);

        // Merge user carriers with system carriers
        return $userCarriers->merge($systemCarriers)->values();
    }

    /**
     * Query valid carriers for a user that can collectively hold the required bytes.
     *
     * @param int $userId User ID
     * @param int $requiredBytes Total bytes needed
     * @return Collection Selected carriers
     */
    private function queryValidCarriers(int $userId, int $requiredBytes): Collection
    {
        // Lock rows to prevent two concurrent encodes selecting the same carriers
        $carriers = StegoCarrier::where('uploaded_by', $userId)
            ->where('validation_status', 'valid')
            ->where('is_in_use', false)
            ->whereNotNull('capacity_bytes')
            ->orderByDesc('capacity_bytes')
            ->lockForUpdate()
            ->get();

        $selected = collect();
        $accumulated = 0;

        foreach ($carriers as $carrier) {
            if ($accumulated >= $requiredBytes) {
                break;
            }
            $selected->push($carrier);
            $accumulated += $carrier->capacity_bytes;
        }

        return $selected;
    }

    /**
     * Get the system user ID (admin user who owns system carriers).
     *
     * @return int System user ID
     * @throws \RuntimeException If no admin user found
     */
    private function getSystemUserId(): int
    {
        $adminUser = User::where('role', 'admin')->first();

        if (!$adminUser) {
            throw new \RuntimeException('No admin user found. System carriers cannot be used.');
        }

        return $adminUser->id;
    }

    /**
     * Mark selected carriers as in use.
     * 
     * @param Collection $carriers Carriers to mark
     */
    public function markInUse(Collection $carriers): void
    {
        if ($carriers->isEmpty()) {
            return;
        }

        StegoCarrier::whereIn('id', $carriers->pluck('id'))
            ->update(['is_in_use' => true]);
    }

    /**
     * Release carriers back to the pool.
     * 
     * @param Collection $carriers Carriers to release
     */
    public function release(Collection $carriers): void
    {
        if ($carriers->isEmpty()) {
            return;
        }

        StegoCarrier::whereIn('id', $carriers->pluck('id'))
            ->update(['is_in_use' => false]);
    }

    /**
     * Get total available capacity for a user's pool.
     * 
     * @param int $userId User ID
     * @return int Total bytes available
     */
    public function getAvailableCapacity(int $userId): int
    {
        return StegoCarrier::where('uploaded_by', $userId)
            ->where('validation_status', 'valid')
            ->where('is_in_use', false)
            ->whereNotNull('capacity_bytes')
            ->sum('capacity_bytes');
    }

    /**
     * Check if pool has sufficient capacity for required bytes.
     * 
     * @param int $userId User ID
     * @param int $requiredBytes Required bytes
     * @return bool
     */
    public function hasSufficientCapacity(int $userId, int $requiredBytes): bool
    {
        return $this->getAvailableCapacity($userId) >= $requiredBytes;
    }
}
