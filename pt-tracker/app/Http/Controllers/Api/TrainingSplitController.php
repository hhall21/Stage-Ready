<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveTrainingSplitRequest;
use App\Models\TrainingSplit;
use App\Models\User;
use App\Services\TrainingSplitScheduler;
use App\Support\DateBounds;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class TrainingSplitController extends Controller
{
    public function index(User $client): JsonResponse
    {
        Gate::authorize('viewTracker', $client);

        return response()->json(TrainingSplit::where('client_id', $client->id)->orderByDesc('start_date')->paginate(20));
    }

    public function store(SaveTrainingSplitRequest $request, User $client): JsonResponse
    {
        Gate::authorize('manageMesocycles', $client);
        $split = DB::transaction(function () use ($request, $client): TrainingSplit {
            User::whereKey($client->id)->lockForUpdate()->firstOrFail();
            Gate::authorize('manageMesocycles', $client);
            $data = $request->validated();
            $this->checkStart($client, $data['start_date']);
            $nextStart = TrainingSplit::where('client_id', $client->id)->where('start_date', '>=', DateBounds::after($data['start_date']))->min('start_date');
            $affected = $client->workouts()->whereNotNull('training_split_id')->where('workout_date', '>=', DateBounds::start($data['start_date']))
                ->when($nextStart !== null, fn ($query) => $query->where('workout_date', '<', DateBounds::start($nextStart)));
            if ($affected->get()->contains(fn ($workout) => $workout->hasPerformance())) {
                throw ValidationException::withMessages(['start_date' => 'A split already has logged workouts in this period. Start the new split after those workouts.']);
            }
            $split = TrainingSplit::create(['client_id' => $client->id, 'trainer_id' => $request->user()?->id, ...$data]);
            $this->clearFuture($client, $data['start_date']);

            return $split;
        });

        return response()->json(['data' => $split], 201);
    }

    public function update(SaveTrainingSplitRequest $request, User $client, int $split): JsonResponse
    {
        Gate::authorize('manageMesocycles', $client);
        $record = DB::transaction(function () use ($request, $client, $split): TrainingSplit {
            User::whereKey($client->id)->lockForUpdate()->firstOrFail();
            Gate::authorize('manageMesocycles', $client);
            $record = TrainingSplit::where('client_id', $client->id)->lockForUpdate()->findOrFail($split);
            $data = $request->validated();
            $originalDate = substr((string) $record->getRawOriginal('start_date'), 0, 10);
            $hasHistory = $record->selections()->exists() || $record->workouts()->get()->contains(fn ($workout) => $workout->hasPerformance());
            if ($hasHistory && ($originalDate !== $data['start_date'] || count($record->days) !== count($data['days']))) {
                throw ValidationException::withMessages(['days' => 'This split has recorded history. Create a new split to change its start date or length; you can still edit its workouts.']);
            }
            $this->checkStart($client, $data['start_date'], $record->id);
            $record->update($data);
            $this->clearFuture($client, min($originalDate, $data['start_date']));

            return $record;
        });

        return response()->json(['data' => $record]);
    }

    public function schedule(Request $request, User $client, TrainingSplitScheduler $scheduler): JsonResponse
    {
        Gate::authorize('viewTracker', $client);
        $data = $request->validate(['date' => ['nullable', 'date_format:Y-m-d']]);

        return response()->json(['data' => $scheduler->state($client, $data['date'] ?? today()->toDateString())]);
    }

    public function select(Request $request, User $client, TrainingSplitScheduler $scheduler): JsonResponse
    {
        Gate::authorize('manageCheckIns', $client);
        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'day_number' => ['present', 'nullable', 'integer', 'between:0,15'],
        ]);

        return response()->json(['data' => $scheduler->select($client, $data['date'], isset($data['day_number']) ? (int) $data['day_number'] : null)]);
    }

    private function checkStart(User $client, string $date, ?int $except = null): void
    {
        if (TrainingSplit::where('client_id', $client->id)->where('start_date', '>=', DateBounds::start($date))->where('start_date', '<', DateBounds::after($date))
            ->when($except !== null, fn ($query) => $query->whereKeyNot($except))->exists()) {
            throw ValidationException::withMessages(['start_date' => 'This client already has a split starting on that date. Edit it or choose another date.']);
        }
    }

    private function clearFuture(User $client, string $start): void
    {
        $client->workouts()->unlogged()->where('workout_date', '>=', max($start, today()->toDateString()))
            ->where(fn ($query) => $query->whereNotNull('training_split_id')->orWhereNotNull('mesocycle_id'))->delete();
    }
}
