<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CoachingHandover;
use App\Models\TrainerClient;
use App\Models\User;
use App\Services\CoachingHandoverBuilder;
use App\Services\CoachingHandoverPdf;
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
            ->through(fn ($client) => $client->only(['id', 'name', 'email', 'role', 'is_bodybuilding_coach'])));
    }

    public function destroy(Request $request, User $client, CoachingHandoverBuilder $builder, CoachingHandoverPdf $pdf): JsonResponse
    {
        $coach = $request->user();
        abort_unless($coach && $coach->role === 'coach', 403);
        Gate::authorize('viewTracker', $client);
        $data = $request->validate([
            'feedback' => ['nullable', 'string', 'max:10000'],
            'next_steps' => ['nullable', 'string', 'max:5000'],
            'notes' => ['nullable', 'array', 'list', 'max:10'],
            'notes.*' => ['required', 'array:title,period,body'],
            'notes.*.title' => ['required', 'string', 'max:160'],
            'notes.*.period' => ['required', 'string', 'max:120'],
            'notes.*.body' => ['required', 'string', 'max:10000'],
        ]);
        DB::transaction(function () use ($coach, $client, $builder, $pdf, $data): void {
            $coach = User::whereKey($coach->id)->lockForUpdate()->firstOrFail();
            $client = User::whereKey($client->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($coach)->authorize('viewTracker', $client);
            $assignment = TrainerClient::where('trainer_id', $coach->id)->where('client_id', $client->id)->lockForUpdate()->firstOrFail();
            $snapshot = $builder->build($coach, $client, $assignment, $data);
            // Save the report and end the assignment together. A rendering failure leaves coaching active.
            CoachingHandover::create(['coach_id' => $coach->id, 'client_id' => $client->id, 'started_on' => $assignment->start_date,
                'ended_at' => now(), 'snapshot' => $snapshot, 'pdf_base64' => base64_encode($pdf->render($snapshot))]);
            $assignment->forceFill(['status' => 'inactive', 'end_date' => today()->toDateString(), 'health_monitoring_enabled_at' => null])->save();
        });

        return response()->json(['message' => 'Coaching ended. The client can sign in to view and download their handover PDF. Their account and history are preserved.']);
    }
}
