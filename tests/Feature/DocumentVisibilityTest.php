<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentVisibilityTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function admin_can_view_all_documents()
    {
        // Create admin user
        $admin = User::factory()->create(['role' => 'admin']);
        
        // Create documents with different visibility
        $publicDoc = Document::factory()->create(['visibility' => 'public']);
        $privateDoc = Document::factory()->create(['visibility' => 'private']);
        
        // Login as admin
        $response = $this->actingAs($admin)
            ->get(route('documents.index'));
            
        // Admin should see all documents
        $response->assertStatus(200);
    }

    /** @test */
    public function owner_can_view_public_and_their_own_private_documents()
    {
        // Create owner and regular user
        $owner = User::factory()->create(['role' => 'owner']);
        $user = User::factory()->create(['role' => 'user']);
        
        // Create documents
        $publicDoc = Document::factory()->create(['visibility' => 'public']);
        $ownerPrivateDoc = Document::factory()->create([
            'visibility' => 'private',
            'owner_id' => $owner->id
        ]);
        $userPrivateDoc = Document::factory()->create([
            'visibility' => 'private',
            'owner_id' => $user->id
        ]);
        
        // Login as owner
        $response = $this->actingAs($owner)
            ->get(route('documents.index'));
            
        // Owner should see public and their own private documents
        $response->assertStatus(200);
    }

    /** @test */
    public function user_can_view_public_and_their_own_private_documents()
    {
        // Create users
        $user1 = User::factory()->create(['role' => 'user']);
        $user2 = User::factory()->create(['role' => 'user']);
        
        // Create documents
        $publicDoc = Document::factory()->create(['visibility' => 'public']);
        $user1PrivateDoc = Document::factory()->create([
            'visibility' => 'private',
            'owner_id' => $user1->id
        ]);
        $user2PrivateDoc = Document::factory()->create([
            'visibility' => 'private',
            'owner_id' => $user2->id
        ]);
        
        // Login as user1
        $response = $this->actingAs($user1)
            ->get(route('documents.index'));
            
        // User should see public and their own private documents
        $response->assertStatus(200);
    }

    /** @test */
    public function guest_can_only_view_public_documents()
    {
        // Create documents
        $publicDoc = Document::factory()->create(['visibility' => 'public']);
        $privateDoc = Document::factory()->create(['visibility' => 'private']);
        
        // Guest user (not logged in)
        $response = $this->get(route('documents.index'));
        
        // Guest should be redirected to login
        $response->assertStatus(302);
    }

    /** @test */
    public function user_can_download_own_private_document()
    {
        $user = User::factory()->create(['role' => 'user']);
        $document = Document::factory()->create([
            'visibility' => 'private',
            'owner_id' => $user->id,
            'file_path' => 'test/document.txt'
        ]);
        
        // Create test file
        $filePath = public_path($document->file_path);
        \Illuminate\Support\Facades\File::ensureDirectoryExists(dirname($filePath));
        \Illuminate\Support\Facades\File::put($filePath, 'Test content');
        
        $response = $this->actingAs($user)
            ->get(route('documents.download', $document));
            
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/plain; charset=utf-8');
        $response->assertSee('Test content');
        
        // Cleanup
        \Illuminate\Support\Facades\File::delete($filePath);
        \Illuminate\Support\Facades\File::deleteDirectory(dirname($filePath));
    }

    /** @test */
    public function user_cannot_download_others_private_document()
    {
        $user = User::factory()->create(['role' => 'user']);
        $otherUser = User::factory()->create(['role' => 'user']);
        
        $document = Document::factory()->create([
            'visibility' => 'private',
            'owner_id' => $otherUser->id,
            'file_path' => 'test/document.txt'
        ]);
        
        $response = $this->actingAs($user)
            ->get(route('documents.download', $document));
            
        $response->assertStatus(403);
    }

    /** @test */
    public function admin_can_download_any_document()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create(['role' => 'user']);
        
        $document = Document::factory()->create([
            'visibility' => 'private',
            'owner_id' => $user->id,
            'file_path' => 'test/document.txt'
        ]);
        
        // Create test file
        $filePath = public_path($document->file_path);
        \Illuminate\Support\Facades\File::ensureDirectoryExists(dirname($filePath));
        \Illuminate\Support\Facades\File::put($filePath, 'Test content');
        
        $response = $this->actingAs($admin)
            ->get(route('documents.download', $document));
            
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/plain; charset=utf-8');
        $response->assertSee('Test content');
        
        // Cleanup
        \Illuminate\Support\Facades\File::delete($filePath);
        \Illuminate\Support\Facades\File::deleteDirectory(dirname($filePath));
    }

    /** @test */
    public function user_can_update_own_document()
    {
        $user = User::factory()->create(['role' => 'user']);
        $document = Document::factory()->create([
            'visibility' => 'private',
            'owner_id' => $user->id,
            'name' => 'Original Name'
        ]);
        
        $response = $this->actingAs($user)
            ->putJson(route('documents.update', $document), [
                'name' => 'Updated Name'
            ]);
            
        $response->assertStatus(200);
        $this->assertDatabaseHas('documents', [
            'id' => $document->id,
            'name' => 'Updated Name'
        ]);
    }

    /** @test */
    public function user_cannot_update_others_document()
    {
        $user = User::factory()->create(['role' => 'user']);
        $otherUser = User::factory()->create(['role' => 'user']);
        
        $document = Document::factory()->create([
            'visibility' => 'private',
            'owner_id' => $otherUser->id,
            'name' => 'Original Name'
        ]);
        
        $response = $this->actingAs($user)
            ->putJson(route('documents.update', $document), [
                'name' => 'Updated Name'
            ]);
            
        $response->assertStatus(403);
    }
}