<?php

namespace App\Http\Controllers;

use App\Models\Folder;
use App\Models\Document;
use App\Models\StegoDocument;
use Illuminate\Http\Request;
use App\Models\ShareDocument;
use App\Http\Requests\StoreShareDocumentRequest;
use App\Http\Requests\UpdateShareDocumentRequest;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class ShareDocumentController extends Controller
{

    public function getSharedDocuments($slug, $sharedid, $token)
    {
        $shareDocument = ShareDocument::whereSlug($slug)->whereToken($token)->whereSharedId($sharedid)->first();

        abort_if(!$shareDocument, 404, 'Not Found');

        if ($slug === 'folder') {
            $folder = Folder::with(['documents', 'subfolders'])->findOrFail($sharedid);
            return Inertia::render('Shares/Folder', [
                'share' => $shareDocument,
                'folder' => $folder
            ]);
        }

        return view('shares.index', compact('shareDocument'));
    }


    public function sharedDocuments(StoreShareDocumentRequest $request)
    {
        $validated = $request->validated();

        // Generate secure token if not provided
        if (!isset($validated['token'])) {
            $validated['token'] = Str::random(40);
        }
        
        // Determine share type based on slug
        $shareType = match($request->slug) {
            'folder' => Folder::class,
            'stego' => StegoDocument::class,
            default => Document::class,
        };
        
        $shareData = $validated + [
            'share_type' => $shareType,
            'share_id' => $request->shared_id,
            'user_type' => User::class,
            'user_id' => Auth::id() ?? 1,
        ];

        $shareDocument = ShareDocument::create($shareData);

        // If permission level is specified, set it
        if (isset($validated['permission_level'])) {
            $shareDocument->setPermissionLevel($validated['permission_level']);
            $shareDocument->save();
        }

        return response()->json(['message' => 'shared successfully', 'share' => $shareDocument], 200);
    }

    public function updatePermissions(UpdateShareDocumentRequest $request, $id)
    {
        $validated = $request->validated();

        $shareDocument = ShareDocument::findOrFail($id);
        
        // Check if user has permission to update
        $this->authorize('update', $shareDocument);

        $shareDocument->setPermissionLevel($validated['permission_level']);
        $shareDocument->save();

        return response()->json(['message' => 'Permissions updated successfully', 'share' => $shareDocument], 200);
    }

    public function getSharePermissions($id)
    {
        $shareDocument = ShareDocument::findOrFail($id);
        
        return response()->json([
            'permissions' => $shareDocument->getAttributes(),
            'permission_levels' => ShareDocument::getPermissionLevels(),
        ], 200);
    }

    public function revokeShare($id)
    {
        $shareDocument = ShareDocument::findOrFail($id);
        
        // Check if user has permission to revoke
        $this->authorize('delete', $shareDocument);

        $shareDocument->delete();

        return response()->json(['message' => 'Share revoked successfully'], 200);
    }

    public function listSharedDocuments()
    {
        $user = Auth::user();
        
        $sharedDocuments = ShareDocument::where('user_id', $user->id)->get();

        return response()->json(['shared_documents' => $sharedDocuments], 200);
    }
}