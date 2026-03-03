<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\Tag;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SearchController extends Controller
{
    public function index(Request $request)
    {
        $query = Document::with('tags');

        // Keyword search
        if ($request->filled('q')) {
            $keyword = $request->input('q');
            $query->where(function($q) use ($keyword) {
                $q->where('name', 'LIKE', "%{$keyword}%")
                  ->orWhere('original_name', 'LIKE', "%{$keyword}%");
            });
        }

        // File type filter
        if ($request->filled('type')) {
            $types = explode(',', $request->input('type'));
            $query->whereIn('extension', $types);
        }

        // Folder filter
        if ($request->filled('folder')) {
            $query->where('folder_id', $request->input('folder'));
        }

        // Tags filter
        if ($request->filled('tags')) {
            $tagIds = explode(',', $request->input('tags'));
            $query->whereHas('tags', function($q) use ($tagIds) {
                $q->whereIn('tags.id', $tagIds);
            });
        }

        // Date range filter
        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->input('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->input('to'));
        }

        // File size filter (in KB)
        if ($request->filled('min_size')) {
            $minBytes = $request->input('min_size') * 1024;
            $query->where('size', '>=', $minBytes);
        }
        if ($request->filled('max_size')) {
            $maxBytes = $request->input('max_size') * 1024;
            $query->where('size', '<=', $maxBytes);
        }

        // Visibility filter
        if ($request->filled('visibility')) {
            $query->where('visibility', $request->input('visibility'));
        }

        $results = $query->latest()->get();

        return Inertia::render('Search/Index', [
            'results' => $results,
            'query' => $request->input('q', ''),
            'filters' => $request->only(['type', 'folder', 'tags', 'from', 'to', 'min_size', 'max_size', 'visibility']),
        ]);
    }
}
