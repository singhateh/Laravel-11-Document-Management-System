<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\StegoCarrier>
 */
class StegoCarrierFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'carrier-' . $this->faker->unique()->uuid() . '.png',
            'file_path' => 'stego/carriers/' . $this->faker->uuid() . '.png',
            'file_type' => 'png',
            'mime_type' => 'image/png',
            'size' => $this->faker->numberBetween(10_000, 2_000_000),
            's3_key' => null,
            'psnr' => null,
            'uploaded_by' => User::factory(),
            'validation_status' => 'pending',
            'validation_error' => null,
            'capacity_bytes' => null,
            'is_in_use' => false,
            'validated_at' => null,
        ];
    }
}
