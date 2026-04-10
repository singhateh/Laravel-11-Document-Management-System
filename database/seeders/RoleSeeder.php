<?php

namespace Database\Seeders;

use App\Models\RoleGrant;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Define the roles and their corresponding permissions
     */
    protected array $rolePermissions = [
        'admin' => [
            // User management
            ['permission' => 'create_user', 'resource' => 'user'],
            ['permission' => 'read_user', 'resource' => 'user'],
            ['permission' => 'update_user', 'resource' => 'user'],
            ['permission' => 'delete_user', 'resource' => 'user'],
            
            // Document management
            ['permission' => 'create_document', 'resource' => 'document'],
            ['permission' => 'read_document', 'resource' => 'document'],
            ['permission' => 'update_document', 'resource' => 'document'],
            ['permission' => 'delete_document', 'resource' => 'document'],
            
            // Folder management
            ['permission' => 'create_folder', 'resource' => 'folder'],
            ['permission' => 'read_folder', 'resource' => 'folder'],
            ['permission' => 'update_folder', 'resource' => 'folder'],
            ['permission' => 'delete_folder', 'resource' => 'folder'],
            
            // Category management
            ['permission' => 'create_category', 'resource' => 'category'],
            ['permission' => 'read_category', 'resource' => 'category'],
            ['permission' => 'update_category', 'resource' => 'category'],
            ['permission' => 'delete_category', 'resource' => 'category'],
            
            // Tag management
            ['permission' => 'create_tag', 'resource' => 'tag'],
            ['permission' => 'read_tag', 'resource' => 'tag'],
            ['permission' => 'update_tag', 'resource' => 'tag'],
            ['permission' => 'delete_tag', 'resource' => 'tag'],
            
            // Comment management
            ['permission' => 'create_comment', 'resource' => 'comment'],
            ['permission' => 'read_comment', 'resource' => 'comment'],
            ['permission' => 'update_comment', 'resource' => 'comment'],
            ['permission' => 'delete_comment', 'resource' => 'comment'],
            
            // Notification management
            ['permission' => 'create_notification', 'resource' => 'notification'],
            ['permission' => 'read_notification', 'resource' => 'notification'],
            ['permission' => 'update_notification', 'resource' => 'notification'],
            ['permission' => 'delete_notification', 'resource' => 'notification'],
            
            // File request management
            ['permission' => 'create_file_request', 'resource' => 'file_request'],
            ['permission' => 'read_file_request', 'resource' => 'file_request'],
            ['permission' => 'update_file_request', 'resource' => 'file_request'],
            ['permission' => 'delete_file_request', 'resource' => 'file_request'],
            
            // Share management
            ['permission' => 'create_share', 'resource' => 'share'],
            ['permission' => 'read_share', 'resource' => 'share'],
            ['permission' => 'update_share', 'resource' => 'share'],
            ['permission' => 'delete_share', 'resource' => 'share'],
            
            // Stego document management
            ['permission' => 'create_stego_document', 'resource' => 'stego_document'],
            ['permission' => 'read_stego_document', 'resource' => 'stego_document'],
            ['permission' => 'update_stego_document', 'resource' => 'stego_document'],
            ['permission' => 'delete_stego_document', 'resource' => 'stego_document'],
            
            // System management
            ['permission' => 'manage_system', 'resource' => 'system'],
            ['permission' => 'view_logs', 'resource' => 'system'],
            ['permission' => 'configure_settings', 'resource' => 'system'],
        ],
        'owner' => [
            // User management (own team only)
            ['permission' => 'read_user', 'resource' => 'user'],
            ['permission' => 'update_user', 'resource' => 'user'],
            
            // Document management
            ['permission' => 'create_document', 'resource' => 'document'],
            ['permission' => 'read_document', 'resource' => 'document'],
            ['permission' => 'update_document', 'resource' => 'document'],
            ['permission' => 'delete_document', 'resource' => 'document'],
            
            // Folder management
            ['permission' => 'create_folder', 'resource' => 'folder'],
            ['permission' => 'read_folder', 'resource' => 'folder'],
            ['permission' => 'update_folder', 'resource' => 'folder'],
            ['permission' => 'delete_folder', 'resource' => 'folder'],
            
            // Category management
            ['permission' => 'create_category', 'resource' => 'category'],
            ['permission' => 'read_category', 'resource' => 'category'],
            ['permission' => 'update_category', 'resource' => 'category'],
            ['permission' => 'delete_category', 'resource' => 'category'],
            
            // Tag management
            ['permission' => 'create_tag', 'resource' => 'tag'],
            ['permission' => 'read_tag', 'resource' => 'tag'],
            ['permission' => 'update_tag', 'resource' => 'tag'],
            ['permission' => 'delete_tag', 'resource' => 'tag'],
            
            // Comment management
            ['permission' => 'create_comment', 'resource' => 'comment'],
            ['permission' => 'read_comment', 'resource' => 'comment'],
            ['permission' => 'update_comment', 'resource' => 'comment'],
            ['permission' => 'delete_comment', 'resource' => 'comment'],
            
            // Notification management
            ['permission' => 'create_notification', 'resource' => 'notification'],
            ['permission' => 'read_notification', 'resource' => 'notification'],
            ['permission' => 'update_notification', 'resource' => 'notification'],
            ['permission' => 'delete_notification', 'resource' => 'notification'],
            
            // File request management
            ['permission' => 'create_file_request', 'resource' => 'file_request'],
            ['permission' => 'read_file_request', 'resource' => 'file_request'],
            ['permission' => 'update_file_request', 'resource' => 'file_request'],
            ['permission' => 'delete_file_request', 'resource' => 'file_request'],
            
            // Share management
            ['permission' => 'create_share', 'resource' => 'share'],
            ['permission' => 'read_share', 'resource' => 'share'],
            ['permission' => 'update_share', 'resource' => 'share'],
            ['permission' => 'delete_share', 'resource' => 'share'],
            
            // Stego document management
            ['permission' => 'create_stego_document', 'resource' => 'stego_document'],
            ['permission' => 'read_stego_document', 'resource' => 'stego_document'],
            ['permission' => 'update_stego_document', 'resource' => 'stego_document'],
            ['permission' => 'delete_stego_document', 'resource' => 'stego_document'],
            
            // Team management
            ['permission' => 'manage_team', 'resource' => 'team'],
            ['permission' => 'invite_user', 'resource' => 'team'],
            ['permission' => 'remove_user', 'resource' => 'team'],
        ],
        'user' => [
            // Document management (own documents)
            ['permission' => 'create_document', 'resource' => 'document'],
            ['permission' => 'read_document', 'resource' => 'document'],
            ['permission' => 'update_document', 'resource' => 'document'],
            ['permission' => 'delete_document', 'resource' => 'document'],
            
            // Folder management (own folders)
            ['permission' => 'create_folder', 'resource' => 'folder'],
            ['permission' => 'read_folder', 'resource' => 'folder'],
            ['permission' => 'update_folder', 'resource' => 'folder'],
            ['permission' => 'delete_folder', 'resource' => 'folder'],
            
            // Comment management
            ['permission' => 'create_comment', 'resource' => 'comment'],
            ['permission' => 'read_comment', 'resource' => 'comment'],
            ['permission' => 'update_comment', 'resource' => 'comment'],
            ['permission' => 'delete_comment', 'resource' => 'comment'],
            
            // Notification management
            ['permission' => 'read_notification', 'resource' => 'notification'],
            ['permission' => 'update_notification', 'resource' => 'notification'],
            
            // File request management
            ['permission' => 'create_file_request', 'resource' => 'file_request'],
            ['permission' => 'read_file_request', 'resource' => 'file_request'],
            
            // Share management (own documents)
            ['permission' => 'create_share', 'resource' => 'share'],
            ['permission' => 'read_share', 'resource' => 'share'],
            ['permission' => 'update_share', 'resource' => 'share'],
            ['permission' => 'delete_share', 'resource' => 'share'],
            
            // Stego document management (own documents)
            ['permission' => 'create_stego_document', 'resource' => 'stego_document'],
            ['permission' => 'read_stego_document', 'resource' => 'stego_document'],
            ['permission' => 'update_stego_document', 'resource' => 'stego_document'],
            ['permission' => 'delete_stego_document', 'resource' => 'stego_document'],
            
            // User profile
            ['permission' => 'update_profile', 'resource' => 'profile'],
            ['permission' => 'change_password', 'resource' => 'profile'],
        ],
    ];

    /**
     * Seed the role grants table
     */
    public function run(): void
    {
        // Clear existing role grants
        RoleGrant::truncate();
        
        $roleGrants = [];
        $now = now();
        
        foreach ($this->rolePermissions as $role => $permissions) {
            foreach ($permissions as $permission) {
                $roleGrants[] = [
                    'role' => $role,
                    'permission' => $permission['permission'],
                    'resource' => $permission['resource'],
                    'user_id' => null,
                    'is_granted' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }
        
        // Insert all role grants at once
        RoleGrant::insert($roleGrants);
        
        $this->command->info('Role seeding completed successfully');
        $this->command->line('Roles created: admin, owner, user');
        $this->command->line('Total permissions assigned: ' . count($roleGrants));
    }
}
