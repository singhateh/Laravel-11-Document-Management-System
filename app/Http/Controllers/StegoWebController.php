<?php

namespace App\Http\Controllers;

use App\Http\Concerns\HasStegoEncoding;
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

        return Inertia::render('Stego/Encode', [
            'documents' => $documents,
        ]);
    }

    public function encode(Request $request)
    {
        $request->validate([
            'document_id'  => ['required', 'integer', 'exists:documents,id'],
            'carriers'     => ['required', 'array', 'min:1'],
            'carriers.*'   => ['required', 'file', 'mimes:png,bmp,jpeg,jpg', 'max:20480'],
        ]);

        // Master Key is derived at login and kept server-side only.
        $masterKey = $this->resolveSessionKey();
        if ($masterKey === null) {
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

        $carrierPaths = $this->storeCarriersTmp($request->file('carriers'));

        try {
            $result   = $this->stegoService->encode(
                $user->id,
                $plaintext,
                $masterKey,
                $carrierPaths,
                $document->id
            );
            $stegoDoc = $result['stego_document'];

            return redirect()->route('stego.index')
                ->with('success', "Document encoded and hidden in " . count($carrierPaths) . " carrier(s).");
        } catch (\Exception $e) {
            return back()->withErrors(['encode' => 'Encoding failed: ' . $e->getMessage()]);
        } finally {
            $this->releaseCarriers($carrierPaths);
        }
    }

    // ── Decode ────────────────────────────────────────────────────────────────

    public function decodeForm()
    {
        $user = Auth::user();

        $stegoDocs = StegoDocument::where('user_id', $user->id)
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
            ->with('document')
            ->firstOrFail();

        try {
            $plaintext = $this->stegoService->decode(
                $request->stego_document_id,
                $masterKey,
                $user->id
            );

            $filename = $this->buildDecodeFilename($stegoDoc);

            // Write to temp file and return as download
            $tmp = tempnam(sys_get_temp_dir(), 'stego_out_');
            file_put_contents($tmp, $plaintext);

            return response()->download($tmp, $filename)->deleteFileAfterSend(true);
        } catch (\Exception $e) {
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
}
