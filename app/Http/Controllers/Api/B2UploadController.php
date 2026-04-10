<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\PreprocessUploadedDocumentJob;
use App\Jobs\RunStegoPostUploadWorkflowJob;
use App\Jobs\ValidateUploadedDocumentJob;
use App\Models\AccessLog;
use App\Models\B2UploadSession;
use App\Models\Document;
use App\Services\DocumentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class B2UploadController extends Controller
{
    private const DISK = 'b2';
    private const MAX_UPLOAD_BYTES = 52_428_800; // 50 MB
    private const SIGN_URL_TTL_MINUTES = 10;
    private const SESSION_TTL_MINUTES = 15;

    public function __construct(private readonly DocumentService $documentService) {}

    public function sign(Request $request): JsonResponse
    {
        $request->validate([
            'original_filename' => ['required', 'string', 'max:255'],
            'mime_type' => ['required', 'string', 'in:application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,text/plain'],
            'size' => ['required_without:size_bytes', 'integer', 'min:1', 'max:51200'], // KB
            'size_bytes' => ['required_without:size', 'integer', 'min:1', 'max:' . self::MAX_UPLOAD_BYTES],
            'folder_id' => ['required', 'integer', 'exists:folders,id'],
            'visibility' => ['sometimes', 'in:public,private'],
            'idempotency_token' => ['sometimes', 'string', 'max:100'],
        ]);

        $user = Auth::user();
        $disk = Storage::disk(self::DISK);

        if (!method_exists($disk, 'temporaryUploadUrl')) {
            return response()->json([
                'message' => 'Signed direct upload is not supported by the configured storage adapter.',
            ], 501);
        }

        $originalName = (string) $request->input('original_filename');
        $sanitizedName = preg_replace('/[^A-Za-z0-9._-]/', '_', $originalName) ?: 'upload.bin';
        $sizeBytesInput = $request->input('size_bytes');
        $sizeBytes = $sizeBytesInput !== null
            ? (int) $sizeBytesInput
            : ((int) $request->input('size') * 1024);
        $mimeType = (string) $request->input('mime_type');
        $folderId = (int) $request->input('folder_id');
        $visibility = (string) $request->input('visibility', 'public');
        $idempotencyToken = (string) $request->input('idempotency_token', Str::uuid()->toString());

        if ($sizeBytes > self::MAX_UPLOAD_BYTES) {
            return response()->json(['message' => 'File exceeds max allowed size of 50 MB.'], 422);
        }

        $objectKey = sprintf(
            'uploads/%d/%s-%s',
            (int) $user->id,
            (string) Str::uuid(),
            $sanitizedName
        );

        /** @var mixed $signedUpload */
        $signedUpload = call_user_func(
            [$disk, 'temporaryUploadUrl'],
            $objectKey,
            now()->addMinutes(self::SIGN_URL_TTL_MINUTES),
            ['ContentType' => $mimeType]
        );

        $uploadUrl = null;
        $headers = [];

        if (is_array($signedUpload)) {
            $candidateUrl = $signedUpload['url'] ?? $signedUpload[0] ?? null;
            $candidateHeaders = $signedUpload['headers'] ?? $signedUpload[1] ?? [];

            if (is_string($candidateUrl) && $candidateUrl !== '') {
                $uploadUrl = $candidateUrl;
            }

            if (is_array($candidateHeaders)) {
                $headers = $candidateHeaders;
            }
        }

        if (is_array($headers)) {
            $headers = collect($headers)
                ->reject(function ($value, $name): bool {
                    if (!is_string($name)) {
                        return true;
                    }

                    $normalized = strtolower(trim($name));

                    return in_array($normalized, [
                        'host',
                        'origin',
                        'referer',
                        'user-agent',
                        'content-length',
                    ], true);
                })
                ->mapWithKeys(function ($value, $name): array {
                    if (!is_scalar($value)) {
                        return [];
                    }

                    return [(string) $name => (string) $value];
                })
                ->all();
        }

        if (!is_string($uploadUrl) || $uploadUrl === '') {
            return response()->json([
                'message' => 'Failed to generate temporary upload URL from the storage adapter.',
            ], 500);
        }

        $session = B2UploadSession::create([
            'session_token' => (string) Str::uuid(),
            'idempotency_token' => $idempotencyToken,
            'user_id' => (int) $user->id,
            'object_key' => $objectKey,
            'original_filename' => $originalName,
            'sanitized_filename' => $sanitizedName,
            'expected_mime' => $mimeType,
            'expected_size' => $sizeBytes,
            'folder_id' => $folderId,
            'visibility' => $visibility,
            'status' => 'signed',
            'expires_at' => now()->addMinutes(self::SESSION_TTL_MINUTES),
        ]);

        return response()->json([
            'session_token' => $session->session_token,
            'idempotency_token' => $session->idempotency_token,
            'object_key' => $objectKey,
            'upload_url' => $uploadUrl,
            'headers' => $headers,
            'expires_at' => now()->addMinutes(self::SIGN_URL_TTL_MINUTES)->toISOString(),
        ]);
    }

    public function finalize(Request $request): JsonResponse
    {
        $request->validate([
            'session_token' => ['required', 'string'],
            'object_key' => ['required', 'string'],
            'file_size' => ['required', 'integer', 'min:1', 'max:' . self::MAX_UPLOAD_BYTES],
            'mime_type' => ['required', 'string', 'in:application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,text/plain'],
            'checksum_sha256' => ['sometimes', 'nullable', 'string', 'size:64'],
        ]);

        $user = Auth::user();
        $sessionToken = (string) $request->input('session_token');
        $objectKey = (string) $request->input('object_key');
        $fileSize = (int) $request->input('file_size');
        $mimeType = (string) $request->input('mime_type');
        $checksumSha256 = $request->input('checksum_sha256');

        $session = B2UploadSession::where('session_token', $sessionToken)
            ->where('user_id', (int) $user->id)
            ->first();

        if (!$session) {
            return response()->json(['message' => 'Upload session not found.'], 404);
        }

        if ($session->consumed_at && $session->document_id) {
            $existing = Document::find($session->document_id);
            if ($existing) {
                return response()->json([
                    'message' => 'Upload session already finalized.',
                    'document' => $this->documentResource($existing),
                ], 200);
            }
        }

        if ($session->expires_at && $session->expires_at->isPast()) {
            return response()->json(['message' => 'Upload session expired.'], 422);
        }

        $expectedPrefix = 'uploads/' . (int) $user->id . '/';
        if (!str_starts_with($objectKey, $expectedPrefix)) {
            return response()->json(['message' => 'Object key prefix is invalid for this user.'], 403);
        }

        if ($session->object_key !== $objectKey) {
            return response()->json(['message' => 'Object key mismatch for session.'], 422);
        }

        if ((int) $session->expected_size !== $fileSize || $fileSize > self::MAX_UPLOAD_BYTES) {
            return response()->json(['message' => 'File size does not match signed upload session.'], 422);
        }

        if ((string) $session->expected_mime !== $mimeType) {
            return response()->json(['message' => 'MIME type does not match signed upload session.'], 422);
        }

        $disk = Storage::disk(self::DISK);
        if (!$disk->exists($objectKey)) {
            return response()->json(['message' => 'Uploaded object was not found.'], 422);
        }

        $actualSize = (int) $disk->size($objectKey);
        if ($actualSize !== $fileSize || $actualSize <= 0 || $actualSize > self::MAX_UPLOAD_BYTES) {
            return response()->json(['message' => 'Uploaded object size verification failed.'], 422);
        }

        $actualMime = '';
        if (method_exists($disk, 'mimeType')) {
            $resolvedMime = call_user_func([$disk, 'mimeType'], $objectKey);
            $actualMime = is_string($resolvedMime) ? $resolvedMime : '';
        }
        if ($actualMime !== '' && strcasecmp($actualMime, $mimeType) !== 0) {
            return response()->json(['message' => 'Uploaded object MIME verification failed.'], 422);
        }

        if (is_string($checksumSha256) && $checksumSha256 !== '') {
            $readStream = $disk->readStream($objectKey);
            if (!is_resource($readStream)) {
                return response()->json(['message' => 'Unable to read uploaded object for checksum verification.'], 500);
            }

            $hashContext = hash_init('sha256');
            hash_update_stream($hashContext, $readStream);
            fclose($readStream);

            $computed = hash_final($hashContext);
            if (!hash_equals(strtolower($checksumSha256), strtolower($computed))) {
                return response()->json(['message' => 'Uploaded object checksum verification failed.'], 422);
            }
        }

        try {
            $result = DB::transaction(function () use ($disk, $session, $objectKey, $fileSize, $mimeType, $checksumSha256, $request, $user) {
                $lockedSession = B2UploadSession::whereKey($session->id)->lockForUpdate()->firstOrFail();
                if ($lockedSession->consumed_at && $lockedSession->document_id) {
                    $existing = Document::find($lockedSession->document_id);
                    if ($existing) {
                        return $existing;
                    }
                }

                $folderId = (int) $lockedSession->folder_id;
                $visibility = (string) $lockedSession->visibility;
                $originalName = (string) $lockedSession->original_filename;
                $extension = strtolower(pathinfo($lockedSession->sanitized_filename, PATHINFO_EXTENSION));

                $destDir = 'documents/' . $folderId;
                $destPath = public_path($destDir);
                if (!file_exists($destPath)) {
                    mkdir($destPath, 0777, true);
                }

                $baseName = pathinfo($originalName, PATHINFO_FILENAME);
                $candidate = $originalName;
                $counter = 1;
                while (file_exists($destPath . DIRECTORY_SEPARATOR . $candidate)) {
                    $suffix = '_' . $counter;
                    $candidate = $extension !== ''
                        ? $baseName . $suffix . '.' . $extension
                        : $baseName . $suffix;
                    $counter++;
                }

                $absoluteTarget = $destPath . DIRECTORY_SEPARATOR . $candidate;
                $readStream = $disk->readStream($objectKey);
                if (!is_resource($readStream)) {
                    throw new \RuntimeException('Could not read uploaded object from cloud storage.');
                }

                $writeStream = fopen($absoluteTarget, 'wb');
                if (!is_resource($writeStream)) {
                    fclose($readStream);
                    throw new \RuntimeException('Could not prepare local file destination.');
                }

                stream_copy_to_stream($readStream, $writeStream);
                fclose($readStream);
                fclose($writeStream);

                $relPath = $destDir . '/' . $candidate;

                $doc = Document::create([
                    'name' => $candidate,
                    'original_name' => $originalName,
                    'extension' => $extension,
                    'file_path' => $relPath,
                    'size' => $fileSize,
                    'folder_id' => $folderId,
                    'visibility' => $visibility,
                    'owner_id' => $user->id,
                    'document_date' => now(),
                    'ingest_status' => 'pending',
                    'ingest_error' => null,
                ]);

                if ($this->documentService->isEncryptionEnabled()) {
                    $this->documentService->encryptDocumentFile($doc);
                    $doc->refresh();
                }

                $lockedSession->update([
                    'status' => 'queued',
                    'checksum_sha256' => is_string($checksumSha256) ? strtolower($checksumSha256) : null,
                    'uploaded_at' => now(),
                    'finalized_at' => now(),
                    'consumed_at' => now(),
                    'document_id' => $doc->id,
                    'error_message' => null,
                ]);

                $disk->delete($objectKey);

                AccessLog::log('upload', 'document', (string) $doc->id, $request, 201);

                ValidateUploadedDocumentJob::withChain([
                    new PreprocessUploadedDocumentJob($doc->id, $lockedSession->id),
                    new RunStegoPostUploadWorkflowJob($doc->id, $lockedSession->id),
                ])->dispatch($doc->id, $lockedSession->id);

                return $doc;
            });
        } catch (\Throwable $e) {
            B2UploadSession::whereKey($session->id)->update([
                'status' => 'failed',
                'error_message' => substr($e->getMessage(), 0, 1000),
            ]);

            return response()->json(['message' => 'Finalize failed: ' . $e->getMessage()], 500);
        }

        return response()->json([
            'message' => 'Direct upload finalized successfully.',
            'document' => $this->documentResource($result),
            'session_token' => $sessionToken,
            'status' => $result->ingest_status,
        ], 201);
    }

    public function status(string $sessionToken): JsonResponse
    {
        $user = Auth::user();

        $session = B2UploadSession::where('session_token', $sessionToken)
            ->where('user_id', (int) $user->id)
            ->first();

        if (!$session) {
            return response()->json(['message' => 'Upload session not found.'], 404);
        }

        $document = $session->document_id ? Document::find($session->document_id) : null;

        return response()->json([
            'session_token' => $session->session_token,
            'status' => $session->status,
            'expires_at' => $session->expires_at?->toISOString(),
            'finalized_at' => $session->finalized_at?->toISOString(),
            'error_message' => $session->error_message,
            'document' => $document ? [
                'id' => $document->id,
                'ingest_status' => $document->ingest_status,
                'ingest_error' => $document->ingest_error,
            ] : null,
        ]);
    }

    private function documentResource(Document $doc): array
    {
        return [
            'id' => $doc->id,
            'name' => $doc->name,
            'original_name' => $doc->original_name,
            'extension' => $doc->extension,
            'size' => $doc->size,
            'visibility' => $doc->visibility,
            'folder_id' => $doc->folder_id,
            'owner_id' => $doc->owner_id,
            'is_encrypted' => $doc->is_encrypted,
            'sha256' => $doc->enc_hash_sha256,
            'url' => $doc->url,
            'download_url' => $doc->hasPhysicalFile() ? url('/documents/' . $doc->id . '/download') : null,
            'ingest_status' => $doc->ingest_status,
            'ingest_error' => $doc->ingest_error,
            'created_at' => $doc->created_at?->toISOString(),
            'updated_at' => $doc->updated_at?->toISOString(),
        ];
    }
}
