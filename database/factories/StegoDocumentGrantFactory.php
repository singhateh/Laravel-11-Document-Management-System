<?php

namespace Database\Factories;

use App\Models\StegoDocument;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\StegoDocumentGrant>
 */
class StegoDocumentGrantFactory extends Factory
{
    public function definition(): array
    {
        $owner = User::factory()->create();

        return [
            'stego_document_id' => StegoDocument::factory()->state(['user_id' => $owner->id]),
            'viewer_user_id'    => User::factory(),
            'granted_by'        => $owner->id,
        ];
    }
}
