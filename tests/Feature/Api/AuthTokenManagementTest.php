<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AuthTokenManagementTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'TokenPass1!';
    private const DEFAULT_TOKEN_NAME = 'stegolock-api';

    #[Test]
    public function authenticated_user_can_list_tokens(): void
    {
        $user = $this->createUser();

        $user->createToken(self::DEFAULT_TOKEN_NAME);
        $user->createToken('cli-token');

        $authToken = $user->createToken('test-client')->plainTextToken;

        $this->withToken($authToken)
            ->getJson('/api/auth/tokens')
            ->assertOk()
            ->assertJsonCount(3, 'tokens');
    }

    #[Test]
    public function authenticated_user_can_create_token(): void
    {
        $user = $this->createUser();
        $authToken = $user->createToken('test-client')->plainTextToken;

        $response = $this->withToken($authToken)
            ->postJson('/api/auth/tokens', [
                'name' => 'integration-suite',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('token.name', 'integration-suite')
            ->assertJsonStructure([
                'token' => ['id', 'name', 'plain_text', 'created_at'],
            ]);
    }

    #[Test]
    public function authenticated_user_can_revoke_owned_token(): void
    {
        $user = $this->createUser();

        $authToken = $user->createToken('test-client')->plainTextToken;
        $tokenToRevoke = $user->createToken('to-revoke');
        $tokenId = $tokenToRevoke->accessToken->id;

        $this->withToken($authToken)
            ->deleteJson("/api/auth/tokens/{$tokenId}")
            ->assertOk()
            ->assertJsonFragment(['message' => 'Token revoked.']);

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $tokenId]);
    }

    #[Test]
    public function revoke_token_returns_404_when_not_found(): void
    {
        $user = $this->createUser();
        $authToken = $user->createToken('test-client')->plainTextToken;

        $this->withToken($authToken)
            ->deleteJson('/api/auth/tokens/999999')
            ->assertStatus(404)
            ->assertJsonFragment(['message' => 'Token not found.']);
    }

    #[Test]
    public function login_rotates_default_api_token_without_touching_other_named_tokens(): void
    {
        $user = $this->createUser();

        $user->createToken(self::DEFAULT_TOKEN_NAME);
        $user->createToken(self::DEFAULT_TOKEN_NAME);
        $user->createToken('keep-me');

        $this->postJson('/api/auth/login', [
            'login' => $user->email,
            'password' => self::PASSWORD,
        ])->assertOk();

        $user->refresh();

        $this->assertSame(1, $user->tokens()->where('name', self::DEFAULT_TOKEN_NAME)->count());
        $this->assertSame(1, $user->tokens()->where('name', 'keep-me')->count());
    }

    private function createUser(): User
    {
        return User::factory()->create([
            'username' => 'token_' . fake()->unique()->numberBetween(1000, 9999),
            'email' => fake()->unique()->safeEmail(),
            'password' => self::PASSWORD,
            'mkd_salt' => str_repeat('ab', 16),
            'role' => 'user',
        ]);
    }
}
