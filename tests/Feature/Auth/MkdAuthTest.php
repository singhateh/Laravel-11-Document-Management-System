<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Services\Stego\CryptoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * MkdAuthTest
 *
 * Feature tests for Master Key Derivation (MKD) integration in the
 * API authentication flow.
 *
 * Tests verify:
 *  - POST /api/auth/register persists mkd_salt on the new user row
 *  - POST /api/auth/register returns a valid Sanctum token
 *  - POST /api/auth/login stores the correct Master Key in the session
 *  - POST /api/auth/login succeeds for existing users without mkd_salt (graceful)
 *  - Wrong password is rejected with 422
 */
class MkdAuthTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'TestPass1!';

    // -------------------------------------------------------------------------
    // Register
    // -------------------------------------------------------------------------

    #[Test]
    public function register_persists_mkd_salt_on_user_row(): void
    {
        $this->postJson('/api/auth/register', $this->registerPayload())
            ->assertStatus(201);

        $user = User::whereEmail('stego@example.com')->first();

        $this->assertNotNull($user, 'User was not created.');
        $this->assertNotNull($user->mkd_salt, 'mkd_salt should be set after registration.');
        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $user->mkd_salt);
    }

    #[Test]
    public function register_returns_access_token(): void
    {
        $response = $this->postJson('/api/auth/register', $this->registerPayload());

        $response->assertStatus(201)
                 ->assertJsonStructure(['access_token', 'token_type', 'user' => ['id', 'email', 'role']]);
    }

    #[Test]
    public function register_rejects_duplicate_email(): void
    {
        $payload = $this->registerPayload();

        $this->postJson('/api/auth/register', $payload)->assertStatus(201);
        $this->postJson('/api/auth/register', $payload)->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // Login + session MKD
    // -------------------------------------------------------------------------

    #[Test]
    public function login_stores_master_key_in_session(): void
    {
        // Register so mkd_salt is set.
        $this->postJson('/api/auth/register', $this->registerPayload());

        $response = $this->postJson('/api/auth/login', [
            'login'    => 'stego@example.com',
            'password' => self::PASSWORD,
        ]);

        $response->assertOk()->assertJsonStructure(['access_token']);

        // The session should now hold a 64-hex-char Master Key.
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{64}$/',
            session('stego_mkd') ?? '',
            'stego_mkd session key should be a 64-char hex string after login.'
        );
    }

    #[Test]
    public function login_derived_master_key_matches_re_derivation(): void
    {
        $this->postJson('/api/auth/register', $this->registerPayload());
        $user = User::whereEmail('stego@example.com')->first();

        $this->postJson('/api/auth/login', [
            'login'    => 'stego@example.com',
            'password' => self::PASSWORD,
        ]);

        // Re-derive manually and compare.
        $crypto     = new CryptoService();
        $rederived  = $crypto->deriveMasterKey(self::PASSWORD, $user->mkd_salt);

        $this->assertSame($rederived['masterKey'], session('stego_mkd'));
    }

    #[Test]
    public function login_succeeds_for_legacy_user_without_mkd_salt(): void
    {
        // Simulate a user that existed before MKD was introduced (no mkd_salt).
        User::factory()->create([
            'username' => 'legacy',
            'email'    => 'legacy@example.com',
            'password' => bcrypt(self::PASSWORD),
            'role'     => 'user',
            'mkd_salt' => null,
        ]);

        $response = $this->postJson('/api/auth/login', [
            'login'    => 'legacy@example.com',
            'password' => self::PASSWORD,
        ]);

        // Should succeed — no mkd_salt just means no session key, not a rejection.
        $response->assertOk()->assertJsonStructure(['access_token']);
    }

    #[Test]
    public function login_rejects_wrong_password(): void
    {
        $this->postJson('/api/auth/register', $this->registerPayload());

        $this->postJson('/api/auth/login', [
            'login'    => 'stego@example.com',
            'password' => 'WrongPassword999!',
        ])->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function registerPayload(): array
    {
        return [
            'name'                  => 'Stego Tester',
            'username'              => 'stegotester',
            'email'                 => 'stego@example.com',
            'password'              => self::PASSWORD,
            'password_confirmation' => self::PASSWORD,
            'role'                  => 'user',
        ];
    }
}
