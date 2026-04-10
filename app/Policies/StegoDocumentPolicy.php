<?php

namespace App\Policies;

use App\Models\StegoDocument;
use App\Models\StegoDocumentGrant;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class StegoDocumentPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, StegoDocument $stegoDocument): bool
    {
        // Admin can view all stego documents
        if ($user->isAdmin()) {
            return true;
        }
        
        // Owner can view their own stego documents
        if ($stegoDocument->user_id === $user->id) {
            return true;
        }
        
        // Check if user has been granted access
        return StegoDocumentGrant::where('stego_document_id', $stegoDocument->id)
            ->where('viewer_user_id', $user->id)
            ->exists();
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, StegoDocument $stegoDocument): bool
    {
        // Admin can update all stego documents
        if ($user->isAdmin()) {
            return true;
        }
        
        // Owner can update their own stego documents
        return $stegoDocument->user_id === $user->id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, StegoDocument $stegoDocument): bool
    {
        // Admin can delete all stego documents
        if ($user->isAdmin()) {
            return true;
        }
        
        // Owner can delete their own stego documents
        return $stegoDocument->user_id === $user->id;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, StegoDocument $stegoDocument): bool
    {
        // Admin can restore all stego documents
        if ($user->isAdmin()) {
            return true;
        }
        
        // Owner can restore their own stego documents
        return $stegoDocument->user_id === $user->id;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, StegoDocument $stegoDocument): bool
    {
        // Admin can force delete all stego documents
        if ($user->isAdmin()) {
            return true;
        }
        
        // Owner can force delete their own stego documents
        return $stegoDocument->user_id === $user->id;
    }

    /**
     * Determine whether the user can decode the model.
     */
    public function decode(User $user, StegoDocument $stegoDocument): bool
    {
        return $this->view($user, $stegoDocument);
    }

    /**
     * Determine whether the user can grant access to the model.
     */
    public function grant(User $user, StegoDocument $stegoDocument): bool
    {
        // Admin can grant access to all stego documents
        if ($user->isAdmin()) {
            return true;
        }
        
        // Owner can grant access to their own stego documents
        return $stegoDocument->user_id === $user->id;
    }

    /**
     * Determine whether the user can revoke access to the model.
     */
    public function revokeGrant(User $user, StegoDocument $stegoDocument): bool
    {
        return $this->grant($user, $stegoDocument);
    }
}
