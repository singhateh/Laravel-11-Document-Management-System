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
            'document_id'       => null,
            'user_id'           => User::factory(),
            'ciphertext'        => bin2hex(random_bytes(32)),
            // Renamed in 2026_03_05_000005_rename_stego_documents_enc_columns:
            'stego_iv'          => bin2hex(random_bytes(12)),
            'stego_auth_tag'    => bin2hex(random_bytes(16)),
            'stego_hash_sha256' => hash('sha256', $this->faker->sentence()),
            'stego_dek_salt'    => bin2hex(random_bytes(16)),
            'stego_dek_iter'    => 10000,
            // s3_url was dropped in 2026_03_05_000004_drop_s3_url_from_stego_tables:
            's3_key'            => null,
        ];
    }
}
