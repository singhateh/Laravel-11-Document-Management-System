<?php

namespace App\Http\Controllers;

use App\Models\Folder;
use App\Models\Document;
use Illuminate\Http\Request;
use App\Models\ShareDocument;
use App\Http\Requests\StoreShareDocumentRequest;
use App\Http\Requests\UpdateShareDocumentRequest;
use App\Models\User;

class ShareDocumentController extends Controller
{

    function getSharedDocuments($slug, $sharedid, $token)
    {
        $shareDocument = ShareDocument::whereSlug($slug)->whereToken($token)->whereSharedId($sharedid)->first();

        abort_if(!$shareDocument, 404, 'Not Found');

        return view('shares.index', compact('shareDocument'));
    }


    function sharedDocuments(Request $request)
    {
        $validated = $request->validate([
            'shared_id' => 'required',
            'token' => 'required',
            'slug' => 'required',
            'url' => 'required',
            'name' => 'nullable',
            'valid_until' => 'nullable',
            'visibility' => 'nullable',
        ]);

        ShareDocument::create($validated +
            [
                'share_type' => $request->slug == 'folder' ?  Folder::class : Document::class,
                'share_id' => $request->shared_id,
                'user_type' => User::class,
                'user_id' => 1,
            ]);

        return response()->json(['message' => 'shared successfully'], 200);
    }
}