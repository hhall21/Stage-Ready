<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveDailyCheckInRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class DailyCheckInController extends Controller
{
    public function index(User $client): JsonResponse
    {
        Gate::authorize('viewTracker', $client);

        return response()->json($client->dailyCheckIns()->orderByDesc('check_in_date')->paginate(30));
    }

    public function show(User $client, int $checkIn): JsonResponse
    {
        Gate::authorize('viewTracker', $client);

        return response()->json(['data' => $client->dailyCheckIns()->findOrFail($checkIn)]);
    }

    public function store(SaveDailyCheckInRequest $request, User $client): JsonResponse
    {
        Gate::authorize('manageCheckIns', $client);
        $data = $request->validated();
        $record = DB::transaction(function () use ($client, $data) {
            User::whereKey($client->id)->lockForUpdate()->firstOrFail();
            if ($client->dailyCheckIns()->whereDate('check_in_date', $data['check_in_date'])->exists()) {
                throw ValidationException::withMessages(['check_in_date' => 'A check-in already exists for this date.']);
            }

            return $client->dailyCheckIns()->create($data);
        });

        return response()->json(['data' => $record], 201);
    }

    public function update(SaveDailyCheckInRequest $request, User $client, int $checkIn): JsonResponse
    {
        Gate::authorize('manageCheckIns', $client);
        $data = $request->validated();
        $record = DB::transaction(function () use ($client, $data, $checkIn) {
            User::whereKey($client->id)->lockForUpdate()->firstOrFail();
            $record = $client->dailyCheckIns()->findOrFail($checkIn);
            if (isset($data['check_in_date']) && $client->dailyCheckIns()->whereKeyNot($checkIn)->whereDate('check_in_date', $data['check_in_date'])->exists()) {
                throw ValidationException::withMessages(['check_in_date' => 'A check-in already exists for this date.']);
            }
            $record->update($data);

            return $record;
        });

        return response()->json(['data' => $record]);
    }

    public function destroy(User $client, int $checkIn): JsonResponse
    {
        Gate::authorize('manageCheckIns', $client);
        $client->dailyCheckIns()->findOrFail($checkIn)->delete();

        return response()->json(null, 204);
    }
}
