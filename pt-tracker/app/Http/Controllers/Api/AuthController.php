<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Rules\SafePassword;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    private const USER_FIELDS = ['id', 'name', 'email', 'role', 'is_bodybuilding_coach'];

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
        return response()->json(['data' => $request->user()?->only(self::USER_FIELDS)]);
    }

    public function coachingType(Request $request): JsonResponse
    {
        abort_unless($request->user()?->role === 'coach', 403);
        $data = $request->validate(['is_bodybuilding_coach' => ['required', 'boolean']]);
        $coach = DB::transaction(function () use ($request, $data): User {
            $coach = User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            abort_unless($coach->role === 'coach' && $coach->is_active, 403);
            $coach->forceFill($data)->save();

            return $coach;
        });

        Auth::setUser($coach);

        return response()->json(['data' => $coach->only(self::USER_FIELDS)]);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Logged out.']);
    }
}
