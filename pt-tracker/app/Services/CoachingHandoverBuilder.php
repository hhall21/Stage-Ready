<?php

namespace App\Services;

use App\Models\TrainerClient;
use App\Models\User;
use App\Support\DateBounds;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * @phpstan-type HandoverNote array{title: string, period: string, body: string}
 * @phpstan-type HandoverSnapshot array{
 *     client_name: string, coach_name: string, discipline: string, start_date: string, end_date: string,
 *     weights: list<array{date: string, kg: float}>,
 *     weight_trend: list<array{date: string, average_kg: float|null, readings: int, change_from_7_days_ago_kg: float|null}>,
 *     completed_sessions: int, working_sets: int,
 *     personal_bests: list<array{exercise: string, weight_kg: float|null, reps: int, date: string}>,
 *     feedback: string|null, next_steps: string|null, notes: list<HandoverNote>
 * }
 */
class CoachingHandoverBuilder
{
    /**
     * @param  array{feedback?: string|null, next_steps?: string|null, notes?: list<HandoverNote>|null}  $input
     * @return HandoverSnapshot
     */
    public function build(User $coach, User $client, TrainerClient $assignment, array $input): array
    {
        $start = CarbonImmutable::parse($assignment->start_date)->startOfDay();
        $end = $assignment->status === 'inactive' && $assignment->end_date !== null
            ? CarbonImmutable::parse($assignment->end_date)->startOfDay()->min(CarbonImmutable::today())
            : CarbonImmutable::today();
        $weights = $client->dailyCheckIns()->whereNotNull('bodyweight')
            ->where('check_in_date', '>=', DateBounds::start($start))
            ->where('check_in_date', '<', DateBounds::after($end))->orderBy('check_in_date')->get()
            ->map(fn ($check) => ['date' => $check->check_in_date->toDateString(), 'kg' => (float) $check->bodyweight])->values()->all();

        $trend = [];
        $averages = [];
        $chartStart = $end->subDays(55)->max($start);
        for ($day = $chartStart->subDays(7)->max($start); $day->lte($end); $day = $day->addDay()) {
            $window = array_values(array_filter($weights, fn ($reading) => $reading['date'] >= $day->subDays(6)->toDateString() && $reading['date'] <= $day->toDateString()));
            $average = $window === [] ? null : round(array_sum(array_column($window, 'kg')) / count($window), 2);
            $prior = $averages[$day->subDays(7)->toDateString()] ?? null;
            $averages[$day->toDateString()] = $average;
            if ($day->gte($chartStart)) {
                $trend[] = ['date' => $day->toDateString(), 'average_kg' => $average, 'readings' => count($window),
                    'change_from_7_days_ago_kg' => $average !== null && $prior !== null ? round($average - $prior, 2) : null];
            }
        }

        $workouts = $client->workouts()->where('workout_date', '>=', DateBounds::start($start))
            ->where('workout_date', '<', DateBounds::after($end));
        $sets = DB::table('exercise_sets')->join('workout_exercises', 'workout_exercises.id', '=', 'exercise_sets.workout_exercise_id')
            ->join('exercises', 'exercises.id', '=', 'workout_exercises.exercise_id')
            ->whereIn('workout_exercises.workout_id', (clone $workouts)->select('id'))
            ->where('exercise_sets.completed', true)->where('exercise_sets.reps', '>', 0)
            ->join('workouts', 'workouts.id', '=', 'workout_exercises.workout_id')
            ->select('exercise_sets.id', 'exercise_sets.weight', 'exercise_sets.reps', 'exercises.id as exercise_id', 'exercises.exercise_name', 'workouts.workout_date')
            ->orderBy('exercises.exercise_name')->orderByRaw('COALESCE(exercise_sets.weight, 0) DESC')->orderByDesc('exercise_sets.reps')
            ->orderBy('workouts.workout_date')->orderBy('exercise_sets.id')->get();
        $bests = $sets->groupBy('exercise_id')->map(function ($exerciseSets): array {
            $best = $exerciseSets->first();

            return ['exercise' => (string) $best->exercise_name, 'weight_kg' => $best->weight === null ? null : (float) $best->weight,
                'reps' => (int) $best->reps, 'date' => substr((string) $best->workout_date, 0, 10)];
        })->values()->all();

        return ['client_name' => $client->name, 'coach_name' => $coach->name,
            'discipline' => $coach->is_bodybuilding_coach ? 'Bodybuilding' : 'Powerlifting',
            'start_date' => $start->toDateString(), 'end_date' => $end->toDateString(),
            'weights' => array_values($weights), 'weight_trend' => $trend,
            'completed_sessions' => (clone $workouts)->where('completed', true)->count(), 'working_sets' => $sets->count(),
            'personal_bests' => array_values($bests), 'feedback' => $input['feedback'] ?? null,
            'next_steps' => $input['next_steps'] ?? null, 'notes' => $input['notes'] ?? []];
    }
}
