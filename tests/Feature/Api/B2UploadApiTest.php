<?php

namespace Tests\Feature\Api;

use App\Models\B2UploadSession;
use App\Models\Document;
use App\Models\Folder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class B2UploadApiTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Document::query()->get()->each(function (Document $document): void {
            $absolutePath = public_path($document->file_path ?? '');
            if ($absolutePath !== '' && is_file($absolutePath)) {
                @unlink($absolutePath);
            }
        });

        parent::tearDown();
    }

    #[Test]
    public function sign_requires_authentication(): void
    {
        $this->postJson('/api/uploads/b2/sign', [])->assertStatus(401);
    }

    #[Test]
    public function finalize_requires_authentication(): void
    {
        $this->postJson('/api/uploads/b2/finalize', [])->assertStatus(401);
    }

    #[Test]
    public function status_requires_authentication(): void
    {
        $this->getJson('/api/uploads/b2/sessions/fake-token/status')->assertStatus(401);
    }

    #[Test]
    public function sign_validates_payload_fields(): void
    {
        $user = $this->createApiUser('b2tester');

        Folder::create([
            'name' => 'Uploads',
            'visibility' => 'public',
        ]);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/uploads/b2/sign', [
            'original_filename' => 'file.bin',
            'size' => 1024,
            'mime_type' => 'application/octet-stream',
            'folder_id' => 999999,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['mime_type', 'folder_id']);
    }

    #[Test]
    public function sign_succeeds_with_mocked_b2_disk_and_persists_session(): void
    {
        $user = $this->createApiUser('b2signsuccess');
        $folder = Folder::create([
            'name' => 'Uploads',
            'visibility' => 'public',
        ]);

        $uploadUrl = 'https://b2.example/mock-upload-url';
        $signedHeaders = ['Authorization' => 'mock-signature'];
        $fakeDisk = new FakeB2Disk($uploadUrl, $signedHeaders, 1024, 'application/pdf', str_repeat('A', 1024));

        Storage::shouldReceive('disk')
            ->once()
            ->with('b2')
            ->andReturn($fakeDisk);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/uploads/b2/sign', [
            'original_filename' => 'report.pdf',
            'size' => 1,
            'mime_type' => 'application/pdf',
            'folder_id' => $folder->id,
            'visibility' => 'private',
            'idempotency_token' => 'idem-sign-success',
        ]);

        $response->assertOk()
            ->assertJsonPath('upload_url', $uploadUrl)
            ->assertJsonPath('headers.Authorization', 'mock-signature')
            ->assertJsonPath('idempotency_token', 'idem-sign-success');

        $sessionToken = (string) $response->json('session_token');
        $objectKey = (string) $response->json('object_key');

        $this->assertNotSame('', $sessionToken);
        $this->assertTrue(str_starts_with($objectKey, 'uploads/' . $user->id . '/'));
        $this->assertSame($objectKey, $fakeDisk->signedObjectKey);

        $this->assertDatabaseHas('b2_upload_sessions', [
            'session_token' => $sessionToken,
            'idempotency_token' => 'idem-sign-success',
            'user_id' => $user->id,
            'object_key' => $objectKey,
            'expected_mime' => 'application/pdf',
            'expected_size' => 1024,
            'folder_id' => $folder->id,
            'visibility' => 'private',
            'status' => 'signed',
        ]);
    }

    #[Test]
    public function finalize_succeeds_after_sign_with_mocked_b2_disk_behaviour(): void
    {
        $user = $this->createApiUser('b2finalizesuccess');
        $folder = Folder::create([
            'name' => 'Finalize Uploads',
            'visibility' => 'public',
        ]);

        $uploadedContent = str_repeat('A', 1024);
        $fakeDisk = new FakeB2Disk(
            'https://b2.example/mock-upload-url',
            ['Authorization' => 'mock-signature'],
            1024,
            'application/pdf',
            $uploadedContent
        );

        Storage::shouldReceive('disk')
            ->times(2)
            ->with('b2')
            ->andReturn($fakeDisk);

        $signResponse = $this->actingAs($user, 'sanctum')->postJson('/api/uploads/b2/sign', [
            'original_filename' => 'report.pdf',
            'size' => 1,
            'mime_type' => 'application/pdf',
            'folder_id' => $folder->id,
            'visibility' => 'public',
            'idempotency_token' => 'idem-finalize-success',
        ]);

        $signResponse->assertOk();

        $sessionToken = (string) $signResponse->json('session_token');
        $objectKey = (string) $signResponse->json('object_key');
        $this->assertNotSame('', $sessionToken);
        $this->assertSame($objectKey, $fakeDisk->signedObjectKey);

        $finalizeResponse = $this->actingAs($user, 'sanctum')->postJson('/api/uploads/b2/finalize', [
            'session_token' => $sessionToken,
            'object_key' => $objectKey,
            'file_size' => 1024,
            'mime_type' => 'application/pdf',
        ]);

        $finalizeResponse->assertStatus(201)
            ->assertJsonPath('document.original_name', 'report.pdf')
            ->assertJsonPath('document.folder_id', $folder->id)
            ->assertJsonPath('document.owner_id', $user->id);

        $documentId = (int) $finalizeResponse->json('document.id');
        $this->assertGreaterThan(0, $documentId);

        $document = Document::findOrFail($documentId);
        $this->assertTrue(is_file(public_path($document->file_path)));

        $session = B2UploadSession::where('session_token', $sessionToken)->first();
        $this->assertNotNull($session);
        $this->assertSame($documentId, (int) $session->document_id);
        $this->assertNotNull($session->consumed_at);
        $this->assertNotSame('failed', (string) $session->status);
        $this->assertSame($objectKey, $fakeDisk->deletedObjectKey);
    }

    private function createApiUser(string $username): User
    {
        /** @var User $user */
        $user = User::factory()->create([
            'username' => $username,
            'role' => 'user',
            'mkd_salt' => str_repeat('ab', 16),
        ]);

        return $user;
    }
}

