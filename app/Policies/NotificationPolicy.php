<?php

namespace App\Policies;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class NotificationPolicy
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
    public function view(User $user, Notification $notification): bool
    {
        // Admin can view all notifications
        if ($user->isAdmin()) {
            return true;
        }

        // Notification policy: only the notifiable user can view their notifications
        return $notification->notifiable_id == $user->id;
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
    public function update(User $user, Notification $notification): bool
    {
        // Admin can update all notifications
        if ($user->isAdmin()) {
            return true;
        }

        return $notification->notifiable_id == $user->id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Notification $notification): bool
    {
        // Admin can delete all notifications
        if ($user->isAdmin()) {
            return true;
        }

        return $notification->notifiable_id == $user->id;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Notification $notification): bool
    {
        // Admin can restore all notifications
        if ($user->isAdmin()) {
            return true;
        }

        return $notification->notifiable_id == $user->id;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Notification $notification): bool
    {
        // Admin can force delete all notifications
        if ($user->isAdmin()) {
            return true;
        }

        return $notification->notifiable_id == $user->id;
    }
}
