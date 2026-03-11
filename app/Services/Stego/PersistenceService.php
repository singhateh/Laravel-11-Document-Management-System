<?php

namespace App\Services\Stego;

use App\Models\StegoDocument;
use App\Models\StegoCarrier;
use App\Models\StegoSegment;
use App\Models\AccessLog;
use Exception;
use Illuminate\Support\Facades\DB;

/**
 * PersistenceService
 *
 * Wraps all Eloquent operations for StegoLock entities.
 * Provides a clean interface so the StegoDocumentService orchestrator
 * does not directly touch models, matching the microservice boundary
 * described in the .md guide.
 *
 * Replaces the gRPC persistence-service microservice.
 */
class PersistenceService
{
    // -------------------------------------------------------------------------
    // StegoDocument
    // -------------------------------------------------------------------------

    /**
     * Persist a new StegoDocument record (encryption metadata).
     *
     * @param  array{
     *   document_id: int|null,
     *   user_id: int,
     *   ciphertext: string,
     *   stego_iv: string,
     *   stego_auth_tag: string,
     *   stego_hash_sha256: string,
     *   stego_dek_salt: string|null,
     *   stego_dek_iter: int,
     *   s3_key: string|null,
     * } $data
     * @return StegoDocument
     */
    public function createStegoDocument(array $data): StegoDocument
    {
        return StegoDocument::create($data);
    }

    /**
     * Update an existing StegoDocument (e.g. fill in crypto fields on a
     * pending skeleton row created before the encode job was dispatched).
     *
     * @param  int   $id
     * @param  array $data
     * @return StegoDocument
     * @throws Exception
     */
    public function updateStegoDocument(int $id, array $data): StegoDocument
    {
        $doc = StegoDocument::findOrFail($id);
        $doc->update($data);
        return $doc->fresh();
    }

    /**
     * Find a StegoDocument by its primary key, eager-loading segments.
     *
     * @param  int $id
     * @return StegoDocument
     * @throws Exception
     */
    public function findStegoDocument(int $id): StegoDocument
    {
        $doc = StegoDocument::with(['segments' => fn ($q) => $q->orderBy('segment_index')])
            ->find($id);

        if (!$doc) {
            throw new Exception("StegoDocument #{$id} not found.");
        }

        return $doc;
    }

    /**
     * Find the StegoDocument linked to an existing document_id.
     *
     * @param  int $documentId
     * @return StegoDocument
     * @throws Exception
     */
    public function findByDocumentId(int $documentId): StegoDocument
    {
        $doc = StegoDocument::with('segments')
            ->where('document_id', $documentId)
            ->first();

        if (!$doc) {
            throw new Exception("No StegoDocument found for document_id #{$documentId}.");
        }

        return $doc;
    }

    /**
     * Delete a StegoDocument and all its segments (cascades via FK).
     *
     * @param  int $id
     */
    public function deleteStegoDocument(int $id): void
    {
        StegoDocument::find($id)?->delete();
    }

    // -------------------------------------------------------------------------
    // StegoCarrier
    // -------------------------------------------------------------------------

    /**
     * Persist a new StegoCarrier (carrier file metadata).
     *
     * @param  array{
     *   name: string,
     *   file_path: string,
     *   file_type: string,
     *   mime_type: string|null,
     *   size: int,
     *   s3_key: string|null,
     *   uploaded_by: int,
     * } $data
     * @return StegoCarrier
     */
    public function createStegoCarrier(array $data): StegoCarrier
    {
        return StegoCarrier::create($data);
    }

    /**
     * Find a StegoCarrier by its primary key.
     *
     * @param  int $id
     * @return StegoCarrier
     * @throws Exception
     */
    public function findStegoCarrier(int $id): StegoCarrier
    {
        $carrier = StegoCarrier::find($id);

        if (!$carrier) {
            throw new Exception("StegoCarrier #{$id} not found.");
        }

        return $carrier;
    }

    /**
     * Delete a StegoCarrier and its segments.
     *
     * @param  int $id
     */
    public function deleteStegoCarrier(int $id): void
    {
        StegoCarrier::find($id)?->delete();
    }

    // -------------------------------------------------------------------------
    // StegoSegment
    // -------------------------------------------------------------------------

    /**
     * Persist a single StegoSegment (one chunk of the encrypted payload).
     *
     * @param  array{
     *   stego_document_id: int,
     *   stego_carrier_id: int,
     *   segment_index: int,
     *   encrypted_chunk: string,
     *   s3_key: string|null,
     *   chunk_hash: string,
     * } $data
     * @return StegoSegment
     */
    public function createStegoSegment(array $data): StegoSegment
    {
        return StegoSegment::create($data);
    }

    /**
     * Persist multiple segments in a single transaction.
     *
     * @param  array<int, array> $segmentsData Array of createStegoSegment() payloads
     * @return StegoSegment[]
     * @throws \Throwable
     */
    public function createStegoSegments(array $segmentsData): array
    {
        $created = [];

        DB::transaction(function () use ($segmentsData, &$created) {
            foreach ($segmentsData as $data) {
                $created[] = $this->createStegoSegment($data);
            }
        });

        return $created;
    }

    /**
     * Retrieve all segments for a StegoDocument, ordered by index.
     *
     * @param  int $stegoDocumentId
     * @return StegoSegment[]
     */
    public function getSegments(int $stegoDocumentId): array
    {
        return StegoSegment::where('stego_document_id', $stegoDocumentId)
            ->orderBy('segment_index')
            ->get()
            ->all();
    }

    // -------------------------------------------------------------------------
    // Access Logging
    // -------------------------------------------------------------------------

    /**
     * Write an access log entry for a StegoLock operation.
     *
     * @param  array{
     *   user_id: int|null,
     *   action: string,
     *   resource: string,
     *   resource_id: string|int|null,
     *   ip_address: string|null,
     *   user_agent: string|null,
     *   method: string|null,
     *   url: string|null,
     *   status_code: int|null,
     *   payload: array|null,
     * } $data
     * @return AccessLog
     */
    public function logAccess(array $data): AccessLog
    {
        return AccessLog::create(array_merge([
            'user_id'     => null,
            'resource_id' => null,
            'ip_address'  => null,
            'user_agent'  => null,
            'method'      => null,
            'url'         => null,
            'status_code' => null,
            'payload'     => null,
        ], $data));
    }
}
