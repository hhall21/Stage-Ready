<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Rules\SafePassword;
use App\Support\CoachPricing;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Throwable;

class CoachRegistrationController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        abort_if($request->user() !== null, 409, 'Sign out before creating a new coach account.');
        $email = $request->input('email');
        $request->merge(['email' => is_string($email) ? Str::lower(trim($email)) : $email]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'], 'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'confirmed', new SafePassword, Password::min(12)->mixedCase()->numbers()],
            'is_bodybuilding_coach' => ['required', 'boolean'],
        ]);
        $coach = new User(['name' => $data['name'], 'email' => $data['email'], 'password' => $data['password']]);
        $coach->forceFill(['role' => 'coach', 'is_admin' => false, 'billing_exempt' => false, 'is_active' => true,
            'is_bodybuilding_coach' => $data['is_bodybuilding_coach']])->save();
        $delivery = 'sent';
        try {
            event(new Registered($coach));
            if (in_array(config('mail.default'), ['array', 'log'], true)) {
                $delivery = 'local';
            }
        } catch (Throwable $error) {
            report($error);
            $delivery = 'failed';
        }
        Auth::login($coach);
        $request->session()->regenerate();
        $request->session()->put('password_hash_'.Auth::getDefaultDriver(), $coach->getAuthPassword());

        return response()->json(['message' => 'Your coach account is ready. Verify your email, then subscribe for '.CoachPricing::forCoach($coach)['price_label'].' per month.', 'delivery' => $delivery], 201);
    }
}
