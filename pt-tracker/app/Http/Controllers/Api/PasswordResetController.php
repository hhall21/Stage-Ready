<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Rules\SafePassword;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

class PasswordResetController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:255']]);
        $data['email'] = Str::lower($data['email']);
        Password::sendResetLink(['email' => $data['email'], 'is_active' => true]);

        return response()->json(['message' => 'If an active account matches that email, a password reset link has been sent. Check your inbox and spam folder.']);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'], 'token' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'confirmed', new SafePassword, PasswordRule::min(12)->mixedCase()->numbers()],
        ]);
        $data['email'] = Str::lower($data['email']);
        $status = Password::reset([...$data, 'is_active' => true], function (User $user, string $password): void {
            $user->forceFill(['password' => $password, 'remember_token' => Str::random(60), 'email_verified_at' => $user->email_verified_at ?? now()])->save();
            event(new PasswordReset($user));
        });
        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['token' => 'This reset link is invalid or has expired. Request a new link.']);
        }
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Password updated. You can now sign in.']);
    }
}
