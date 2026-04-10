<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RoleGrant;
use Illuminate\Http\JsonResponse;

class RoleController extends Controller
{
    /**
     * Get all available roles
     */
    public function index(): JsonResponse
    {
        // Return predefined roles since RoleGrant may not have all roles
        $roles = ['admin', 'owner', 'user'];
        return response()->json($roles);
    }

    /**
     * Get permissions for a specific role
     */
    public function permissions(string $role): JsonResponse
    {
        // Define predefined permissions for each role (matches RoleSeeder)
        $rolePermissions = [
            'admin' => [
                'user' => ['create', 'read', 'update', 'delete'],
                'document' => ['create', 'read', 'update', 'delete'],
                'folder' => ['create', 'read', 'update', 'delete'],
                'category' => ['create', 'read', 'update', 'delete'],
                'tag' => ['create', 'read', 'update', 'delete'],
                'comment' => ['create', 'read', 'update', 'delete'],
                'notification' => ['create', 'read', 'update', 'delete'],
                'file_request' => ['create', 'read', 'update', 'delete'],
                'share' => ['create', 'read', 'update', 'delete'],
                'stego_document' => ['create', 'read', 'update', 'delete'],
                'system' => ['manage', 'view_logs', 'configure_settings'],
                'team' => ['manage', 'invite_user', 'remove_user'],
                'profile' => ['update', 'change_password'],
            ],
            'owner' => [
                'user' => ['read', 'update'],
                'document' => ['create', 'read', 'update', 'delete'],
                'folder' => ['create', 'read', 'update', 'delete'],
                'category' => ['create', 'read', 'update', 'delete'],
                'tag' => ['create', 'read', 'update', 'delete'],
                'comment' => ['create', 'read', 'update', 'delete'],
                'notification' => ['create', 'read', 'update', 'delete'],
                'file_request' => ['create', 'read', 'update', 'delete'],
                'share' => ['create', 'read', 'update', 'delete'],
                'stego_document' => ['create', 'read', 'update', 'delete'],
                'team' => ['manage', 'invite_user', 'remove_user'],
                'profile' => ['update', 'change_password'],
            ],
            'user' => [
                'document' => ['create', 'read', 'update', 'delete'],
                'folder' => ['create', 'read', 'update', 'delete'],
                'comment' => ['create', 'read', 'update', 'delete'],
                'notification' => ['read', 'update'],
                'file_request' => ['create', 'read'],
                'share' => ['create', 'read', 'update', 'delete'],
                'stego_document' => ['create', 'read', 'update', 'delete'],
                'profile' => ['update', 'change_password'],
            ],
        ];

        return response()->json($rolePermissions[$role] ?? []);
    }
}
