<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class NotificationService
{
    /**
     * Create a login notification for the authenticated user.
     */
    public function createLoginNotification(): Notification
    {
        $user = Auth::user();
        
        return Notification::create([
            'notifiable_id' => $user->id,
            'notifiable_type' => User::class,
            'activity_type' => 'login',
            'model_type' => User::class,
            'model_id' => $user->id,
            'message' => "Welcome back, {$user->name}! You have successfully logged in.",
            'status' => 'UNREAD',
            'dismiss_status' => 'UNDISMISSED',
            'created_by_user_id' => $user->id,
        ]);
    }

    /**
     * Create a document share notification for the recipient.
     */
    public function createShareNotification(
        User $recipient,
        User $sender,
        string $shareType,
        string $shareName,
        int $shareId
    ): Notification {
        return Notification::create([
            'notifiable_id' => $recipient->id,
            'notifiable_type' => User::class,
            'activity_type' => 'document_shared',
            'model_type' => $shareType,
            'model_id' => $shareId,
            'message' => "{$sender->name} has shared a {$shareType} '{$shareName}' with you.",
            'status' => 'UNREAD',
            'dismiss_status' => 'UNDISMISSED',
            'created_by_user_id' => $sender->id,
        ]);
    }

    /**
     * Get unread notifications for the authenticated user.
     */
    public function getUnreadNotifications(int $limit = 10)
    {
        $user = Auth::user();
        
        return Notification::where('notifiable_id', $user->id)
            ->where('notifiable_type', User::class)
            ->where('status', 'UNREAD')
            ->where('dismiss_status', 'UNDISMISSED')
            ->latest()
            ->limit($limit)
            ->get();
    }

    /**
     * Mark a notification as read.
     */
    public function markAsRead(Notification $notification): void
    {
        $notification->update(['status' => 'READ']);
    }

    /**
     * Dismiss a notification.
     */
    public function dismiss(Notification $notification): void
    {
        $notification->update(['dismiss_status' => 'DISMISSED']);
    }
}
