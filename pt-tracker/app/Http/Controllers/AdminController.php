<?php

namespace App\Http\Controllers;

use App\Models\ClientInvitation;
use App\Models\User;
use App\Notifications\CoachWelcomeNotification;
use App\Support\DateBounds;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class AdminController extends Controller
{
    public function page(): Response
    {
        return Inertia::render('Admin');
    }

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'role' => ['nullable', Rule::in(['coach', 'client', 'admin'])],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]);
        $search = trim($filters['search'] ?? '');
        $role = $filters['role'] ?? null;
        $status = $filters['status'] ?? null;
        $users = User::query()
            ->select(['id', 'name', 'email', 'role', 'is_active', 'is_admin', 'billing_exempt', 'is_bodybuilding_coach', 'created_at'])
            ->with(['coachMembership' => fn ($query) => $query->select(['id', 'user_id', 'status', 'last_paid_at', 'paid_through', 'retention_expires_at', 'data_purged_at'])])
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('name', 'like', '%'.$search.'%')->orWhere('email', 'like', '%'.$search.'%')))
            ->when($role === 'admin', fn ($query) => $query->where('is_admin', true))
            ->when(in_array($role, ['coach', 'client'], true), fn ($query) => $query->where('role', $role))
            ->when($status !== null, fn ($query) => $query->where('is_active', $status === 'active'))
            ->withCount(['clients as active_clients_count' => fn ($query) => $query
                ->where('role', 'client')->where('is_active', true)
                ->where('trainer_clients.status', 'active')
                ->where('trainer_clients.start_date', '<', DateBounds::after(today()))
                ->where(fn ($query) => $query->whereNull('trainer_clients.end_date')
                    ->orWhereDate('trainer_clients.end_date', '>=', today()))])
            ->orderBy('name')->orderBy('id')->paginate(20);

        return response()->json([
            'users' => $users,
            'summary' => [
                'active_coaches' => User::where('role', 'coach')->where('is_active', true)->count(),
                'active_clients' => User::where('role', 'client')->where('is_active', true)->count(),
                'inactive_accounts' => User::where('is_active', false)->count(),
                'pending_invitations' => ClientInvitation::whereNull('accepted_at')->whereNull('revoked_at')
                    ->where('expires_at', '>', now())
                    ->whereHas('trainer', fn ($query) => $query->where('role', 'coach')->where('is_active', true))->count(),
            ],
        ]);
    }

    public function storeCoach(Request $request): JsonResponse
    {
        $request->merge(['email' => is_string($request->input('email')) ? Str::lower(trim($request->input('email'))) : $request->input('email')]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'is_bodybuilding_coach' => ['required', 'boolean'],
        ]);
        $coach = new User(['name' => $data['name'], 'email' => $data['email'], 'password' => Str::random(64)]);
        $coach->forceFill(['role' => 'coach', 'is_active' => true, 'is_admin' => false, 'is_bodybuilding_coach' => $data['is_bodybuilding_coach']])->save();

        return $this->setupLink($coach, 201);
    }

    public function updateAccess(Request $request, User $user): JsonResponse
    {
        $data = $request->validate(['is_active' => ['required', 'boolean']]);
        DB::transaction(function () use ($user, $data): void {
            $account = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            if ($account->is_admin) {
                throw ValidationException::withMessages(['is_active' => 'Administrator accounts cannot be deactivated from this portal.']);
            }
            $account->forceFill(['is_active' => $data['is_active']])->save();
        });

        return response()->json(['message' => $data['is_active'] ? 'Account activated.' : 'Account deactivated. Its records and coaching assignments have been preserved.']);
    }

    public function resendSetupLink(User $coach): JsonResponse
    {
        abort_unless($coach->role === 'coach' && ! $coach->is_admin, 404);
        if (! $coach->is_active) {
            throw ValidationException::withMessages(['coach' => 'Activate this coach account before sending a password link.']);
        }

        return $this->setupLink($coach);
    }

    private function setupLink(User $coach, int $status = 200): JsonResponse
    {
        $token = Password::broker()->createToken($coach);
        $url = rtrim((string) config('app.url'), '/').'/reset-password/'.$token.'?email='.rawurlencode($coach->email);
        $delivery = 'sent';
        try {
            $coach->notify(new CoachWelcomeNotification($url));
            if (in_array(config('mail.default'), ['log', 'array'], true)) {
                $delivery = 'local';
            }
        } catch (Throwable $exception) {
            report($exception);
            $delivery = 'failed';
        }

        return response()->json([
            'data' => $coach->only(['id', 'name', 'email', 'role', 'is_active', 'is_bodybuilding_coach']),
            'setup_url' => $url,
            'expires_in_minutes' => (int) config('auth.passwords.users.expire'),
            'delivery' => $delivery,
        ], $status);
    }
}
