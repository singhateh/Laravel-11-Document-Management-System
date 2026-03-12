<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';

// Create artisan application
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Comment;
use App\Models\Document;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

echo "=== Document Visibility Test ===\n\n";

// Cleanup previous test data
echo "Cleaning up previous test data...\n";
DB::statement('SET FOREIGN_KEY_CHECKS=0;');
Comment::truncate();
Document::truncate();
User::truncate();
DB::statement('SET FOREIGN_KEY_CHECKS=1;');

// Create admin user
echo "\nCreating admin user...\n";
$admin = User::factory()->create(['role' => 'admin']);

// Create regular user
echo "Creating regular user...\n";
$user = User::factory()->create(['role' => 'user']);

// Create documents with different visibility
echo "\nCreating test documents...\n";
$publicDoc = Document::factory()->create([
    'visibility' => 'public',
    'owner_id' => $user->id
]);
echo "- Public document created: #{$publicDoc->id}\n";

$privateDoc1 = Document::factory()->create([
    'visibility' => 'private',
    'owner_id' => $user->id
]);
echo "- Private document (user) created: #{$privateDoc1->id}\n";

$privateDoc2 = Document::factory()->create([
    'visibility' => 'private',
    'owner_id' => $admin->id
]);
echo "- Private document (admin) created: #{$privateDoc2->id}\n";

// Test 1: Admin visibility
echo "\n=== Test 1: Admin visibility ===\n";
Auth::login($admin);

$documents = Document::with('tags')
    ->where(function ($query) use ($admin) {
        $query->where('visibility', 'public')
            ->orWhere('owner_id', $admin->id)
            ->orWhereIn('id', function ($subquery) use ($admin) {
                $subquery->select('share_id')
                    ->from('share_documents')
                    ->where('user_id', $admin->id);
            });
            
        if ($admin->isAdmin()) {
            $query->orWhere('visibility', 'private');
        }
        
        if ($admin->isOwner()) {
            $query->orWhere(function ($q) {
                $q->where('visibility', '!=', 'private');
            });
        }
    })
    ->latest()
    ->get();

$docIds = $documents->pluck('id');
echo "Documents visible to admin: " . $docIds->join(', ') . "\n";

// Verify admin sees all documents
if ($docIds->contains($publicDoc->id) && $docIds->contains($privateDoc1->id) && $docIds->contains($privateDoc2->id)) {
    echo "✓ Admin can see all documents\n";
} else {
    echo "✗ Admin cannot see all documents\n";
}

// Test 2: User visibility
echo "\n=== Test 2: User visibility ===\n";
Auth::login($user);

$documents = Document::with('tags')
    ->where(function ($query) use ($user) {
        $query->where('visibility', 'public')
            ->orWhere('owner_id', $user->id)
            ->orWhereIn('id', function ($subquery) use ($user) {
                $subquery->select('share_id')
                    ->from('share_documents')
                    ->where('user_id', $user->id);
            });
            
        if ($user->isAdmin()) {
            $query->orWhere('visibility', 'private');
        }
        
        if ($user->isOwner()) {
            $query->orWhere(function ($q) {
                $q->where('visibility', '!=', 'private');
            });
        }
    })
    ->latest()
    ->get();

$docIds = $documents->pluck('id');
echo "Documents visible to user: " . $docIds->join(', ') . "\n";

// Verify user sees public and own private document
if ($docIds->contains($publicDoc->id) && $docIds->contains($privateDoc1->id) && !$docIds->contains($privateDoc2->id)) {
    echo "✓ User can see public and own private document\n";
} else {
    echo "✗ User visibility is incorrect\n";
}

// Cleanup
echo "\nCleaning up test data...\n";
DB::statement('SET FOREIGN_KEY_CHECKS=0;');
Comment::truncate();
Document::truncate();
User::truncate();
DB::statement('SET FOREIGN_KEY_CHECKS=1;');

echo "\n=== Test complete! ===\n";
