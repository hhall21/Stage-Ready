<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveWorkoutRequest;
use App\Models\ExerciseSet;
use App\Models\User;
use App\Models\Workout;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class WorkoutController extends Controller
{
    public function index(User $client): JsonResponse
    {
        Gate::authorize('viewTracker', $client);

        return response()->json($client->workouts()->with('workoutExercises.exercise', 'workoutExercises.sets')
            ->orderByRaw('CASE WHEN workout_date >= ? THEN 0 ELSE 1 END', [today()->toDateString()])
            ->orderByRaw('CASE WHEN workout_date >= ? THEN workout_date END ASC', [today()->toDateString()])
            ->orderByDesc('workout_date')->paginate(30));
    }

    public function show(User $client, int $workout): JsonResponse
    {
        Gate::authorize('viewTracker', $client);

        return response()->json(['data' => $client->workouts()->with('workoutExercises.exercise', 'workoutExercises.sets')->findOrFail($workout)]);
    }

    public function store(SaveWorkoutRequest $request, User $client): JsonResponse
    {
        Gate::authorize('manageWorkouts', $client);
        $data = $request->validated();
        $record = DB::transaction(function () use ($client, $request, $data) {
            $record = $client->workouts()->create([
                ...Arr::except($data, 'exercises'),
                'trainer_id' => $request->user()?->role === 'coach' ? $request->user()->id : null,
            ]);
            $this->saveExercises($record, $data['exercises'] ?? []);

            return $record;
        });

        return response()->json(['data' => $record->load('workoutExercises.exercise', 'workoutExercises.sets')], 201);
    }

    public function update(SaveWorkoutRequest $request, User $client, int $workout): JsonResponse
    {
        Gate::authorize('manageWorkouts', $client);
        $data = $request->validated();
        $record = DB::transaction(function () use ($client, $data, $workout, $request) {
            User::whereKey($client->id)->lockForUpdate()->firstOrFail();
            $record = $client->workouts()->lockForUpdate()->findOrFail($workout);
            if ((($data['completed'] ?? false) || $request->user()?->role === 'client') && $record->getAttribute('logged_at') === null) {
                $record->forceFill(['logged_at' => now()]);
            }
            $record->update(Arr::except($data, 'exercises'));
            if (array_key_exists('exercises', $data)) {
                $record->workoutExercises()->delete();
                $this->saveExercises($record, $data['exercises']);
            }

            return $record;
        });

        return response()->json(['data' => $record->load('workoutExercises.exercise', 'workoutExercises.sets')]);
    }

    public function destroy(User $client, int $workout): JsonResponse
    {
        Gate::authorize('manageWorkouts', $client);
        $client->workouts()->findOrFail($workout)->delete();

        return response()->json(null, 204);
    }

    public function logSet(Request $request, User $client, int $workout, int $set): JsonResponse
    {
        Gate::authorize('manageCheckIns', $client);
        $data = $request->validate([
            'reps' => ['nullable', 'integer', 'min:0', 'max:4294967295'],
            'weight' => ['nullable', 'numeric', 'min:0', 'max:99999.99'],
            'rir' => ['nullable', 'numeric', 'min:0', 'max:99.9'],
            'completed' => ['required', 'boolean'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
        $record = DB::transaction(function () use ($client, $workout, $set, $data) {
            User::whereKey($client->id)->lockForUpdate()->firstOrFail();
            $session = $client->workouts()->lockForUpdate()->findOrFail($workout);
            $record = ExerciseSet::whereHas('workoutExercise', fn ($query) => $query->where('workout_id', $session->id))->findOrFail($set);
            $record->update($data);
            $session->forceFill(['logged_at' => $session->getAttribute('logged_at') ?? now()])->save();

            return $record;
        });

        return response()->json(['data' => $record]);
    }

    /** @param array<int, array<string, mixed>> $exercises */
    private function saveExercises(Workout $workout, array $exercises): void
    {
        foreach (array_values($exercises) as $index => $exercise) {
            $record = $workout->workoutExercises()->create([
                'exercise_id' => $exercise['exercise_id'],
                'exercise_order' => $index + 1,
                'notes' => $exercise['notes'] ?? null,
            ]);
            foreach (array_values($exercise['sets'] ?? []) as $setIndex => $set) {
                $record->sets()->create([...$set, 'set_number' => $setIndex + 1]);
            }
        }
    }
}
