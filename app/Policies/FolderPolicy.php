<?php

namespace App\Policies;

use App\Models\Folder;
use App\Models\User;
use App\Models\ShareDocument;
use Illuminate\Auth\Access\Response;

class FolderPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        // All authenticated users can view folders they have access to
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Folder $folder): bool
    {
        // Admin can view all folders
        if ($user->isAdmin()) {
            return true;
        }
        
        // Owner role can view all non-private folders
        if ($user->isOwner() && $folder->visibility !== 'private') {
            return true;
        }
        
        // Check if user has been shared the folder
        if (ShareDocument::where('share_id', $folder->id)
            ->where('user_id', $user->id)
            ->exists()
        ) {
            return true;
        }
        
        // Check if folder is public
        if ($folder->visibility === 'public') {
            return true;
        }
        
        // For private folders, we need to check if the user owns any documents in the folder
        $documents = $folder->documents;
        foreach ($documents as $document) {
            if ($document->owner_id === $user->id) {
                return true;
            }
        }
        
        // Check subfolders recursively
        foreach ($folder->subfolders as $subfolder) {
            if ($this->view($user, $subfolder)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // All authenticated users can create folders
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Folder $folder): bool
    {
        // Admin can update all folders
        if ($user->isAdmin()) {
            return true;
        }
        
        // Check if user has edit permission via share
        $share = ShareDocument::where('share_id', $folder->id)
            ->where('user_id', $user->id)
            ->first();
            
        if ($share && $share->can_edit) {
            return true;
        }
        
        // For private folders, check if user owns any documents in the folder
        $documents = $folder->documents;
        foreach ($documents as $document) {
            if ($document->owner_id === $user->id) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Folder $folder): bool
    {
        // Admin can delete all folders
        if ($user->isAdmin()) {
            return true;
        }
        
        // For private folders, check if user owns any documents in the folder
        $documents = $folder->documents;
        foreach ($documents as $document) {
            if ($document->owner_id === $user->id) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Folder $folder): bool
    {
        // Only admin can restore
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Folder $folder): bool
    {
        // Only admin can force delete
        return $user->isAdmin();
    }
}
