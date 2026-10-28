<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ClientInvitation;
use App\Models\TrainerClient;
use App\Models\User;
use App\Notifications\ClientInvitationNotification;
use App\Rules\SafePassword;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class ClientInvitationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $coach = $this->coach($request);

        return response()->json(ClientInvitation::where('trainer_id', $coach->id)
            ->whereNull('accepted_at')->whereNull('revoked_at')->latest()->paginate(30)
            ->through(fn (ClientInvitation $invite) => $invite->only(['id', 'name', 'email', 'expires_at'])));
    }

    public function store(Request $request): JsonResponse
    {
        $coach = $this->coach($request);
        $data = $request->validate(['name' => ['required', 'string', 'max:100'], 'email' => ['required', 'email', 'max:255']]);
        $data['email'] = Str::lower($data['email']);
        $token = Str::random(64);
        $invite = DB::transaction(function () use ($coach, $data, $token): ClientInvitation {
            User::whereKey($coach->id)->lockForUpdate()->firstOrFail();
            $client = User::where('email', $data['email'])->first();
            if ($client && ($client->role !== 'client' || ! $client->is_active)) {
                throw ValidationException::withMessages(['email' => 'This account cannot accept a client invitation.']);
            }
            if ($client && $coach->clients()->whereKey($client->id)->wherePivot('status', 'active')->exists()) {
                throw ValidationException::withMessages(['email' => 'This client already has active coaching with you.']);
            }

            return ClientInvitation::updateOrCreate(['trainer_id' => $coach->id, 'email' => $data['email']],
                ['name' => $data['name'], 'token_hash' => hash('sha256', $token), 'expires_at' => now()->addDays(7), 'accepted_at' => null, 'revoked_at' => null]);
        });

        return $this->deliver($coach, $invite, $token, 201);
    }

    public function resend(Request $request, int $invitation): JsonResponse
    {
        $coach = $this->coach($request);
        $token = Str::random(64);
        $invite = DB::transaction(function () use ($coach, $invitation, $token): ClientInvitation {
            User::whereKey($coach->id)->lockForUpdate()->firstOrFail();
            $invite = ClientInvitation::where('trainer_id', $coach->id)->whereNull('accepted_at')->whereNull('revoked_at')->lockForUpdate()->findOrFail($invitation);
            $invite->update(['token_hash' => hash('sha256', $token), 'expires_at' => now()->addDays(7)]);

            return $invite;
        });

        return $this->deliver($coach, $invite, $token);
    }

    public function destroy(Request $request, int $invitation): JsonResponse
    {
        $coach = $this->coach($request);
        DB::transaction(function () use ($coach, $invitation): void {
            User::whereKey($coach->id)->lockForUpdate()->firstOrFail();
            ClientInvitation::where('trainer_id', $coach->id)->whereNull('accepted_at')->whereNull('revoked_at')
                ->lockForUpdate()->findOrFail($invitation)->update(['revoked_at' => now()]);
        });

        return response()->json(null, 204);
    }

    public function show(Request $request, string $token): Response
    {
        $invite = ClientInvitation::with('trainer')->where('token_hash', hash('sha256', $token))->first();
        $valid = $invite && $invite->isUsable() && $invite->trainer?->is_active && $invite->trainer->hasPaidCoachAccess();

        return Inertia::render('JoinCoach', [
            'token' => $token,
            'invitation' => $valid ? ['name' => $invite->name, 'email' => $invite->email, 'coach' => $invite->trainer->name] : null,
            'existingAccount' => $valid && User::where('email', $invite->email)->exists(),
            'signedInAsInvitee' => $valid && $request->user()?->email === $invite->email,
        ]);
    }

    public function accept(Request $request, string $token): JsonResponse
    {
        $coachId = ClientInvitation::where('token_hash', hash('sha256', $token))->value('trainer_id');
        abort_if($coachId === null, 410, 'This invitation is invalid or no longer available.');
        $client = DB::transaction(function () use ($request, $token, $coachId): User {
            $coach = User::whereKey($coachId)->lockForUpdate()->firstOrFail();
            abort_unless($coach->role === 'coach' && $coach->is_active && $coach->hasPaidCoachAccess(), 410, 'This coaching invitation is no longer available.');
            $invite = ClientInvitation::where('token_hash', hash('sha256', $token))->lockForUpdate()->first();
            abort_unless($invite?->isUsable(), 410, 'This invitation has expired, been cancelled, or already been accepted.');
            $client = User::where('email', $invite->email)->lockForUpdate()->first();
            if ($client) {
                abort_unless($client->role === 'client' && $client->is_active && $request->user()?->is($client), 403, 'Sign in to the invited client account to accept.');
            } else {
                abort_if($request->user() !== null, 403, 'Sign out before setting up this new client account.');
                $data = $request->validate(['password' => ['required', 'string', 'confirmed', new SafePassword, Password::min(12)->mixedCase()->numbers()]]);
                $client = new User(['name' => $invite->name, 'email' => $invite->email, 'password' => $data['password']]);
                $client->forceFill(['role' => 'client', 'is_active' => true, 'email_verified_at' => now()])->save();
            }
            TrainerClient::updateOrCreate(['trainer_id' => $coach->id, 'client_id' => $client->id],
                ['status' => 'active', 'start_date' => today()->toDateString(), 'end_date' => null, 'review_phase' => 'offseason', 'review_started_on' => today()->toDateString()]);
            $invite->update(['accepted_at' => now()]);

            return $client;
        });
        Auth::login($client);
        $request->session()->regenerate();
        $request->session()->put('password_hash_'.Auth::getDefaultDriver(), $client->getAuthPassword());

        return response()->json(['message' => 'Your coaching workspace is ready.']);
    }

    private function coach(Request $request): User
    {
        $coach = $request->user();
        abort_unless($coach && $coach->role === 'coach', 403);

        return $coach;
    }

    private function deliver(User $coach, ClientInvitation $invite, string $token, int $status = 200): JsonResponse
    {
        $url = rtrim((string) config('app.url'), '/').'/join/'.$token;
        $delivery = 'sent';
        try {
            Notification::route('mail', $invite->email)->notify(new ClientInvitationNotification($coach->name, $url));
            if (in_array(config('mail.default'), ['log', 'array'], true)) {
                $delivery = 'local';
            }
        } catch (Throwable $exception) {
            report($exception);
            $delivery = 'failed';
        }

        return response()->json(['data' => $invite->only(['id', 'name', 'email', 'expires_at']), 'accept_url' => $url, 'delivery' => $delivery], $status);
    }
}
