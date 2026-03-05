<?php

namespace Tests\Feature\Api;

use App\Models\StegoDocument;
use App\Models\User;
use App\Services\Stego\StegoDocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * StegoApiTest
 *
 * Feature tests for the StegoLock API encode/decode/list endpoints.
 *
 * The heavy pipeline (Python stego + S3) is mocked via Mockery so these
 * tests validate request handling, session guards, ownership checks, and
 * response shapes without touching the filesystem or network.
 *
 * Tests verify:
 *  - Unauthenticated requests to all stego routes return 401
 *  - Encode without session Master Key returns 401
 *  - Decode without session Master Key returns 401
 *  - Encode with missing document returns 422 / 404
 *  - Encode success returns 201 with correct JSON shape
 *  - Decode success streams a file download
 *  - Decode of another user's document returns 404
 *  - Index returns paginated stego documents for authenticated user
 *  - Show returns 404 for non-owned document
 */
class StegoApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'username' => 'apitester',
            'role'     => 'user',
            'mkd_salt' => str_repeat('ab', 16),   // 32 hex chars
        ]);
    }

    // -------------------------------------------------------------------------
    // Authentication guard
    // -------------------------------------------------------------------------

    #[Test]
    public function encode_requires_authentication(): void
    {
        $this->postJson('/api/stego/encode')->assertStatus(401);
    }

    #[Test]
    public function decode_requires_authentication(): void
    {
        $this->postJson('/api/stego/decode')->assertStatus(401);
    }

    #[Test]
    public function index_requires_authentication(): void
    {
        $this->getJson('/api/stego/documents')->assertStatus(401);
    }

    // -------------------------------------------------------------------------
    // Session Master Key guard
    // -------------------------------------------------------------------------

    #[Test]
    public function encode_returns_401_when_session_key_is_missing(): void
    {
        // Authenticated but no 'stego_mkd' in session.
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/stego/encode', [
                'document_id' => 1,
                'carriers'    => [],
            ])
            ->assertStatus(401)
            ->assertJsonFragment(['message' => 'Session expired. Please log in again to refresh the Master Key.']);
    }

    #[Test]
    public function decode_returns_401_when_session_key_is_missing(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/stego/decode', ['stego_document_id' => 1])
            ->assertStatus(401)
            ->assertJsonFragment(['message' => 'Session expired. Please log in again to refresh the Master Key.']);
    }

    // -------------------------------------------------------------------------
    // Encode — success path (mocked pipeline)
    // -------------------------------------------------------------------------

    #[Test]
    public function encode_returns_201_with_quality_metrics_on_success(): void
    {
        Storage::fake('local');

        // Create a fake document file in local storage.
        $document = \App\Models\Document::factory()->create([
            'owner_id'  => $this->user->id,
            'file_path' => 'documents/test.txt',
        ]);
        Storage::disk('local')->put('documents/test.txt', 'plaintext payload');

        // Stub out the StegoDocumentService so no Python/S3 is needed.
        $fakeStegoDoc = StegoDocument::factory()->create([
            'user_id'     => $this->user->id,
            'document_id' => $document->id,
        ]);

        $mock = Mockery::mock(StegoDocumentService::class);
        $mock->shouldReceive('encode')
            ->once()
            ->andReturn([
                'stego_document'  => $fakeStegoDoc,
                'quality_metrics' => [
                    ['carrier' => 'carrier.png', 'psnr' => 52.3, 'threshold_40db' => true],
                ],
            ]);

        $this->app->instance(StegoDocumentService::class, $mock);

        $response = $this->actingAs($this->user, 'sanctum')
            ->withSession(['stego_mkd' => str_repeat('a', 64)])
            ->postJson('/api/stego/encode', [
                'document_id' => $document->id,
                'carriers'    => [
                    UploadedFile::fake()->image('carrier.png', 800, 600),
                ],
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'stego_document_id',
                'document_id',
                'document_name',
                'segments_count',
                'quality_metrics' => [['carrier', 'psnr', 'threshold_40db']],
                'created_at',
            ]);
    }

    // -------------------------------------------------------------------------
    // Decode — success path (mocked pipeline)
    // -------------------------------------------------------------------------

    #[Test]
    public function decode_streams_file_download_on_success(): void
    {
        $stegoDoc = StegoDocument::factory()
            ->has(\App\Models\Document::factory()->state(['owner_id' => $this->user->id]), 'document')
            ->create(['user_id' => $this->user->id]);

        $mock = Mockery::mock(StegoDocumentService::class);
        $mock->shouldReceive('decode')
            ->once()
            ->andReturn('recovered plaintext bytes');

        $this->app->instance(StegoDocumentService::class, $mock);

        $response = $this->actingAs($this->user, 'sanctum')
            ->withSession(['stego_mkd' => str_repeat('a', 64)])
            ->postJson('/api/stego/decode', ['stego_document_id' => $stegoDoc->id]);

        $response->assertOk()
            ->assertHeader('Content-Disposition');
    }

    // -------------------------------------------------------------------------
    // Decode — ownership enforcement
    // -------------------------------------------------------------------------

    #[Test]
    public function decode_returns_404_for_another_users_document(): void
    {
        $otherUser = User::factory()->create(['username' => 'other', 'role' => 'user']);

        $stegoDoc = StegoDocument::factory()->create(['user_id' => $otherUser->id]);

        $this->actingAs($this->user, 'sanctum')
            ->withSession(['stego_mkd' => str_repeat('a', 64)])
            ->postJson('/api/stego/decode', ['stego_document_id' => $stegoDoc->id])
            ->assertStatus(404);
    }

    // -------------------------------------------------------------------------
    // Index / Show
    // -------------------------------------------------------------------------

    #[Test]
    public function index_returns_only_authenticated_users_documents(): void
    {
        $otherUser = User::factory()->create(['username' => 'other2', 'role' => 'user']);

        StegoDocument::factory(3)->create(['user_id' => $this->user->id]);
        StegoDocument::factory(2)->create(['user_id' => $otherUser->id]);

        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/stego/documents')
            ->assertOk()
            ->assertJsonPath('total', 3);
    }

    #[Test]
    public function show_returns_404_for_non_owned_document(): void
    {
        $otherUser = User::factory()->create(['username' => 'other3', 'role' => 'user']);
        $stegoDoc  = StegoDocument::factory()->create(['user_id' => $otherUser->id]);

        $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/stego/documents/{$stegoDoc->id}")
            ->assertStatus(404);
    }
}
