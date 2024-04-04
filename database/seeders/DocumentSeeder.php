<?php

namespace Database\Seeders;

use App\Models\Tag;
use App\Models\Folder;
use App\Models\Document;
use Illuminate\Support\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class DocumentSeeder extends Seeder
{


    // public function run()
    // {
    //     $rootDirectory = public_path('documents');
    //     $directories = ['folder1', 'folder2', 'folder3'];

    //     // Create directories if they don't exist
    //     foreach ($directories as $directory) {
    //         $folderPath = $rootDirectory . '/' . $directory;

    //         if (!File::exists($folderPath)) {
    //             File::makeDirectory($folderPath, 0777, true);
    //         }
    //     }

    //     // Get files from the documents directory
    //     $directoryFiles = File::allFiles($rootDirectory);

    //     // Shuffle files and limit to 30
    //     shuffle($directoryFiles);
    //     $files = array_slice($directoryFiles, 0, 30);

    //     foreach ($files as $file) {
    //         $name = pathinfo($file->getFilename(), PATHINFO_FILENAME);
    //         $filePath = 'documents/' . $file->getFilename();
    //         $size = $file->getSize();
    //         $extension = $file->getExtension();
    //         $folder = pathinfo($file->getPath(), PATHINFO_FILENAME);
    //         $visibility = 'public';
    //         $share = true;
    //         $download = true;
    //         $email = 'example@example.com';
    //         $url = 'https://example.com';
    //         $contact = 'phone';
    //         $owner = 'admin';
    //         $tags = 'tag1,tag2,tag3';
    //         $date = now();
    //         $emojies = '😊😍🎉';

    //         $document = new Document();
    //         $document->name = $name;
    //         $document->file_path = $filePath;
    //         $document->size = $size;
    //         $document->extension = $extension;
    //         $document->folder = $folder;
    //         $document->visibility = $visibility;
    //         $document->share = $share;
    //         $document->download = $download;
    //         $document->email = $email;
    //         $document->url = $url;
    //         $document->contact = $contact;
    //         $document->owner = $owner;
    //         $document->tags = $tags;
    //         $document->date = $date;
    //         $document->emojies = $emojies;

    //         $document->save();
    //     }
    // }

    public function run()
    {
        // Get folders and tags
        $folders = Folder::all();
        $tags = Tag::all();

        // Path to the directory containing document files
        $directory = public_path('documents');

        // Get all files from the directory
        // $files = scandir($directory);
        $files = File::allFiles($directory);

        // Remove "." and ".." from the file list
        // $files = array_diff($files, ['.', '..']);

        // Shuffle the file array
        shuffle($files);

        // Limit to 30 files
        $files = array_slice($files, 0, 30);

        // $files = array_slice($directoryFiles, 0, 30);

        // Iterate through each file and create a corresponding record in the database
        foreach ($files as $file) {
            // Get file information
            $name = pathinfo($file, PATHINFO_FILENAME);
            $extension = pathinfo($file, PATHINFO_EXTENSION);
            $filePath = 'documents/' . $file;
            $size = $file->getSize();
            $folderId = $folders->random()->id; // Assign random folder
            $tagsArray = $tags->random(2)->pluck('name')->toArray(); // Assign two random tags
            $visibility = rand(0, 1) ? 'public' : 'private';
            $share = rand(0, 1);
            $download = rand(0, 1);
            $email = 'example@example.com';
            $url = 'https://example.com';
            $contact = 'phone';
            $owner = 'admin';
            $tagsString = implode(',', $tagsArray);
            $date = Carbon::now();
            $emojies = '😊😍🎉';

            // Ensure name is unique and not empty
            $i = 1;
            $originalName = $name;
            while (Document::where('name', $name)->exists() || empty($name)) {
                $name = $originalName . '_' . $i;
                $i++;
            }

            // Create document record in the database
            Document::create([
                'name' => $name,
                'file_path' => $filePath,
                'size' => $size,
                'extension' => $extension,
                'folder' => $folderId,
                'visibility' => $visibility,
                'share' => $share,
                'download' => $download,
                'email' => $email,
                'url' => $url,
                'contact' => $contact,
                'owner' => $owner,
                'tags' => $tagsString,
                'date' => $date,
                'emojies' => $emojies,
            ]);
        }
    }
}