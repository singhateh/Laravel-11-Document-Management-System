<?php

namespace App\Providers;

use App\Models\Document;
use App\Models\Folder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        // Register short-key morphMap so polymorphic type columns store
        // stable short strings ('folder', 'document') instead of full
        // class paths that break silently on rename/move.
        Relation::morphMap([
            'folder'   => Folder::class,
            'document' => Document::class,
        ]);
    }
}
