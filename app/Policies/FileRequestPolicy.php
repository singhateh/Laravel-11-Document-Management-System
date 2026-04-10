<?php

namespace App\Policies;

use App\Models\FileRequest;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class FileRequestPolicy
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
    public function view(User $user, FileRequest $fileRequest): bool
    {
        // Admin can view all file requests
        if ($user->isAdmin()) {
            return true;
        }

        // File request policy: only the creator or the requested user can view
        // (Assuming file requests have a user_id or similar field indicating creator)
        // For now, we'll assume only creator can view
        return $fileRequest->user_id === $user->id;
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
    public function update(User $user, FileRequest $fileRequest): bool
    {
        // Admin can update all file requests
        if ($user->isAdmin()) {
            return true;
        }

        return $fileRequest->user_id === $user->id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, FileRequest $fileRequest): bool
    {
        // Admin can delete all file requests
        if ($user->isAdmin()) {
            return true;
        }

        return $fileRequest->user_id === $user->id;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, FileRequest $fileRequest): bool
    {
        // Admin can restore all file requests
        if ($user->isAdmin()) {
            return true;
        }

        return $fileRequest->user_id === $user->id;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, FileRequest $fileRequest): bool
    {
        // Admin can force delete all file requests
        if ($user->isAdmin()) {
            return true;
        }

        return $fileRequest->user_id === $user->id;
    }
}