class FakeB2Disk
{
    public ?string $signedObjectKey = null;
    public ?string $deletedObjectKey = null;

    public function __construct(
        private readonly string $uploadUrl,
        private readonly array $headers,
        private readonly int $expectedSize,
        private readonly string $expectedMime,
        private readonly string $content,
    ) {}

    public function temporaryUploadUrl(string $objectKey, $expiresAt, array $options): array
    {
        if (!isset($options['ContentType']) || $options['ContentType'] !== $this->expectedMime) {
            throw new \RuntimeException('Unexpected ContentType for temporary upload URL.');
        }

        $this->signedObjectKey = $objectKey;

        return [$this->uploadUrl, $this->headers];
    }

    public function exists(string $objectKey): bool
    {
        $this->assertExpectedObjectKey($objectKey);
        return true;
    }

    public function size(string $objectKey): int
    {
        $this->assertExpectedObjectKey($objectKey);
        return $this->expectedSize;
    }

    public function mimeType(string $objectKey): string
    {
        $this->assertExpectedObjectKey($objectKey);
        return $this->expectedMime;
    }

    public function readStream(string $objectKey)
    {
        $this->assertExpectedObjectKey($objectKey);
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $this->content);
        rewind($stream);
        return $stream;
    }

    public function delete(string $objectKey): bool
    {
        $this->assertExpectedObjectKey($objectKey);
        $this->deletedObjectKey = $objectKey;
        return true;
    }

    private function assertExpectedObjectKey(string $objectKey): void
    {
        if ($this->signedObjectKey === null) {
            throw new \RuntimeException('Signed object key is not initialized.');
        }

        if ($objectKey !== $this->signedObjectKey) {
            throw new \RuntimeException('Unexpected object key provided to fake disk.');
        }
    }
}
