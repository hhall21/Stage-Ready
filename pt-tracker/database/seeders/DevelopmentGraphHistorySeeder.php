<?php

namespace Database\Seeders;

use App\Models\User;
use App\Support\DateBounds;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DevelopmentGraphHistorySeeder extends Seeder
{
    private const MARKER = 'Fictional graph history v1';

    private const SESSION_OFFSETS = [40, 36, 32, 28, 24, 20, 16, 12, 8, 6, 4, 2];

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Graph history can only be added in local and testing environments.');
        }

        foreach (User::where('role', 'client')->orderBy('id')->get() as $client) {
            DB::transaction(function () use ($client): void {
                User::whereKey($client->id)->lockForUpdate()->firstOrFail();
                $this->history($client);
            });
        }
    }

    private function history(User $client): void
    {
        $firstRun = DB::table('workouts')->where('client_id', $client->id)
            ->where(fn ($query) => $query->where('workout_name', 'like', 'Graph history (sample) / %')->orWhere('notes', 'like', self::MARKER.'%'))
            ->min('created_at');
        $anchor = $firstRun === null ? CarbonImmutable::today() : CarbonImmutable::parse($firstRun)->startOfDay();
        $coachId = DB::table('trainer_clients')->where('client_id', $client->id)->where('status', 'active')->orderByDesc('start_date')->value('trainer_id');
        $latestWeight = DB::table('daily_check_ins')->where('client_id', $client->id)->where('bodyweight', '>', 0)
            ->where('check_in_date', '<', $anchor->toDateString())->orderByDesc('check_in_date')->value('bodyweight');
        $baseline = $latestWeight === null ? 78 + $client->id % 8 : (float) $latestWeight;
        foreach (range(42, 1) as $offset) {
            $date = $anchor->subDays($offset)->toDateString();
            // Existing blank/partial check-ins and submitted pose reviews stay intact.
            if (DB::table('daily_check_ins')->where('client_id', $client->id)
                ->where('check_in_date', '>=', $date)->where('check_in_date', '<', DateBounds::after($date))->exists()) {
                continue;
            }
            DB::table('daily_check_ins')->insert([
                'client_id' => $client->id, 'check_in_date' => $date,
                'bodyweight' => round($baseline + $offset * 0.025 + sin($offset + $client->id) * 0.16, 2),
                'notes' => self::MARKER.'. Sample weigh-in, not an actual measurement.',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $exercises = DB::table('exercises')->whereIn('id', DB::table('workout_exercises')->join('workouts', 'workouts.id', '=', 'workout_exercises.workout_id')
            ->where('workouts.client_id', $client->id)->select('workout_exercises.exercise_id'))->orderBy('id')->get(['id', 'exercise_name']);
        if ($exercises->isEmpty()) {
            $exercises = DB::table('exercises')->orderBy('id')->limit(4)->get(['id', 'exercise_name']);
        }
        if ($exercises->isEmpty()) {
            $id = DB::table('exercises')->insertGetId(['exercise_name' => 'Machine chest press (sample)',
                'muscle_group' => 'Chest', 'description' => 'Fictional exercise for graph history.', 'created_at' => now(), 'updated_at' => now()]);
            $exercises = DB::table('exercises')->where('id', $id)->get(['id', 'exercise_name']);
        }

        $loads = DB::table('exercise_sets')->join('workout_exercises', 'workout_exercises.id', '=', 'exercise_sets.workout_exercise_id')
            ->join('workouts', 'workouts.id', '=', 'workout_exercises.workout_id')->where('workouts.client_id', $client->id)
            ->where('workouts.workout_date', '<', $anchor->toDateString())->where('exercise_sets.completed', true)->where('exercise_sets.reps', '>', 0)
            ->select('workout_exercises.exercise_id')->selectRaw('MAX(exercise_sets.weight) AS weight')->groupBy('workout_exercises.exercise_id')->get()->keyBy('exercise_id');
        foreach (self::SESSION_OFFSETS as $index => $offset) {
            $date = $anchor->subDays($offset);
            foreach ($exercises->chunk(10)->values() as $batch => $entries) {
                $name = sprintf('Graph history (sample) / Session %02d / Group %02d', $index + 1, $batch + 1);
                $notes = self::MARKER.sprintf('; session %02d; group %02d.', $index + 1, $batch + 1);
                if (DB::table('workouts')->where('client_id', $client->id)
                    ->where(fn ($query) => $query->where('workout_name', $name)->orWhere('notes', $notes))->exists()) {
                    continue;
                }
                $workoutId = DB::table('workouts')->insertGetId([
                    'client_id' => $client->id, 'trainer_id' => $coachId, 'workout_name' => $name, 'workout_date' => $date->toDateString(),
                    'completed' => true, 'logged_at' => $date->setTime(18, 0), 'notes' => $notes,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                foreach ($entries->values() as $order => $exercise) {
                    $entryId = DB::table('workout_exercises')->insertGetId([
                        'workout_id' => $workoutId, 'exercise_id' => $exercise->id, 'exercise_order' => $order + 1,
                        'notes' => self::MARKER.'. Completed sample working sets.', 'created_at' => now(), 'updated_at' => now(),
                    ]);
                    $bodyweight = preg_match('/push[ -]?up|pull[ -]?up|chin[ -]?up|plank|bodyweight/i', $exercise->exercise_name) === 1;
                    $load = (float) ($loads->get($exercise->id)->weight ?? (35 + $exercise->id % 6 * 5));
                    $weight = $bodyweight ? null : max(2.5, round(($load - 7.5 + intdiv($index, 3) * 2.5) / 2.5) * 2.5);
                    $sets = [];
                    foreach (range(1, 3) as $number) {
                        $sets[] = ['workout_exercise_id' => $entryId, 'set_number' => $number, 'weight' => $weight,
                            'reps' => 8 + $index % 3 + intdiv($index, 4) - ($number === 3 ? 1 : 0),
                            'rir' => $number === 3 ? 1 : 2, 'completed' => true, 'notes' => self::MARKER.'.',
                            'created_at' => now(), 'updated_at' => now()];
                    }
                    DB::table('exercise_sets')->insert($sets);
                }
            }
            $this->foodLog($client->id, $date, $index);
        }
    }

    private function foodLog(int $clientId, CarbonImmutable $date, int $index): void
    {
        // Do not add invented intake to a date with an existing complete or partial log.
        if (DB::table('meals')->where('client_id', $clientId)->where('meal_date', '>=', $date->toDateString())
            ->where('meal_date', '<', $date->addDay()->toDateString())->exists()) {
            return;
        }
        $total = 2200 + $clientId % 7 * 40 + $index * 15 + ($index % 3 - 1) * 70;
        $calories = [(int) round($total * 0.25), (int) round($total * 0.4)];
        $calories[] = $total - array_sum($calories);
        foreach (['breakfast', 'lunch', 'dinner'] as $mealIndex => $type) {
            $mealId = DB::table('meals')->insertGetId(['client_id' => $clientId, 'meal_date' => $date->toDateString(), 'meal_type' => $type,
                'notes' => self::MARKER.'. Fictional calorie log for chart testing.', 'created_at' => now(), 'updated_at' => now()]);
            DB::table('meal_items')->insert(['meal_id' => $mealId, 'food_name' => 'Fictional sample '.$type,
                'quantity' => 1, 'unit' => 'serving', 'calories' => $calories[$mealIndex], 'created_at' => now(), 'updated_at' => now()]);
        }
    }
}
