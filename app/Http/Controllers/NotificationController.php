<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Http\Requests\StoreNotificationRequest;
use App\Http\Requests\UpdateNotificationRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class NotificationController extends Controller
{

    public function fetchNotifications()
    {
        $user = request()->user();
        
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $cacheKey = 'notifications_' . $user->id;

        // Check if notifications exist in cache
        if (Cache::has($cacheKey)) {
            $notifications = Cache::get($cacheKey);
        } else {
            // Fetch notifications from the database if not found in cache
            $notifications = Notification::where('notifiable_id', $user->id)
                ->where('notifiable_type', User::class)
                ->where('dismiss_status', 'UNDISMISSED')
                ->where('status', 'UNREAD')
                ->latest()->limit(10)->get();

            // Store notifications in cache
            Cache::put($cacheKey, $notifications, now()->addMinutes(5)); // Adjust expiration time as needed
        }

        session()->put('notifications', $notifications);

        // Return JSON response for API calls
        if (request()->expectsJson()) {
            return response()->json(['notifications' => $notifications]);
        }

        // Render notifications view
        $view = view('notifications.fetch', compact('notifications'))->render();

        return response()->json(['html' => $view, 'count' => count($notifications)]);
    }


    public function dismiss(Request $request, Notification $notification)
    {
        $user = $request->user();

        // Check if user is authenticated
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        // Verify the notification belongs to this user
        $belongsToUser =
            ($notification->notifiable_id == $user->id && $notification->notifiable_type == User::class)
            || ($notification->created_by_user_id == $user->id);

        if (!$belongsToUser) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $query = Notification::where('notifiable_id', $user->id)
            ->where('notifiable_type', User::class)
            ->where('dismiss_status', 'UNDISMISSED')
            ->where('status', 'UNREAD')
            ->latest()
            ->take(3);

        try {
            $data = $query->get();

            // Update the dismiss status for the current notification
            $notification->update([
                'dismiss_status' => 'DISMISSED', // or 'READ'
            ]);

            // Clear the cache
            $cacheKey = 'dashboard_data_' . $user->id;
            Cache::forget($cacheKey);

            // Retrieve the latest notifications again (after update)
            $data = $query->get();

            // Render the view
            $view = view('notifications.list', compact('data'))->render();

            return response()->json(['html' => $view, 'message' => 'Notification dismissed successfully']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'An error occurred while processing the request'], 500);
        }
    }


    public function store(StoreNotificationRequest $request)
    {
        $validated = $request->validated();
        $user = $request->user();

        $notification =  Notification::create([
            'notifiable_id'   => $validated['customer_id'],
            'notifiable_type' => User::class,
            'activity_type'   => 'note_added',
            'model_type'      => User::class,
            'model_id'        => $validated['customer_id'],
            'message'         => $validated['message'],
            'created_by_user_id' => $user?->id ?? 1,
        ]);

        // Fetch recent notifications for the notifiable user
        $data = Notification::where('notifiable_id', $notification->notifiable_id)
            ->where('notifiable_type', User::class)
            ->where('dismiss_status', 'UNDISMISSED')
            ->latest()
            ->limit(10)
            ->get();
        $customer = $notification->notifiable;
        $image = false;

        $view = view('notifications.list', compact('data', 'customer', 'image'))->render();

        return response()->json(['html' => $view, 'message' => 'Notification added successfully']);
    }

    public function show(Request $request, Notification $notification)
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        // Update the dismiss status for the current notification
        $notification->update([
            'status' => 'READ', // or 'READ'
        ]);

        return view('notifications.show', compact('notification'));
    }

    public function markAsRead(Request $request, Notification $notification)
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json(['message' => 'Invalid user data'], 400);
        }

        // Verify the notification belongs to this user
        $belongsToUser =
            ($notification->notifiable_id == $user->id && $notification->notifiable_type == User::class)
            || ($notification->created_by_user_id == $user->id);

        if (!$belongsToUser) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        
        try {
            $notification->update(['status' => 'READ']);

            // Clear the cache
            $cacheKey = 'notifications_' . $user->id;
            Cache::forget($cacheKey);

            return response()->json(['message' => 'Notification marked as read']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'An error occurred while processing the request'], 500);
        }
    }
}