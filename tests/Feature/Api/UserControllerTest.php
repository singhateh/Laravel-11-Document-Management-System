<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserControllerTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function admin_can_list_all_users()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        User::factory()->count(3)->create(['role' => 'user']);
        User::factory()->create(['role' => 'owner']);

        $response = $this->actingAs($admin)
            ->getJson('/api/users');

        $response->assertStatus(200)
            ->assertJsonCount(5);
    }

    /** @test */
    public function owner_can_list_all_users()
    {
        $owner = User::factory()->create(['role' => 'owner']);
        User::factory()->count(2)->create(['role' => 'user']);

        $response = $this->actingAs($owner)
            ->getJson('/api/users');

        $response->assertStatus(200)
            ->assertJsonCount(3);
    }

    /** @test */
    public function regular_user_cannot_list_users()
    {
        $user = User::factory()->create(['role' => 'user']);
        User::factory()->count(2)->create(['role' => 'user']);

        $response = $this->actingAs($user)
            ->getJson('/api/users');

        $response->assertStatus(403);
    }

    /** @test */
    public function admin_can_update_user_role()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create(['role' => 'user']);

        $response = $this->actingAs($admin)
            ->putJson("/api/users/{$user->id}/role", [
                'role' => 'owner',
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'role' => 'owner',
        ]);
    }

    /** @test */
    public function owner_can_update_user_role()
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $user = User::factory()->create(['role' => 'user']);

        $response = $this->actingAs($owner)
            ->putJson("/api/users/{$user->id}/role", [
                'role' => 'owner',
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'role' => 'owner',
        ]);
    }

    /** @test */
    public function regular_user_cannot_update_user_role()
    {
        $user = User::factory()->create(['role' => 'user']);
        $anotherUser = User::factory()->create(['role' => 'user']);

        $response = $this->actingAs($user)
            ->putJson("/api/users/{$anotherUser->id}/role", [
                'role' => 'admin',
            ]);

        $response->assertStatus(403);
    }

    /** @test */
    public function owner_cannot_update_role_to_admin()
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $user = User::factory()->create(['role' => 'user']);

        $response = $this->actingAs($owner)
            ->putJson("/api/users/{$user->id}/role", [
                'role' => 'admin',
            ]);

        $response->assertStatus(403);
    }

    /** @test */
    public function admin_can_update_own_role()
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)
            ->putJson("/api/users/{$admin->id}/role", [
                'role' => 'user',
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
            'role' => 'user',
        ]);
    }

    /** @test */
    public function user_can_update_own_role()
    {
        $user = User::factory()->create(['role' => 'user']);

        $response = $this->actingAs($user)
            ->putJson("/api/users/{$user->id}/role", [
                'role' => 'owner',
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'role' => 'owner',
        ]);
    }

    /** @test */
    public function updating_role_with_invalid_value_returns_error()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create(['role' => 'user']);

        $response = $this->actingAs($admin)
            ->putJson("/api/users/{$user->id}/role", [
                'role' => 'invalid-role',
            ]);

        $response->assertStatus(422);
    }
}
