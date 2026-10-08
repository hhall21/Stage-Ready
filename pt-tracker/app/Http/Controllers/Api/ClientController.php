<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
            ->whereDate('trainer_clients.start_date', '<=', today()->toDateString())
            ->where(fn ($query) => $query->whereNull('trainer_clients.end_date')
                ->orWhereDate('trainer_clients.end_date', '>=', today()->toDateString()))
            ->orderBy('users.name')
            ->paginate(30)
            ->through(fn ($client) => $client->only(['id', 'name', 'email', 'role'])));
    }
}
