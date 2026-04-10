<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\FileRequest;
use App\Jobs\SendFileRequestEmail;
use Illuminate\Support\Facades\DB;
use App\Http\Requests\StoreFileRequestRequest;
use App\Http\Requests\UpdateFileRequestRequest;

class FileRequestController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreFileRequestRequest $request)
    {
        $validated = $request->validated();

        try {
            $requestFile = DB::transaction(function () use ($validated) {
                $createdRequest = FileRequest::create($validated);

                Document::create([
                    'name' => $createdRequest->name,
                    'original_name' => $createdRequest->name,
                    'folder_id' => $createdRequest->folder_id,
                    'document_date' => $createdRequest->created_at,
                    'file_path' => 'img/empty-upload.jpg',
                    'extension' => 'jpg',
                ]);

                SendFileRequestEmail::dispatch($createdRequest);

                return $createdRequest;
            });

            return response()->json(['url' => route('getFiles', $requestFile->folder_id)], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Unable to create file request at this time.',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(FileRequest $fileRequest)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(FileRequest $fileRequest)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateFileRequestRequest $request, FileRequest $fileRequest)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(FileRequest $fileRequest)
    {
        //
    }
}