<?php

namespace App\Policies;

use App\Models\ShareDocument;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ShareDocumentPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        // All authenticated users can view their own share documents
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, ShareDocument $shareDocument): bool
    {
        // Admin can view all share documents
        if ($user->isAdmin()) {
            return true;
        }

        // Owner can view their own share documents
        return $shareDocument->user_id === $user->id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // All authenticated users can create share documents
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, ShareDocument $shareDocument): bool
    {
        // Admin can update all share documents
        if ($user->isAdmin()) {
            return true;
        }

        // Owner can update their own share documents
        return $shareDocument->user_id === $user->id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ShareDocument $shareDocument): bool
    {
        // Admin can delete all share documents
        if ($user->isAdmin()) {
            return true;
        }

        // Owner can delete their own share documents
        return $shareDocument->user_id === $user->id;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, ShareDocument $shareDocument): bool
    {
        // Admin can restore all share documents
        if ($user->isAdmin()) {
            return true;
        }

        // Owner can restore their own share documents
        return $shareDocument->user_id === $user->id;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, ShareDocument $shareDocument): bool
    {
        // Admin can force delete all share documents
        if ($user->isAdmin()) {
            return true;
        }

        // Owner can force delete their own share documents
        return $shareDocument->user_id === $user->id;
    }
}
