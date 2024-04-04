<?php

namespace App\Http\Controllers;

use App\Models\ShareDocument;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Http\Request;

class UserController extends Controller
{

    public function search(Request $request)
    {
        $query = $request->input('q');

        // Perform a database query to search for users by username or name
        $users = User::where('email', 'like', "%$query%")
            ->orWhere('name', 'like', "%$query%")
            ->get();

        $userList = [];

        foreach ($users as $key => $value) {
            $userList[] = ['name' => Str::studly($value->name), 'email' => $value->email, 'id' => $value->id];
        }
        return response()->json(['users' => $userList]);
    }

  
}