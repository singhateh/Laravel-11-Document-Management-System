<?php

namespace Database\Seeders;

use App\Models\StegoCarrier;
use App\Models\User;
use App\Services\Stego\StegoService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * SystemCarrierSeeder
 * 
 * Seeds system default carriers that any user can borrow when their personal
 * pool is insufficient. These are pre-validated images owned by the application.
 * 
 * This unblocks new users who have an empty pool and cannot encode anything.
 */
class SystemCarrierSeeder extends Seeder
{
    public function run(): void
    {
        // Get the first admin user to own system carriers
        $adminUser = User::where('role', 'admin')->first();

        if (!$adminUser) {
            Log::warning('SystemCarrierSeeder: No admin user found. Skipping system carrier seeding.');
            return;
        }

        $systemUserId = $adminUser->id;

        // Define system carrier images
        // These should be high-quality PNG images with good capacity
        $carriers = [
            ['name' => 'system-carrier-1.png', 'path' => 'stego/system/carrier-1.png'],
            ['name' => 'system-carrier-2.png', 'path' => 'stego/system/carrier-2.png'],
            ['name' => 'system-carrier-3.png', 'path' => 'stego/system/carrier-3.png'],
            ['name' => 'system-carrier-4.png', 'path' => 'stego/system/carrier-4.png'],
            ['name' => 'system-carrier-5.png', 'path' => 'stego/system/carrier-5.png'],
        ];

        $stegoService = app(StegoService::class);

        foreach ($carriers as $carrierData) {
            $filePath = Storage::path($carrierData['path']);

            // Check if the system carrier file exists
            if (!file_exists($filePath)) {
                Log::warning("SystemCarrierSeeder: Carrier file not found: {$filePath}. Skipping.");
                continue;
            }

            // Check if carrier already exists in database
            $existingCarrier = StegoCarrier::where('file_path', $carrierData['path'])->first();
            if ($existingCarrier) {
                Log::info("SystemCarrierSeeder: Carrier already exists: {$carrierData['name']}. Skipping.");
                continue;
            }

            try {
                // Measure capacity using StegoService
                $capacity = $stegoService->capacity($filePath);

                // Create the system carrier record
                StegoCarrier::create([
                    'name'              => $carrierData['name'],
                    'file_path'         => $carrierData['path'],
                    'file_type'         => 'png',
                    'mime_type'         => 'image/png',
                    'size'              => Storage::size($carrierData['path']),
                    'uploaded_by'       => $systemUserId,
                    'validation_status' => 'valid',
                    'validated_at'      => now(),
                    'capacity_bytes'    => $capacity,
                    'is_in_use'         => false,
                ]);

                Log::info("SystemCarrierSeeder: Created system carrier: {$carrierData['name']} with capacity {$capacity} bytes.");

            } catch (\Throwable $e) {
                Log::error("SystemCarrierSeeder: Failed to create carrier {$carrierData['name']}: {$e->getMessage()}");
            }
        }

        Log::info('SystemCarrierSeeder: System carrier seeding completed.');
    }
}
