<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TrainerClient;
use App\Models\User;
use App\Support\DateBounds;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ClientController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $coach = $request->user();
        abort_unless($coach?->role === 'coach', 403);

        return response()->json($coach->clients()
            ->where('users.role', 'client')
            ->where('users.is_active', true)
            ->wherePivot('status', 'active')
            ->where('trainer_clients.start_date', '<', DateBounds::after(today()->toDateString()))
            ->where(fn ($query) => $query->whereNull('trainer_clients.end_date')
                ->orWhereDate('trainer_clients.end_date', '>=', today()->toDateString()))
            ->orderBy('users.name')
            ->paginate(30)
            ->through(fn ($client) => $client->only(['id', 'name', 'email', 'role'])));
    }

    public function destroy(Request $request, User $client): JsonResponse
    {
        $coach = $request->user();
        abort_unless($coach && $coach->role === 'coach', 403);
        DB::transaction(function () use ($coach, $client): void {
            User::whereKey($coach->id)->lockForUpdate()->firstOrFail();
            User::whereKey($client->id)->lockForUpdate()->firstOrFail();
            Gate::authorize('viewTracker', $client);
            TrainerClient::where('trainer_id', $coach->id)->where('client_id', $client->id)
                ->update(['status' => 'inactive', 'end_date' => today()->toDateString(), 'health_monitoring_enabled_at' => null]);
        });

        return response()->json(['message' => 'Coaching ended. The client keeps their training and check-in history.']);
    }
}
