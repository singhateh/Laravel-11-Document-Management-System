<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Document>
 */
class DocumentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name'          => $this->faker->words(3, true) . '.txt',
            'original_name' => $this->faker->words(3, true) . '.txt',
            'file_path'     => 'documents/' . $this->faker->uuid() . '.txt',
            'size'          => $this->faker->numberBetween(1024, 1_048_576),
            'extension'     => 'txt',
            'folder_id'     => null,
            'visibility'    => true,
            'owner_id'      => User::factory(),
            'document_date' => now(),
            'is_encrypted'  => false,
        ];
    }
}
