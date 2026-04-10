<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Stego\CryptoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/**
 * AuthController (API)
 *
 * Provides Sanctum token-based authentication for the StegoLock API layer.
 *
 * NOTE: The .md guide specifies tymon/jwt-auth. Since Laravel Sanctum v4 is
 * already installed and provides equivalent stateless API token authentication,
 * Sanctum personal access tokens are used here instead — no extra package needed.
 * Tokens are Bearer tokens passed in the Authorization header.
 *
 * Routes (all under /api/auth, see routes/api.php):
 *   POST /api/auth/register  — create account, return token
 *   POST /api/auth/login     — authenticate, return token
 *   POST /api/auth/logout    — revoke current token  [auth:sanctum]
 *   GET  /api/auth/me        — return authenticated user  [auth:sanctum]
 */
class AuthController extends Controller
{
    private const DEFAULT_TOKEN_NAME = 'stegolock-api';

    public function __construct(private readonly CryptoService $crypto) {}

    // -------------------------------------------------------------------------
    // Register
    // -------------------------------------------------------------------------

    /**
     * Register a new user and return a Sanctum API token.
     *
     * @param  Request $request
     * @return JsonResponse
     */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'                  => ['required', 'string', 'max:255'],
            'username'              => ['required', 'string', 'max:255', 'unique:users,username', 'regex:/^[a-z0-9_]+$/i'],
            'email'                 => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password'              => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()],
            'role'                  => ['sometimes', 'in:user,owner,admin'],
        ]);

        // Derive a Master Key salt for this user (the key itself is never stored).
        $mkdResult = $this->crypto->deriveMasterKey($validated['password']);

        $user = User::create([
            'name'     => $validated['name'],
            'username' => $validated['username'],
            'email'    => $validated['email'],
            'password' => $validated['password'],   // cast to 'hashed' in model
            'role'     => $validated['role'] ?? 'user',
            'mkd_salt' => $mkdResult['salt'],       // 32 hex chars; key itself is NOT stored
        ]);

        $token = $user->createToken(self::DEFAULT_TOKEN_NAME)->plainTextToken;

        return $this->tokenResponse($user, $token, 201);
    }

    // -------------------------------------------------------------------------
    // Login
    // -------------------------------------------------------------------------

    /**
     * Authenticate with email + password and return a Sanctum API token.
     * Supports login by email or username.
     *
     * @param  Request $request
     * @return JsonResponse
     * @throws ValidationException
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'login'    => ['required', 'string'],   // accepts email or username
            'password' => ['required', 'string'],
        ]);

        // Determine whether the login field looks like an email.
        $field = filter_var($request->login, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        $user = User::where($field, $request->login)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'login' => ['The provided credentials are incorrect.'],
            ]);
        }

        // Revoke all previous tokens for this device name before issuing a new one.
        $user->tokens()->where('name', self::DEFAULT_TOKEN_NAME)->delete();

        $token = $user->createToken(self::DEFAULT_TOKEN_NAME)->plainTextToken;

        // Re-derive the Master Key using the stored salt and store it in the server-side
        // session for this request's lifetime.  The key never leaves the server.
        // If the user pre-dates MKD (no salt stored), generate one now and save it.
        if (!$user->mkd_salt) {
            $mkdResult = $this->crypto->deriveMasterKey($request->password);
            $user->update(['mkd_salt' => $mkdResult['salt']]);
        } else {
            $mkdResult = $this->crypto->deriveMasterKey(
                $request->password,
                $user->mkd_salt,
                (int) config('stegolock.mkd_iterations', 100_000)
            );
        }
        session(['stego_mkd' => $mkdResult['masterKey']]);

        return $this->tokenResponse($user, $token);
    }

    // -------------------------------------------------------------------------
    // Logout
    // -------------------------------------------------------------------------

    /**
     * Revoke the currently authenticated token.
     *
     * @param  Request $request
     * @return JsonResponse
     */
    public function logout(Request $request): JsonResponse
    {
        $user = $this->authenticatedUser($request);
        $user->tokens()->where('id', $user->currentAccessToken()->id)->delete();

        return response()->json(['message' => 'Successfully logged out.']);
    }

    // -------------------------------------------------------------------------
    // Me
    // -------------------------------------------------------------------------

    /**
     * Return the authenticated user's profile.
     *
     * @param  Request $request
     * @return JsonResponse
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => $this->userResource($this->authenticatedUser($request)),
        ]);
    }

    // -------------------------------------------------------------------------
    // Internal
    // -------------------------------------------------------------------------

    /**
     * Return a consistent user representation (excludes sensitive fields).
     *
     * @param  User $user
     * @return array
     */
    // -------------------------------------------------------------------------
    // Token management
    // -------------------------------------------------------------------------

    public function listTokens(Request $request): JsonResponse
    {
        $tokens = $this->authenticatedUser($request)->tokens()->select(
            'id', 'name', 'abilities', 'last_used_at', 'expires_at', 'created_at'
        )->latest()->get();

        return response()->json(['tokens' => $tokens]);
    }

    public function createToken(Request $request): JsonResponse
    {
        $request->validate(['name' => ['required', 'string', 'max:100']]);

        $token = $this->authenticatedUser($request)->createToken(
            $request->name,
            ['*'],
            now()->addDays(365)
        );

        return response()->json([
            'token' => [
                'id'           => $token->accessToken->id,
                'name'         => $token->accessToken->name,
                'plain_text'   => $token->plainTextToken,   // only returned once
                'created_at'   => $token->accessToken->created_at->toISOString(),
            ],
        ], 201);
    }

    public function revokeToken(Request $request, int $id): JsonResponse
    {
        $deleted = $this->authenticatedUser($request)->tokens()->where('id', $id)->delete();

        if (!$deleted) {
            return response()->json(['message' => 'Token not found.'], 404);
        }

        return response()->json(['message' => 'Token revoked.']);
    }

    // -------------------------------------------------------------------------
    // Internal
    // -------------------------------------------------------------------------

    private function userResource(User $user): array
    {
        return [
            'id'         => $user->id,
            'name'       => $user->name,
            'username'   => $user->username,
            'email'      => $user->email,
            'role'       => $user->role,
            'avatar'     => $user->avatar,
            'created_at' => $user->created_at?->toISOString(),
        ];
    }

    private function tokenResponse(User $user, string $token, int $status = 200): JsonResponse
    {
        return response()->json([
            'user'         => $this->userResource($user),
            'access_token' => $token,
            'token_type'   => 'Bearer',
        ], $status);
    }

    private function authenticatedUser(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
