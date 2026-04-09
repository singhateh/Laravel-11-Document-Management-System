<?php

namespace Tests\Feature\Api;

use App\Models\StegoCarrier;
use App\Models\Document;
use App\Models\User;
use App\Jobs\ValidateCarrierJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * CarrierPoolTest
 *
 * Feature tests for the Carrier Pool API endpoints.
 *
 * Tests verify:
 *  - Carrier upload to pool
 *  - Carrier listing with filters
 *  - Carrier deletion
 *  - Preflight check for encoding capacity
 *  - Background validation job dispatch
 *  - Quota enforcement
 */
class CarrierPoolTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'username' => 'pooltester',
            'role'     => 'user',
            'mkd_salt' => str_repeat('ab', 16),
        ]);
    }

    private function createDocumentWithBytes(int $bytes): Document
    {
        $relativePath = 'documents/preflight-' . uniqid('', true) . '.txt';
        $absolutePath = public_path($relativePath);

        $directory = dirname($absolutePath);
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        file_put_contents($absolutePath, str_repeat('A', $bytes));

        return Document::factory()->create([
            'owner_id' => $this->user->id,
            'name' => 'preflight-' . $bytes . '.txt',
            'original_name' => 'preflight-' . $bytes . '.txt',
            'file_path' => $relativePath,
            'size' => $bytes,
            'extension' => 'txt',
            'is_encrypted' => false,
        ]);
    }

    private function expectedDecodedCiphertextBytes(int $bytes): int
    {
        $compressed = gzcompress(str_repeat('A', $bytes), 6);
        $this->assertIsString($compressed);

        return strlen($compressed);
    }

    // -------------------------------------------------------------------------
    // Authentication guard
    // -------------------------------------------------------------------------

    #[Test]
    public function carriers_index_requires_authentication(): void
    {
        $this->getJson('/api/stego/carriers')->assertStatus(401);
    }

    #[Test]
    public function carriers_store_requires_authentication(): void
    {
        $this->postJson('/api/stego/carriers')->assertStatus(401);
    }

    #[Test]
    public function carriers_destroy_requires_authentication(): void
    {
        $this->deleteJson('/api/stego/carriers/1')->assertStatus(401);
    }

    #[Test]
    public function preflight_requires_authentication(): void
    {
        $this->postJson('/api/stego/preflight')->assertStatus(401);
    }

    // -------------------------------------------------------------------------
    // Carrier upload
    // -------------------------------------------------------------------------

    #[Test]
    public function can_upload_carrier_to_pool(): void
    {
        Queue::fake();

        Storage::fake('local');

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/stego/carriers', [
                'carrier' => UploadedFile::fake()->image('test-carrier.png', 800, 600),
                'name' => 'Test Carrier',
            ]);

        $response->assertStatus(202)
            ->assertJsonStructure([
                'message',
                'carrier_id',
                'validation_status',
            ])
            ->assertJsonPath('validation_status', 'pending');

        // Verify carrier was created in database
        $this->assertDatabaseHas('stego_carriers', [
            'name' => 'Test Carrier',
            'uploaded_by' => $this->user->id,
            'validation_status' => 'pending',
        ]);

        // Verify validation job was dispatched
        Queue::assertPushed(ValidateCarrierJob::class, function ($job) {
            return $job->carrierId === StegoCarrier::first()->id;
        });
    }

    #[Test]
    public function carrier_upload_validates_file_type(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/stego/carriers', [
                'carrier' => UploadedFile::fake()->create('document.pdf', 100),
            ]);

        $response->assertStatus(422);
    }

    #[Test]
    public function carrier_upload_validates_file_size(): void
    {
        // Create a file larger than 100MB
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/stego/carriers', [
                'carrier' => UploadedFile::fake()->create('large-image.png', 102401), // 100MB + 1KB
            ]);

        $response->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // Carrier listing
    // -------------------------------------------------------------------------

    #[Test]
    public function can_list_user_carriers(): void
    {
        // Create carriers for this user
        StegoCarrier::factory()->count(3)->create([
            'uploaded_by' => $this->user->id,
            'validation_status' => 'valid',
        ]);

        // Create carriers for another user (should not appear)
        $otherUser = User::factory()->create(['username' => 'other', 'role' => 'user']);
        StegoCarrier::factory()->count(2)->create([
            'uploaded_by' => $otherUser->id,
            'validation_status' => 'valid',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/stego/carriers');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    #[Test]
    public function can_filter_carriers_by_validation_status(): void
    {
        StegoCarrier::factory()->count(2)->create([
            'uploaded_by' => $this->user->id,
            'validation_status' => 'valid',
        ]);

        StegoCarrier::factory()->count(1)->create([
            'uploaded_by' => $this->user->id,
            'validation_status' => 'pending',
        ]);

        StegoCarrier::factory()->count(1)->create([
            'uploaded_by' => $this->user->id,
            'validation_status' => 'invalid',
        ]);

        // Filter for valid carriers
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/stego/carriers?status=valid');

        $response->assertOk()
            ->assertJsonCount(2, 'data');

        // Filter for pending carriers
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/stego/carriers?status=pending');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    // -------------------------------------------------------------------------
    // Carrier deletion
    // -------------------------------------------------------------------------

    #[Test]
    public function can_delete_carrier_from_pool(): void
    {
        Storage::fake('local');

        $carrier = StegoCarrier::factory()->create([
            'uploaded_by' => $this->user->id,
            'file_path' => 'stego/carriers/test.png',
            'is_in_use' => false,
        ]);

        // Create the file in storage
        Storage::disk('local')->put('stego/carriers/test.png', 'fake content');

        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/stego/carriers/{$carrier->id}");

        $response->assertOk()
            ->assertJsonPath('message', 'Carrier removed from pool.');

        // Verify carrier was deleted from database
        $this->assertDatabaseMissing('stego_carriers', [
            'id' => $carrier->id,
        ]);

        // Verify file was deleted from storage
        $this->assertFalse(Storage::disk('local')->exists('stego/carriers/test.png'));
    }

    #[Test]
    public function cannot_delete_carrier_in_use(): void
    {
        $carrier = StegoCarrier::factory()->create([
            'uploaded_by' => $this->user->id,
            'is_in_use' => true,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/stego/carriers/{$carrier->id}");

        $response->assertStatus(409)
            ->assertJsonPath('message', 'Carrier is currently in use by an active stego document.');

        // Verify carrier still exists
        $this->assertDatabaseHas('stego_carriers', [
            'id' => $carrier->id,
        ]);
    }

    #[Test]
    public function cannot_delete_another_users_carrier(): void
    {
        $otherUser = User::factory()->create(['username' => 'other', 'role' => 'user']);

        $carrier = StegoCarrier::factory()->create([
            'uploaded_by' => $otherUser->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/stego/carriers/{$carrier->id}");

        $response->assertStatus(404);
    }

    // -------------------------------------------------------------------------
    // Preflight check
    // -------------------------------------------------------------------------

    #[Test]
    public function preflight_returns_sufficient_capacity(): void
    {
        $documentBytes = 2000000;
        $document = $this->createDocumentWithBytes($documentBytes);
        $requiredBytes = $this->expectedDecodedCiphertextBytes($documentBytes);

        // Create valid carriers with known capacities
        StegoCarrier::factory()->count(3)->create([
            'uploaded_by' => $this->user->id,
            'validation_status' => 'valid',
            'capacity_bytes' => 1000000, // 1MB each
            'is_in_use' => false,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/stego/preflight', [
                'document_id' => $document->id,
            ]);

        $response->assertOk()
            ->assertJsonPath('can_encode', true)
            ->assertJsonPath('available_bytes', 3000000)
            ->assertJsonPath('required_bytes', $requiredBytes)
            ->assertJsonPath('required_bytes_basis', 'decoded_ciphertext')
            ->assertJsonPath('valid_carriers', 3);
    }

    #[Test]
    public function preflight_returns_insufficient_capacity(): void
    {
        $documentBytes = 2000000;
        $document = $this->createDocumentWithBytes($documentBytes);
        $requiredBytes = $this->expectedDecodedCiphertextBytes($documentBytes);
        $availableBytes = max(1, $requiredBytes - 1);
        $firstCarrierBytes = max(1, $availableBytes - 1);
        $secondCarrierBytes = 1;

        // Create valid carriers with limited capacity
        StegoCarrier::factory()->create([
            'uploaded_by' => $this->user->id,
            'validation_status' => 'valid',
            'capacity_bytes' => $firstCarrierBytes,
            'is_in_use' => false,
        ]);

        StegoCarrier::factory()->create([
            'uploaded_by' => $this->user->id,
            'validation_status' => 'valid',
            'capacity_bytes' => $secondCarrierBytes,
            'is_in_use' => false,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/stego/preflight', [
                'document_id' => $document->id,
            ]);

        $response->assertOk()
            ->assertJsonPath('can_encode', false)
            ->assertJsonPath('available_bytes', $availableBytes)
            ->assertJsonPath('required_bytes', $requiredBytes)
            ->assertJsonPath('required_bytes_basis', 'decoded_ciphertext')
            ->assertJsonPath('valid_carriers', 2);
    }

    #[Test]
    public function preflight_ignores_invalid_carriers(): void
    {
        $documentBytes = 2000000;
        $document = $this->createDocumentWithBytes($documentBytes);
        $requiredBytes = $this->expectedDecodedCiphertextBytes($documentBytes);

        // Create mix of valid and invalid carriers
        StegoCarrier::factory()->count(2)->create([
            'uploaded_by' => $this->user->id,
            'validation_status' => 'valid',
            'capacity_bytes' => 1000000,
            'is_in_use' => false,
        ]);

        StegoCarrier::factory()->count(2)->create([
            'uploaded_by' => $this->user->id,
            'validation_status' => 'invalid',
            'capacity_bytes' => 1000000,
            'is_in_use' => false,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/stego/preflight', [
                'document_id' => $document->id,
            ]);

        $response->assertOk()
            ->assertJsonPath('can_encode', true)
            ->assertJsonPath('available_bytes', 2000000)
            ->assertJsonPath('required_bytes', $requiredBytes)
            ->assertJsonPath('required_bytes_basis', 'decoded_ciphertext')
            ->assertJsonPath('valid_carriers', 2);
    }

    #[Test]
    public function preflight_ignores_carriers_in_use(): void
    {
        $documentBytes = 3000000;
        $document = $this->createDocumentWithBytes($documentBytes);
        $requiredBytes = $this->expectedDecodedCiphertextBytes($documentBytes);
        $availableBytes = max(1, $requiredBytes - 1);
        $firstCarrierBytes = max(1, $availableBytes - 1);
        $secondCarrierBytes = 1;

        // Create carriers, some in use
        StegoCarrier::factory()->create([
            'uploaded_by' => $this->user->id,
            'validation_status' => 'valid',
            'capacity_bytes' => $firstCarrierBytes,
            'is_in_use' => false,
        ]);

        StegoCarrier::factory()->create([
            'uploaded_by' => $this->user->id,
            'validation_status' => 'valid',
            'capacity_bytes' => $secondCarrierBytes,
            'is_in_use' => false,
        ]);

        StegoCarrier::factory()->count(2)->create([
            'uploaded_by' => $this->user->id,
            'validation_status' => 'valid',
            'capacity_bytes' => max($requiredBytes, 1),
            'is_in_use' => true,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/stego/preflight', [
                'document_id' => $document->id,
            ]);

        $response->assertOk()
            ->assertJsonPath('can_encode', false)
            ->assertJsonPath('available_bytes', $availableBytes)
            ->assertJsonPath('required_bytes', $requiredBytes)
            ->assertJsonPath('required_bytes_basis', 'decoded_ciphertext')
            ->assertJsonPath('valid_carriers', 2);
    }

    // -------------------------------------------------------------------------
    // Quota enforcement
    // -------------------------------------------------------------------------

    #[Test]
    public function cannot_exceed_max_carriers_per_user(): void
    {
        // Create max carriers (50)
        StegoCarrier::factory()->count(50)->create([
            'uploaded_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/stego/carriers', [
                'carrier' => UploadedFile::fake()->image('test-carrier.png', 800, 600),
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Pool limit reached (50 carriers). Remove unused carriers first.');
    }

    #[Test]
    public function cannot_exceed_max_total_size(): void
    {
        // Create carriers totaling 500MB
        StegoCarrier::factory()->count(5)->create([
            'uploaded_by' => $this->user->id,
            'size' => 100 * 1024 * 1024, // 100MB each
        ]);

        // Try to upload another 100MB carrier
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/stego/carriers', [
                'carrier' => UploadedFile::fake()->create('large.png', 102400), // 100MB
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Pool storage limit of 500MB would be exceeded.');
    }
}
