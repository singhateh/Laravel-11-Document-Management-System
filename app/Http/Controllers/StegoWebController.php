<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\StegoDocument;
use App\Services\Stego\StegoDocumentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class StegoWebController extends Controller
{
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
            'master_key'   => ['required', 'string', 'min:8'],
            'carriers'     => ['required', 'array', 'min:1'],
            'carriers.*'   => ['required', 'file', 'mimes:png,bmp', 'max:20480'],
        ]);

        $user = Auth::user();

        // Resolve the source document path
        $document   = Document::findOrFail($request->document_id);
        $sourcePath = storage_path('app/' . $document->file_path);

        if (!file_exists($sourcePath)) {
            return back()->withErrors(['document_id' => 'Source document file not found on disk.']);
        }

        $plaintext = file_get_contents($sourcePath);

        // Save uploaded carrier files to temp paths
        $carrierPaths = [];
        foreach ($request->file('carriers') as $carrier) {
            $tmp = tempnam(sys_get_temp_dir(), 'carrier_');
            $carrier->move(dirname($tmp), basename($tmp));
            $carrierPaths[] = $tmp;
        }

        try {
            $stegoDoc = $this->stegoService->encode(
                $user->id,
                $plaintext,
                $request->master_key,
                $carrierPaths,
                $document->id
            );

            return redirect()->route('stego.index')
                ->with('success', "Document encoded and hidden in " . count($carrierPaths) . " carrier(s).");
        } catch (\Exception $e) {
            return back()->withErrors(['encode' => 'Encoding failed: ' . $e->getMessage()]);
        } finally {
            foreach ($carrierPaths as $p) {
                if (file_exists($p)) @unlink($p);
            }
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
            'master_key'        => ['required', 'string', 'min:8'],
        ]);

        $user = Auth::user();

        // Verify ownership
        $stegoDoc = StegoDocument::where('user_id', $user->id)
            ->where('id', $request->stego_document_id)
            ->with('document')
            ->firstOrFail();

        try {
            $plaintext = $this->stegoService->decode(
                $request->stego_document_id,
                $request->master_key,
                $user->id
            );

            $filename = ($stegoDoc->document->name ?? 'decoded') . '.' . ($stegoDoc->document->extension ?? 'bin');

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
