<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveMesocycleRequest;
use App\Models\User;
use App\Services\MesocycleScheduler;
use App\Support\DateBounds;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class MesocycleController extends Controller
{
    public function index(User $client): JsonResponse
    {
        Gate::authorize('viewTracker', $client);

        return response()->json($client->mesocycles()->orderByDesc('start_date')->paginate(20));
    }

    public function store(SaveMesocycleRequest $request, User $client, MesocycleScheduler $scheduler): JsonResponse
    {
        Gate::authorize('manageMesocycles', $client);
        $cycle = DB::transaction(function () use ($request, $client, $scheduler) {
            User::whereKey($client->id)->lockForUpdate()->firstOrFail();
            $data = $request->validated();
            $this->validateDates($client, $data);
            $cycle = $client->mesocycles()->create([...$data, 'trainer_id' => $request->user()?->id]);
            $scheduler->sync($cycle, false);

            return $cycle;
        });

        return response()->json(['data' => $cycle], 201);
    }

    public function update(SaveMesocycleRequest $request, User $client, int $mesocycle, MesocycleScheduler $scheduler): JsonResponse
    {
        Gate::authorize('manageMesocycles', $client);
        $cycle = DB::transaction(function () use ($request, $client, $mesocycle, $scheduler) {
            User::whereKey($client->id)->lockForUpdate()->firstOrFail();
            $cycle = $client->mesocycles()->lockForUpdate()->findOrFail($mesocycle);
            $data = $request->validated();
            $this->validateDates($client, $data, $cycle->id);
            $cycle->update($data);
            $scheduler->sync($cycle, true);

            return $cycle;
        });

        return response()->json(['data' => $cycle]);
    }

    /** @param array<string, mixed> $data */
    private function validateDates(User $client, array $data, ?int $except = null): void
    {
        if (CarbonImmutable::parse($data['start_date'])->diffInDays(CarbonImmutable::parse($data['end_date'])) > 365) {
            throw ValidationException::withMessages(['end_date' => 'A mesocycle can span at most one year.']);
        }
        $query = $client->mesocycles()->where('start_date', '<', DateBounds::after($data['end_date']))->where('end_date', '>=', DateBounds::start($data['start_date']));
        if ($except !== null) {
            $query->whereKeyNot($except);
        }
        if ($query->exists()) {
            throw ValidationException::withMessages(['start_date' => 'These dates overlap another mesocycle for this client.']);
        }
    }
}
