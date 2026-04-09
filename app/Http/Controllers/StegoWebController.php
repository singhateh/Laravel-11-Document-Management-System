<?php

namespace App\Http\Controllers;

use App\Http\Concerns\HasStegoEncoding;
use App\Jobs\EncodeStegoDocumentJob;
use App\Models\Document;
use App\Models\StegoDocument;
use App\Services\Stego\StegoDocumentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class StegoWebController extends Controller
{
    use HasStegoEncoding;

    public function __construct(protected StegoDocumentService $stegoService)
    {
        $this->middleware('auth');
    }

    // ── Index ─────────────────────────────────────────────────────────────────

    public function index()
    {
        $user = Auth::user();

        $stegoDocs = StegoDocument::where('user_id', $user->id)
            ->select([
                'id',
                'document_id',
                'user_id',
                'status',
                'decoding_status',
                'download_path',
                'created_at',
                'updated_at',
                'stego_hash_sha256',
            ])
            ->with(['document:id,name,extension,size'])
            ->withCount('segments')
            ->latest()
            ->paginate(20);

        return Inertia::render('Stego/Index', [
            'stegoDocs' => $stegoDocs,
        ]);
    }

    // ── Encode ────────────────────────────────────────────────────────────────

    public function encodeForm()
    {
        $documents = Document::select('id', 'name', 'extension', 'size', 'file_path')
            ->latest()
            ->get();

        // Get system carriers for fallback option
        $systemCarriers = \App\Models\StegoCarrier::where('uploaded_by', \App\Models\User::where('role', 'admin')->first()->id ?? 0)
            ->where('validation_status', 'valid')
            ->where('is_in_use', false)
            ->select(['id', 'name', 'capacity_bytes', 'file_path'])
            ->get();

        return Inertia::render('Stego/Encode', [
            'documents' => $documents,
            'systemCarriers' => $systemCarriers,
        ]);
    }

    public function encode(Request $request)
    {
        $useSystemCarriers = $request->boolean('use_system_carriers');

        $request->validate([
            'document_id'  => ['required', 'integer', 'exists:documents,id'],
        ]);

        // Master Key is derived at login and kept server-side only.
        $masterKey = $this->resolveSessionKey();
        if ($masterKey === null) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Session expired. Please log in again.'], 419);
            }
            return back()->withErrors(['session' => 'Session expired. Please log in again to obtain a fresh Master Key.']);
        }

        $user = Auth::user();

        // Resolve the source document path
        $document = Document::findOrFail($request->document_id);

        try {
            $plaintext = $this->readDocumentPlaintext($document);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['document_id' => $e->getMessage()]);
        }

        // Pre-create pending record so users can immediately see queued state in index.
        $pending = StegoDocument::create([
            'document_id' => $document->id,
            'user_id'     => $user->id,
            'status'      => 'pending',
        ]);

        // Manual carrier uploads are disabled for web encode.
        // Encoding now always uses carrier pool + optional system fallback.
        [$plainPath, $carrierPaths] = $this->stageForQueue($plaintext, []);

        EncodeStegoDocumentJob::dispatchAfterResponse(
            $user->id,
            $plainPath,
            $masterKey,
            $carrierPaths,
            $document->id,
            $pending->id,
            $useSystemCarriers,
        );

        return redirect()->route('stego.index')
            ->with('success', 'Encoding queued. Your document will appear once processing completes.');
    }

    // ── Decode ────────────────────────────────────────────────────────────────

    public function decodeForm()
    {
        $user = Auth::user();

        $stegoDocs = StegoDocument::where('user_id', $user->id)
            ->select([
                'id',
                'document_id',
                'user_id',
                'decoding_status',
                'decoding_error',
                'download_path',
                'created_at',
            ])
            ->with(['document:id,name,extension'])
            ->withCount('segments')
            ->latest()
            ->get()
            ->map(fn ($s) => [
                'id'             => $s->id,
                'document'       => $s->document ? [
                    'id'        => $s->document->id,
                    'name'      => $s->document->name,
                    'extension' => $s->document->extension,
                ] : null,
                'segments_count' => $s->segments_count,
                'decoding_status' => $s->decoding_status,
                'decoding_error'  => $s->decoding_error,
                'download_path'   => $s->download_path,
                'created_at'     => $s->created_at?->toISOString(),
            ]);

        return Inertia::render('Stego/Decode', [
            'stegoDocs' => $stegoDocs,
        ]);
    }

    public function decode(Request $request)
    {
        $request->validate([
            'stego_document_id' => ['required', 'integer'],
        ]);

        // Master Key is derived at login and kept server-side only.
        $masterKey = $this->resolveSessionKey();
        if ($masterKey === null) {
            return back()->withErrors(['session' => 'Session expired. Please log in again to obtain a fresh Master Key.']);
        }

        $user = Auth::user();

        // Verify ownership
        $stegoDoc = StegoDocument::where('user_id', $user->id)
            ->where('id', $request->stego_document_id)
            ->select(['id', 'document_id', 'user_id'])
            ->with('document')
            ->firstOrFail();

        // Keep web decode state aligned with API decode workflow.
        $stegoDoc->update([
            'decoding_status' => 'pending',
            'decoding_error'  => null,
            'download_path'   => null,
        ]);

        try {
            $plaintext = $this->stegoService->decode(
                $request->stego_document_id,
                $masterKey,
                $user->id
            );

            $filename = $this->buildDecodeFilename($stegoDoc);

            $downloadPath = 'decoded/' . $stegoDoc->id . '/' . $filename;
            Storage::disk('local')->put($downloadPath, $plaintext);

            $stegoDoc->update([
                'decoding_status' => 'completed',
                'download_path'   => $downloadPath,
            ]);

            return response()->download(Storage::disk('local')->path($downloadPath), $filename);
        } catch (\Exception $e) {
            $stegoDoc->update([
                'decoding_status' => 'failed',
                'decoding_error'  => substr($e->getMessage(), 0, 500),
            ]);

            if ($request->expectsJson()) {
                return response()->json(['message' => 'Decoding failed: ' . $e->getMessage()], 422);
            }
            return back()->withErrors(['decode' => 'Decoding failed: ' . $e->getMessage()]);
        }
    }

    // ── Destroy ───────────────────────────────────────────────────────────────

    public function destroy(int $id)
    {
        $user = Auth::user();
        $stegoDoc = StegoDocument::where('user_id', $user->id)->findOrFail($id);
        $stegoDoc->delete();

        return redirect()->route('stego.index')->with('success', 'Stego document deleted.');
    }

    // ── Token management (Inertia page) ───────────────────────────────────────

    public function tokens()
    {
        $tokens = Auth::user()->tokens()
            ->select('id', 'name', 'abilities', 'last_used_at', 'expires_at', 'created_at')
            ->latest()
            ->get();

        return Inertia::render('Stego/Tokens', [
            'tokens' => $tokens,
        ]);
    }

    // ── Carrier Pool management (Inertia page) ───────────────────────────────

    public function carrierPool()
    {
        $user = Auth::user();

        $carriers = \App\Models\StegoCarrier::where('uploaded_by', $user->id)
            ->select([
                'id', 'name', 'file_type', 'mime_type', 'size',
                'psnr', 'capacity_bytes', 'validation_status',
                'validation_error', 'is_in_use', 'validated_at', 'created_at',
            ])
            ->latest()
            ->paginate(20);

        // Calculate pool statistics
        $totalCarriers = \App\Models\StegoCarrier::where('uploaded_by', $user->id)->count();
        $validCarriers = \App\Models\StegoCarrier::where('uploaded_by', $user->id)
            ->where('validation_status', 'valid')
            ->count();
        $totalCapacity = \App\Models\StegoCarrier::where('uploaded_by', $user->id)
            ->where('validation_status', 'valid')
            ->sum('capacity_bytes');
        $inUseCount = \App\Models\StegoCarrier::where('uploaded_by', $user->id)
            ->where('is_in_use', true)
            ->count();

        return Inertia::render('Stego/CarrierPool', [
            'carriers' => $carriers,
            'stats' => [
                'total' => $totalCarriers,
                'valid' => $validCarriers,
                'totalCapacity' => $totalCapacity,
                'inUse' => $inUseCount,
            ],
        ]);
    }
}
