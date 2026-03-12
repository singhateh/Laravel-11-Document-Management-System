<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\User;
use App\Models\ShareDocument;
use Illuminate\Auth\Access\Response;

class DocumentPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        // All authenticated users can view documents they have access to
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Document $document): bool
    {
        // Owner can always view
        if ($document->owner_id === $user->id) {
            return true;
        }
        
        // Admin can view all documents
        if ($user->isAdmin()) {
            return true;
        }
        
        // Check if user has been shared the document
        if (ShareDocument::where('share_id', $document->id)
            ->where('user_id', $user->id)
            ->exists()
        ) {
            return true;
        }
        
        // Check if user has access to the folder containing the document
        $folder = $document->folder;
        if ($folder && ShareDocument::where('share_id', $folder->id)
            ->where('user_id', $user->id)
            ->exists()
        ) {
            return true;
        }
        
        // Documents are private by default - only accessible to owner, admin, or shared users
        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // All users can create documents
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Document $document): bool
    {
        // Owner can always update
        if ($document->owner_id === $user->id) {
            return true;
        }
        
        // Admin can update all documents
        if ($user->isAdmin()) {
            return true;
        }
        
        // Check if user has edit permission via share
        $share = ShareDocument::where('share_id', $document->id)
            ->where('user_id', $user->id)
            ->first();
            
        if ($share && $share->can_edit) {
            return true;
        }
        
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Document $document): bool
    {
        // Owner can always delete
        if ($document->owner_id === $user->id) {
            return true;
        }
        
        // Admin can delete all documents
        if ($user->isAdmin()) {
            return true;
        }
        
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Document $document): bool
    {
        // Only admin or owner can restore
        if ($user->isAdmin() || $document->owner_id === $user->id) {
            return true;
        }
        
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Document $document): bool
    {
        // Only admin or owner can force delete
        if ($user->isAdmin() || $document->owner_id === $user->id) {
            return true;
        }
        
        return false;
    }
}
