<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserManagementController extends Controller
{
    /**
     * Display the role management page.
     */
    public function roles(): Response
    {
        $this->authorize('viewAny', User::class);

        return Inertia::render('Users/Roles');
    }
}
