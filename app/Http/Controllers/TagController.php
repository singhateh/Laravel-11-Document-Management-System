<?php

namespace App\Http\Controllers;

use App\Models\Tag;
use Illuminate\Http\Request;
use App\Http\Requests\StoreTagRequest;
use App\Http\Requests\UpdateTagRequest;
use App\Models\Category;
use App\Models\Folder;

class TagController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {

        $folders = Folder::with('categories', 'subfolders')->get();

        return view('tags.index', compact('folders'));
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
    public function store(StoreTagRequest $request)
    {

        if (!empty($request->parent_id)) {
            $folder = Folder::firstOrCreate(['name' => $request->folder_name, 'parent_id' => $request->parent_id]);
        } else {
            $folder = Folder::firstOrCreate(['name' => $request->folder_name]);
        }

        $category = Category::create(['name' => $request->category_name]);


        $folder->categories()->attach($category);

        if ($request->tags) {
            foreach ($request->tags as $tagName) {
                $tag = Tag::firstOrCreate(['name' => $tagName]);
                $category->tags()->attach($tag);
            }
        }

        $folder->load('categories');
        $categories = $folder->categories;

        $view = view('folders.tags', compact('categories'))->render();

        return response()->json(['html' => $view]);
    }

    /**
     * Display the specified resource.
     */
    public function show(Tag $tag)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Tag $tag)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateTagRequest $request, Tag $tag)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Tag $tag)
    {
        //
    }

    // Controller logic for searching tags
    public function searchTags(Request $request)
    {
        $query = $request->input('query');
        $tags = Tag::where('name', 'like', "%$query%")->get();
        return response()->json(['tags' => $tags]);
    }

    // Controller logic for adding tags
    public function addTag(Request $request)
    {
        $tagId = $request->input('tag_id');
        // Logic to add the tag to the database
        // You may also perform validation and other checks here
        return response()->json(['message' => 'Tag added successfully']);
    }
}