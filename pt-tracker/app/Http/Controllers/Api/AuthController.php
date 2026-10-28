<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CoachingHandover;
use App\Rules\SafePassword;
use App\Support\AccountNavigation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    private const USER_FIELDS = ['id', 'name', 'email', 'role', 'is_admin', 'is_bodybuilding_coach'];

    public function csrf(Request $request): JsonResponse
    {
        return response()->json(['csrf_token' => $request->session()->token()]);
    }

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', new SafePassword],
        ]);
        $credentials['email'] = Str::lower($credentials['email']);
        if (! Auth::attempt([...$credentials, 'is_active' => true])) {
            throw ValidationException::withMessages(['email' => 'The login details are incorrect or the account is inactive.']);
        }
        $request->session()->regenerate();
        $request->session()->put('password_hash_'.Auth::getDefaultDriver(), $request->user()?->getAuthPassword());

        return $this->user($request);
    }

    public function user(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json(['data' => $user ? [...$user->only(self::USER_FIELDS),
            'has_active_coaching' => $user->role === 'client' && $user->hasActiveCoach(),
            'has_suspended_coaching' => $user->role === 'client' && $user->hasSuspendedCoaching(),
            'has_coaching_handovers' => $user->role === 'client' && CoachingHandover::where('client_id', $user->id)->exists(),
        ] : null, 'navigation' => AccountNavigation::forUser($user)]);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Logged out.']);
    }
}
