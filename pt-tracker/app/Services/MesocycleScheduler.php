<?php

namespace App\Services;

use App\Models\Mesocycle;
use Carbon\CarbonImmutable;
use LogicException;

class MesocycleScheduler
{
    // Stay below SQLite's parameter limit as well as MySQL's packet limit.
    private const BATCH_SIZE = 50;

    public function sync(Mesocycle $cycle, bool $editing): void
    {
        // Keep completed/logged sessions and every session before today.
        if ($editing) {
            $cycle->workouts()->where('workout_date', '>=', today()->toDateString())
                ->where('completed', false)->whereNull('logged_at')
                ->whereDoesntHave('workoutExercises.sets', fn ($query) => $query->where('completed', true))
                ->whereDoesntHave('workoutExercises.sets.trainingClips')->delete();
        }
        $start = CarbonImmutable::parse($cycle->start_date)->startOfDay();
        $end = CarbonImmutable::parse($cycle->end_date)->startOfDay();
        $today = CarbonImmutable::today();
        $existing = $cycle->workouts()->get(['workout_date', 'template_slot'])
            ->mapWithKeys(fn ($workout) => [$workout->workout_date->toDateString().':'.$workout->template_slot => true])->all();
        $templates = array_values($cycle->templates);
        $timestamp = now()->toDateTimeString();
        $workouts = [];
        for ($date = $start; $date->lte($end); $date = $date->addDay()) {
            if ($editing && $date->lt($today)) {
                continue;
            }
            foreach ($templates as $slot => $template) {
                if ((int) $template['weekday'] !== $date->dayOfWeekIso) {
                    continue;
                }
                // A logged future session wins over the revised template.
                if (isset($existing[$date->toDateString().':'.$slot])) {
                    continue;
                }
                $workouts[] = [
                    'client_id' => $cycle->client_id, 'trainer_id' => $cycle->trainer_id,
                    'workout_name' => $template['name'], 'workout_date' => $date->toDateString(),
                    'notes' => $template['notes'] ?? null, 'template_slot' => $slot,
                    'mesocycle_id' => $cycle->id, 'completed' => false,
                    'created_at' => $timestamp, 'updated_at' => $timestamp,
                ];
            }
        }
        if ($workouts === []) {
            return;
        }

        $connection = $cycle->getConnection();
        foreach (array_chunk($workouts, self::BATCH_SIZE) as $batch) {
            $connection->table('workouts')->insert($batch);
        }

        // Read generated IDs back by their session keys; never assume consecutive IDs.
        $created = $cycle->workouts()->get(['id', 'workout_date', 'template_slot'])
            ->reject(fn ($workout) => isset($existing[$workout->workout_date->toDateString().':'.$workout->template_slot]));
        $exercises = [];
        $slots = [];
        foreach ($created as $workout) {
            if ($workout->template_slot === null || ! isset($templates[$workout->template_slot])) {
                throw new LogicException('Scheduled workout refers to a missing mesocycle template.');
            }
            $slots[$workout->id] = $workout->template_slot;
            foreach (array_values($templates[$workout->template_slot]['exercises']) as $order => $exercise) {
                $exercises[] = [
                    'workout_id' => $workout->id, 'exercise_id' => $exercise['exercise_id'],
                    'exercise_order' => $order + 1, 'notes' => $exercise['notes'] ?? null,
                    'created_at' => $timestamp, 'updated_at' => $timestamp,
                ];
            }
        }
        foreach (array_chunk($exercises, self::BATCH_SIZE) as $batch) {
            $connection->table('workout_exercises')->insert($batch);
        }

        $sets = [];
        foreach (array_chunk(array_keys($slots), self::BATCH_SIZE) as $workoutIds) {
            $records = $connection->table('workout_exercises')->whereIn('workout_id', $workoutIds)
                ->get(['id', 'workout_id', 'exercise_order']);
            foreach ($records as $record) {
                $exercise = array_values($templates[$slots[$record->workout_id]]['exercises'])[$record->exercise_order - 1];
                foreach (array_values($exercise['sets']) as $number => $set) {
                    $sets[] = [
                        'workout_exercise_id' => $record->id, 'set_number' => $number + 1,
                        'reps' => $set['reps'] ?? null, 'weight' => $set['weight'] ?? null,
                        'rir' => $set['rir'] ?? null, 'completed' => false,
                        'created_at' => $timestamp, 'updated_at' => $timestamp,
                    ];
                    if (count($sets) === self::BATCH_SIZE) {
                        $connection->table('exercise_sets')->insert($sets);
                        $sets = [];
                    }
                }
            }
        }
        if ($sets !== []) {
            $connection->table('exercise_sets')->insert($sets);
        }
    }
}
