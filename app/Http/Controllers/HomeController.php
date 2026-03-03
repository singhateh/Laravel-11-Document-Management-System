<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\Folder;
use App\Models\Category;
use App\Models\Tag;
use App\Models\StegoDocument;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class HomeController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        $user = Auth::user();

        $stats = [
            'documents'    => Document::count(),
            'folders'      => Folder::count(),
            'categories'   => Category::count(),
            'tags'         => Tag::count(),
            'stego_docs'   => StegoDocument::where('user_id', $user->id)->count(),
        ];

        $recentDocuments = Document::with('tags')
            ->latest()
            ->take(8)
            ->get()
            ->map(fn ($d) => [
                'id'         => $d->id,
                'name'       => $d->name,
                'extension'  => $d->extension,
                'size'       => $d->size,
                'created_at' => $d->created_at?->toISOString(),
                'is_stegoed' => $d->isStegoed(),
                'tags'       => $d->tags->map(fn ($t) => ['id' => $t->id, 'name' => $t->name]),
            ]);

        return Inertia::render('Dashboard', [
            'stats'           => $stats,
            'recentDocuments' => $recentDocuments,
        ]);
    }
}
