<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Document;
use App\Models\Folder;
use App\Models\StegoDocument;
use App\Models\Tag;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

/**
 * DashboardController (API)
 *
 * Supplies aggregate counts and recent activity data consumed by the SPA
 * dashboard. Extracted from route closures in routes/api.php to centralise
 * query logic in controllers and keep route files as thin declarations (DRY / SRP).
 *
 * Routes (registered in routes/api.php):
 *   GET /api/dashboard/stats   — aggregate counts across all resources
 *   GET /api/dashboard/recent  — 8 most recently uploaded documents
 */
class DashboardController extends Controller
{
    // -------------------------------------------------------------------------
    // GET /api/dashboard/stats
    // -------------------------------------------------------------------------

    /**
     * Return aggregate counts for the dashboard summary cards.
     *
     * Returns total documents, folders, categories, tags, and the authenticated
     * user's own stego document count.
     *
     * @return JsonResponse
     */
    public function stats(): JsonResponse
    {
        $userId = Auth::id();

        return response()->json([
            'documents'       => Document::count(),
            'folders'         => Folder::count(),
            'categories'      => Category::count(),
            'tags'            => Tag::count(),
            'stego_documents' => StegoDocument::where('user_id', $userId)->count(),
        ]);
    }

    // -------------------------------------------------------------------------
    // GET /api/dashboard/recent
    // -------------------------------------------------------------------------

    /**
     * Return the 8 most recently uploaded documents with their tags and a
     * boolean flag indicating whether each document has been stego-encoded.
     *
     * @return JsonResponse
     */
    public function recent(): JsonResponse
    {
        $docs = Document::with('tags:id,name')
            ->withExists('stegoDocument as is_stegoed')
            ->latest()
            ->take(8)
            ->get(['id', 'name', 'extension', 'size', 'created_at']);

        return response()->json($docs);
    }
}
