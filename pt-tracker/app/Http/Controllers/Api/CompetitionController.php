<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveCompetitionRequest;
use App\Models\Competition;
use App\Models\User;
use App\Support\DateBounds;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class CompetitionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $coach = $request->user();
        abort_unless($coach?->role === 'coach', 403);
        $filters = $request->validate([
            'period' => ['sometimes', Rule::in(['all', 'upcoming', 'past'])],
            'client_id' => ['sometimes', 'integer', 'min:1'],
        ]);
        $period = $filters['period'] ?? 'all';

        $eligible = $coach->clients()
            ->where('users.role', 'client')->where('users.is_active', true)
            ->wherePivot('status', 'active')
            ->where('trainer_clients.start_date', '<', DateBounds::after(today()->toDateString()))
            ->where(fn ($query) => $query->whereNull('trainer_clients.end_date')
                ->orWhereDate('trainer_clients.end_date', '>=', today()->toDateString()));
        $options = (clone $eligible)->whereHas('competitions')
            ->orderBy('users.name')->orderBy('users.id')->get(['users.id', 'users.name'])
            ->map(fn (User $client) => $client->only(['id', 'name']));

        $clients = $eligible
            ->when(isset($filters['client_id']), fn ($query) => $query->where('users.id', $filters['client_id']))
            ->withWhereHas('competitions', fn ($query) => $query
                ->when($period !== 'all', fn ($query) => $query->where('competition_date', $period === 'past' ? '<' : '>=', today()->toDateString()))
                ->orderBy('competition_date')->orderBy('id'))
            ->orderBy('users.name')->orderBy('users.id')->paginate(12)->withQueryString()
            ->through(fn (User $client) => [
                'id' => $client->id,
                'name' => $client->name,
                'competitions' => $client->competitions->map(fn (Competition $competition) => $this->data($competition)),
            ]);

        return response()->json([
            ...$clients->toArray(),
            'today' => today()->toDateString(),
            'client_options' => $options,
        ]);
    }

    public function store(SaveCompetitionRequest $request, User $client): JsonResponse
    {
        $competition = DB::transaction(function () use ($request, $client) {
            User::whereKey($client->id)->lockForUpdate()->firstOrFail();
            Gate::authorize('manageCompetitions', $client);

            return $client->competitions()->create($request->validated());
        });

        return response()->json(['data' => $this->data($competition)], 201);
    }

    public function update(SaveCompetitionRequest $request, User $client, int $competition): JsonResponse
    {
        $record = DB::transaction(function () use ($request, $client, $competition) {
            User::whereKey($client->id)->lockForUpdate()->firstOrFail();
            Gate::authorize('manageCompetitions', $client);
            $record = $client->competitions()->findOrFail($competition);
            $record->update($request->validated());

            return $record;
        });

        return response()->json(['data' => $this->data($record)]);
    }

    public function destroy(User $client, int $competition): JsonResponse
    {
        Gate::authorize('manageCompetitions', $client);
        DB::transaction(function () use ($client, $competition): void {
            User::whereKey($client->id)->lockForUpdate()->firstOrFail();
            Gate::authorize('manageCompetitions', $client);
            $client->competitions()->findOrFail($competition)->delete();
        });

        return response()->json(['message' => 'Competition removed.']);
    }

    /** @return array<string, mixed> */
    private function data(Competition $competition): array
    {
        $past = $competition->hasTakenPlace();

        return [
            'id' => $competition->id,
            'competition_name' => $competition->competition_name,
            'competition_date' => Carbon::parse($competition->competition_date)->toDateString(),
            'location' => $competition->location,
            'category' => $competition->category,
            'period' => $past ? 'past' : 'upcoming',
            ...($past ? ['placing' => $competition->placing, 'notes' => $competition->notes] : []),
        ];
    }
}
