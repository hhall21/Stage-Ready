<?php

namespace App\Services;

use App\Models\Mesocycle;
use Carbon\CarbonImmutable;

class MesocycleScheduler
{
    public function sync(Mesocycle $cycle, bool $editing): void
    {
        // Keep completed/logged sessions and every session before today.
        if ($editing) {
            $cycle->workouts()->whereDate('workout_date', '>=', today()->toDateString())
                ->where('completed', false)->whereNull('logged_at')
                ->whereDoesntHave('workoutExercises.sets', fn ($query) => $query->where('completed', true))
                ->whereDoesntHave('workoutExercises.sets.trainingClips')->delete();
        }
        $start = CarbonImmutable::parse($cycle->start_date)->startOfDay();
        $end = CarbonImmutable::parse($cycle->end_date)->startOfDay();
        for ($date = $start; $date->lte($end); $date = $date->addDay()) {
            if ($editing && $date->lt(CarbonImmutable::today())) {
                continue;
            }
            foreach (array_values($cycle->templates) as $slot => $template) {
                if ((int) $template['weekday'] !== $date->dayOfWeekIso) {
                    continue;
                }
                // A logged future session wins over the revised template.
                if ($cycle->workouts()->whereDate('workout_date', $date->toDateString())->where('template_slot', $slot)->exists()) {
                    continue;
                }
                $workout = $cycle->workouts()->create([
                    'client_id' => $cycle->client_id, 'trainer_id' => $cycle->trainer_id,
                    'workout_name' => $template['name'], 'workout_date' => $date->toDateString(),
                    'notes' => $template['notes'] ?? null, 'template_slot' => $slot,
                ]);
                foreach (array_values($template['exercises']) as $order => $exercise) {
                    $record = $workout->workoutExercises()->create(['exercise_id' => $exercise['exercise_id'], 'exercise_order' => $order + 1, 'notes' => $exercise['notes'] ?? null]);
                    foreach (array_values($exercise['sets']) as $number => $set) {
                        $record->sets()->create([...$set, 'set_number' => $number + 1, 'completed' => false]);
                    }
                }
            }
        }
    }
}
