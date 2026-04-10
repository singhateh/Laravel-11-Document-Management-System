<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /**
     * Get all users with their roles
     */
    public function index(): JsonResponse
    {
        $user = Auth::user();
        
        // Only admins and owners can list all users
        if (!$user->isAdmin() && !$user->isOwner()) {
            return response()->json(['message' => 'Forbidden: You do not have permission to list users.'], 403);
        }

        $users = User::select('id', 'name', 'email', 'username', 'role', 'created_at')
            ->orderBy('name')
            ->get()
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'username' => $user->username,
                    'role' => $user->role,
                    'created_at' => $user->created_at?->toISOString(),
                ];
            });

        return response()->json($users);
    }

    /**
     * Update a user's role
     */
    public function updateRole(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'role' => ['required', 'string', Rule::in(['admin', 'owner', 'user'])],
        ]);

        $user = User::findOrFail($id);
        $currentUser = Auth::user();

        // Only admins can update roles to admin
        if ($request->role === 'admin' && !$currentUser->isAdmin()) {
            return response()->json(['message' => 'Forbidden: Only admins can create admin users.'], 403);
        }

        // Only admins or owners can update other users' roles
        if ($user->id !== $currentUser->id && !$currentUser->isAdmin() && !$currentUser->isOwner()) {
            return response()->json(['message' => 'Forbidden: You do not have permission to update this user\'s role.'], 403);
        }

        $user->role = $request->role;
        $user->save();

        return response()->json([
            'message' => 'User role updated successfully.',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ],
        ]);
    }
}
