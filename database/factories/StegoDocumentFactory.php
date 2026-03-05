<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\StegoDocument>
 */
class StegoDocumentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'document_id'    => null,
            'user_id'        => User::factory(),
            'ciphertext'     => bin2hex(random_bytes(32)),
            'iv'             => bin2hex(random_bytes(12)),
            'auth_tag'       => bin2hex(random_bytes(16)),
            'hash_sha256'    => hash('sha256', $this->faker->sentence()),
            'dek_salt'       => bin2hex(random_bytes(16)),
            'dek_iterations' => 10000,
            's3_key'         => null,
            's3_url'         => null,
        ];
    }
}
