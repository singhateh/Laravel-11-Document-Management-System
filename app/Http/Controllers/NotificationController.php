<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Http\Requests\StoreNotificationRequest;
use App\Http\Requests\UpdateNotificationRequest;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class NotificationController extends Controller
{

    public function fetchNotifications()
    {
        $cacheKey = 'notifications';

        // Check if notifications exist in cache
        if (Cache::has($cacheKey)) {
            $notifications = Cache::get($cacheKey);
        } else {
            // Fetch notifications from the database if not found in cache
            $notifications = Notification::where('dismiss_status', 'UNDISMISSED')
                ->where('status', 'UNREAD')
                ->latest()->limit(10)->get();

            // Store notifications in cache
            Cache::put($cacheKey, $notifications, now()->addMinutes(5)); // Adjust expiration time as needed
        }

        session()->put('notifications', $notifications);

        // Render notifications view
        $view = view('notifications.fetch', compact('notifications'))->render();

        return response()->json(['html' => $view, 'count' => count($notifications)]);
    }


    public function dismiss(Request $request, Notification $notification)
    {
        $authUser = $request->authUser;

        // Check if authUser and roles are set
        if (!isset($authUser['roles'][0]['name']) || !isset($authUser['id'])) {
            return response()->json(['message' => 'Invalid user data'], 400);
        }

        $isCustomer = $authUser['roles'][0]['name'] === 'customer';

        $query = Notification::where($isCustomer ? 'user_id' : 'created_by_id', $authUser['id'])
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
            $cacheKey = 'dashboard_data_' . $authUser['id'];
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


    function store(Request $request)
    {

        $request->validate([
            'customer_id' => 'required|uuid',
            'message' => 'required|string',
        ]);


        $notification =  Notification::create([
            'user_id' => $request->customer_id,
            'user_type' => User::class,
            'activity_type' => 'note_added',
            'model_type' => User::class,
            'model_id' => $request->customer_id,
            'message' => $request->message,
        ]);

        $data = $notification->customer?->recentNotifications;
        $customer = $notification->customer;
        $image = false;

        $view = view('notifications.list', compact('data', 'customer', 'image'))->render();

        return response()->json(['html' => $view, 'message' => 'Notification added successfully']);
    }

    public function show(Request $request, Notification $notification)
    {

        // try {
        // Update the dismiss status for the current notification
        $notification->update([
            'status' => 'READ', // or 'READ'
        ]);

        return view('notifications.show', compact('notification'));
        // } catch (\Exception $e) {
        //     return response()->json(['message' => 'An error occurred while processing the request'], 500);
        // }
    }
}