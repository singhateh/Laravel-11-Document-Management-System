<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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

        $user = User::create([
            'name'     => $validated['name'],
            'username' => $validated['username'],
            'email'    => $validated['email'],
            'password' => $validated['password'],   // cast to 'hashed' in model
            'role'     => $validated['role'] ?? 'user',
        ]);

        $token = $user->createToken('stegolock-api')->plainTextToken;

        return response()->json([
            'user'         => $this->userResource($user),
            'access_token' => $token,
            'token_type'   => 'Bearer',
        ], 201);
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
        $user->tokens()->where('name', 'stegolock-api')->delete();

        $token = $user->createToken('stegolock-api')->plainTextToken;

        return response()->json([
            'user'         => $this->userResource($user),
            'access_token' => $token,
            'token_type'   => 'Bearer',
        ]);
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
        $request->user()->currentAccessToken()->delete();

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
            'user' => $this->userResource($request->user()),
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
        $tokens = $request->user()->tokens()->select(
            'id', 'name', 'abilities', 'last_used_at', 'expires_at', 'created_at'
        )->latest()->get();

        return response()->json(['tokens' => $tokens]);
    }

    public function createToken(Request $request): JsonResponse
    {
        $request->validate(['name' => ['required', 'string', 'max:100']]);

        $token = $request->user()->createToken(
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
        $deleted = $request->user()->tokens()->where('id', $id)->delete();

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
}
