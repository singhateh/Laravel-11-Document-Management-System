<?php

namespace App\Http\Controllers;

use App\Models\ShareDocument;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{

    public function search(Request $request)
    {
        $user = Auth::user();
        
        // Only admins and owners can list all users without a search query
        if (empty(trim($request->input('search', ''))) && empty(trim($request->input('q', '')))) {
            if (!$user->isAdmin() && !$user->isOwner()) {
                return response()->json(['message' => 'Forbidden: You do not have permission to list users.'], 403);
            }
        }

        $query = trim((string) $request->input('search', $request->input('q', '')));

        $usersQuery = User::query();

        if ($query !== '') {
            $usersQuery->where(function ($q) use ($query) {
                $q->where('email', 'like', "%{$query}%")
                    ->orWhere('name', 'like', "%{$query}%");
            });
        }

        $users = $usersQuery
            ->orderBy('name')
            ->get();

        $userList = [];

        foreach ($users as $key => $value) {
            $userList[] = [
                'name'  => Str::studly($value->name),
                'email' => $value->email,
                'id'    => $value->id,
                'role'  => $value->role,
            ];
        }

        return response()->json($userList);
    }


}