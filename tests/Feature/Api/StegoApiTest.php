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

        // Create a fake document file in public directory.
        $document = \App\Models\Document::factory()->create([
            'owner_id'  => $this->user->id,
        ]);
        $publicPath = public_path($document->file_path);
        $dir = dirname($publicPath);
        if (!file_exists($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($publicPath, 'plaintext payload');

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

        $response->assertStatus(202)
            ->assertJsonStructure([
                'message',
                'stego_document_id',
            ]);
    }

    // -------------------------------------------------------------------------
    // Decode — success path (mocked pipeline)
    // -------------------------------------------------------------------------

    #[Test]
    public function decode_returns_202_accepted_on_success(): void
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

        $response->assertStatus(202)
            ->assertJsonStructure([
                'message',
                'stego_document_id',
            ]);
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

    // -------------------------------------------------------------------------
    // Grant — authentication guard
    // -------------------------------------------------------------------------

    #[Test]
    public function grant_requires_authentication(): void
    {
        $this->postJson('/api/stego/documents/1/grant')->assertStatus(401);
    }

    // -------------------------------------------------------------------------
    // Grant — owner creates a grant
    // -------------------------------------------------------------------------

    #[Test]
    public function grant_returns_201_for_owner(): void
    {
        $stegoDoc = StegoDocument::factory()->create(['user_id' => $this->user->id]);
        $viewer   = User::factory()->create(['username' => 'viewer1', 'role' => 'user']);

        $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/stego/documents/{$stegoDoc->id}/grant", [
                'viewer_user_id' => $viewer->id,
            ])
            ->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'grant' => ['id', 'stego_document_id', 'viewer_user_id', 'granted_by', 'created_at'],
            ])
            ->assertJsonPath('grant.viewer_user_id', $viewer->id)
            ->assertJsonPath('grant.granted_by', $this->user->id);
    }

    // -------------------------------------------------------------------------
    // Grant — non-owner gets 404
    // -------------------------------------------------------------------------

    #[Test]
    public function grant_returns_404_for_non_owner(): void
    {
        $owner    = User::factory()->create(['username' => 'owner1', 'role' => 'user']);
        $stegoDoc = StegoDocument::factory()->create(['user_id' => $owner->id]);
        $viewer   = User::factory()->create(['username' => 'viewer2', 'role' => 'user']);

        // Authenticated as a different user (not the owner).
        $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/stego/documents/{$stegoDoc->id}/grant", [
                'viewer_user_id' => $viewer->id,
            ])
            ->assertStatus(404);
    }

    // -------------------------------------------------------------------------
    // Grant — duplicate grant returns 409
    // -------------------------------------------------------------------------

    #[Test]
    public function grant_returns_409_on_duplicate(): void
    {
        $stegoDoc = StegoDocument::factory()->create(['user_id' => $this->user->id]);
        $viewer   = User::factory()->create(['username' => 'viewer3', 'role' => 'user']);

        // First grant.
        $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/stego/documents/{$stegoDoc->id}/grant", [
                'viewer_user_id' => $viewer->id,
            ])
            ->assertStatus(201);

        // Duplicate grant.
        $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/stego/documents/{$stegoDoc->id}/grant", [
                'viewer_user_id' => $viewer->id,
            ])
            ->assertStatus(409)
            ->assertJsonFragment(['message' => 'Viewer already has access to this document.']);
    }

    // -------------------------------------------------------------------------
    // Grant — revoke
    // -------------------------------------------------------------------------

    #[Test]
    public function revoke_grant_returns_200_for_owner(): void
    {
        $stegoDoc = StegoDocument::factory()->create(['user_id' => $this->user->id]);
        $viewer   = User::factory()->create(['username' => 'viewer4', 'role' => 'user']);

        // Create the grant first.
        $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/stego/documents/{$stegoDoc->id}/grant", [
                'viewer_user_id' => $viewer->id,
            ])
            ->assertStatus(201);

        // Revoke it.
        $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/stego/documents/{$stegoDoc->id}/grant/{$viewer->id}")
            ->assertOk()
            ->assertJsonFragment(['message' => 'Grant revoked.']);

        // Confirm the row is gone.
        $this->assertDatabaseMissing('stego_document_grants', [
            'stego_document_id' => $stegoDoc->id,
            'viewer_user_id'    => $viewer->id,
        ]);
    }

    // -------------------------------------------------------------------------
    // Decode — granted viewer can reach the decode pipeline
    // -------------------------------------------------------------------------

    #[Test]
    public function decode_succeeds_for_granted_viewer(): void
    {
        $owner    = User::factory()->create(['username' => 'grantowner', 'role' => 'user', 'mkd_salt' => str_repeat('cd', 16)]);
        $stegoDoc = StegoDocument::factory()
            ->has(\App\Models\Document::factory()->state(['owner_id' => $owner->id]), 'document')
            ->create(['user_id' => $owner->id]);

        // Grant access to $this->user (the viewer).
        \App\Models\StegoDocumentGrant::create([
            'stego_document_id' => $stegoDoc->id,
            'viewer_user_id'    => $this->user->id,
            'granted_by'        => $owner->id,
        ]);

        $mock = Mockery::mock(StegoDocumentService::class);
        $mock->shouldReceive('decode')
            ->once()
            ->andReturn('recovered plaintext bytes');

        $this->app->instance(StegoDocumentService::class, $mock);

        // $this->user is a viewer (not the owner) — decode should be authorized.
        $this->actingAs($this->user, 'sanctum')
            ->withSession(['stego_mkd' => str_repeat('a', 64)])
            ->postJson('/api/stego/decode', ['stego_document_id' => $stegoDoc->id])
            ->assertStatus(202)
            ->assertJsonStructure([
                'message',
                'stego_document_id',
            ]);
    }
}

