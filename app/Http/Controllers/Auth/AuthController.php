<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        // Same lockout behaviour as the old FastAPI rate_limit.py:
        // 5 failed attempts (per IP + email combo) -> 15 minute lock.
        $throttleKey = 'login:' . $credentials['email'] . '|' . $request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            return response()->json([
                'detail' => "Too many attempts. Try again in {$seconds} seconds.",
            ], 429);
        }

        $user = User::where('email', $credentials['email'])->first();

        // Same-message-either-way as before: don't reveal whether the
        // email exists or the password was wrong.
        if (!$user || !$user->is_active || !Auth::attempt($credentials)) {
            RateLimiter::hit($throttleKey, 900); // 15 minutes
            throw ValidationException::withMessages([
                'email' => 'Incorrect email or password.',
            ]);
        }

        RateLimiter::clear($throttleKey);
        $request->session()->regenerate();

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ]);
    }

    public function logout(Request $request)
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['ok' => true]);
    }

    public function me(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['detail' => 'Not authenticated.'], 401);
        }

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ]);
    }
}
