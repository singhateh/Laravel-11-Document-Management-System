<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\Stego\CryptoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    public function __construct(private readonly CryptoService $crypto) {}

    /**
     * Display the login view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        // Derive the per-user Master Key from the plaintext password + stored
        // salt and hold it server-side for the lifetime of this session.
        // If the user pre-dates MKD (no salt stored), generate one now and save it.
        $user = Auth::user();
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

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
