<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ExerciseProgressController extends Controller
{
    public function __invoke(Request $request, User $client): JsonResponse
    {
        Gate::authorize('viewTracker', $client);
        $data = $request->validate(['exercise_id' => ['nullable', 'integer', 'exists:exercises,id']]);
        $query = DB::table('exercise_sets')->join('workout_exercises', 'workout_exercises.id', '=', 'exercise_sets.workout_exercise_id')
            ->join('workouts', 'workouts.id', '=', 'workout_exercises.workout_id')
            ->join('exercises', 'exercises.id', '=', 'workout_exercises.exercise_id')
            ->where('workouts.client_id', $client->id)->where('workouts.workout_date', '<', today()->addDay()->toDateString())
            ->where('exercise_sets.completed', true)->where('exercise_sets.reps', '>', 0);
        $exercises = (clone $query)->select('exercises.id', 'exercises.exercise_name')->distinct()->orderBy('exercises.exercise_name')->get();
        $exerciseId = isset($data['exercise_id']) ? (int) $data['exercise_id'] : $exercises->first()?->id;
        $points = $exerciseId === null ? collect() : (clone $query)->where('exercises.id', $exerciseId)
            ->select('workouts.id', 'workouts.workout_date', 'workouts.workout_name')
            ->selectRaw('MAX(exercise_sets.weight) as max_weight_kg, MAX(exercise_sets.reps) as max_reps, COUNT(*) as working_sets')
            ->groupBy('workouts.id', 'workouts.workout_date', 'workouts.workout_name')
            ->orderByDesc('workouts.workout_date')->orderByDesc('workouts.id')->limit(60)->get()->reverse()->values()
            ->map(fn (object $point) => ['workout_id' => (int) $point->id, 'date' => substr($point->workout_date, 0, 10), 'workout_name' => $point->workout_name,
                'max_weight_kg' => $point->max_weight_kg === null ? null : round((float) $point->max_weight_kg, 2), 'max_reps' => (int) $point->max_reps,
                'working_sets' => (int) $point->working_sets]);

        return response()->json(['exercises' => $exercises, 'exercise_id' => $exerciseId, 'data' => $points]);
    }
}
