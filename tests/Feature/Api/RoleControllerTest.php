<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\RoleGrant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleControllerTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function authenticated_user_can_list_roles()
    {
        $user = User::factory()->create(['role' => 'user']);

        $response = $this->actingAs($user)
            ->getJson('/api/roles');

        $response->assertStatus(200)
            ->assertJson(['admin', 'owner', 'user']);
    }

    /** @test */
    public function guest_cannot_list_roles()
    {
        $response = $this->getJson('/api/roles');

        $response->assertStatus(401);
    }

    /** @test */
    public function user_can_get_role_permissions()
    {
        $user = User::factory()->create(['role' => 'user']);

        $response = $this->actingAs($user)
            ->getJson('/api/roles/user/permissions');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'document',
                'folder',
                'comment',
                'notification',
                'file_request',
                'share',
                'stego_document',
                'profile',
            ]);
    }

    /** @test */
    public function guest_cannot_get_role_permissions()
    {
        $response = $this->getJson('/api/roles/user/permissions');

        $response->assertStatus(401);
    }

    /** @test */
    public function admin_role_has_all_permissions()
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)
            ->getJson('/api/roles/admin/permissions');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'user',
                'document',
                'folder',
                'category',
                'tag',
                'comment',
                'notification',
                'file_request',
                'share',
                'stego_document',
                'system',
                'team',
                'profile',
            ]);
    }

    /** @test */
    public function owner_role_has_specific_permissions()
    {
        $owner = User::factory()->create(['role' => 'owner']);

        $response = $this->actingAs($owner)
            ->getJson('/api/roles/owner/permissions');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'user',
                'document',
                'folder',
                'category',
                'tag',
                'comment',
                'notification',
                'file_request',
                'share',
                'stego_document',
                'team',
                'profile',
            ]);
    }
}
