<?php

namespace App\Services;

use App\Models\TrainingSplit;
use App\Models\User;
use App\Models\Workout;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class TrainingSplitScheduler
{
    public function active(User $client, CarbonImmutable $date): ?TrainingSplit
    {
        return TrainingSplit::where('client_id', $client->id)->whereDate('start_date', '<=', $date->toDateString())
            ->orderByDesc('start_date')->first();
    }

    public function expectedDay(TrainingSplit $split, CarbonImmutable $date): int
    {
        $anchor = CarbonImmutable::parse($split->getAttribute('start_date'))->startOfDay();
        $next = 1;
        $length = count($split->days);
        foreach ($split->selections()->whereDate('calendar_date', '<', $date->toDateString())->orderBy('calendar_date')->get() as $selection) {
            $selectedDate = CarbonImmutable::parse($selection->getAttribute('calendar_date'))->startOfDay();
            $expected = ($next - 1 + (int) $anchor->diffInDays($selectedDate)) % $length + 1;
            $selected = (int) $selection->getAttribute('day_number');
            // Extra rest consumes a calendar day without consuming a split day.
            $next = $selected === 0 ? $expected : $selected % $length + 1;
            $anchor = $selectedDate->addDay();
        }

        return ($next - 1 + (int) $anchor->diffInDays($date)) % $length + 1;
    }

    public function dayType(User $client, string $date): ?string
    {
        $calendarDate = CarbonImmutable::parse($date)->startOfDay();
        $split = $this->active($client, $calendarDate);
        if (! $split) {
            return null;
        }
        $workout = $split->workouts()->whereDate('workout_date', $date)->first();
        if ($workout && ($workout->hasPerformance() || $calendarDate->lt(CarbonImmutable::today()))) {
            return 'training';
        }
        $selection = $split->selections()->whereDate('calendar_date', $date)->first()?->getAttribute('day_number');
        if ($selection === 0) {
            return 'rest';
        }
        $number = $selection ?? $this->expectedDay($split, $calendarDate);

        return $split->days[$number - 1]['is_rest_day'] ? 'rest' : 'training';
    }

    /** @return array<string, mixed> */
    public function state(User $client, string $date): array
    {
        return DB::transaction(function () use ($client, $date): array {
            User::whereKey($client->id)->lockForUpdate()->firstOrFail();
            Gate::authorize('viewTracker', $client);

            return $this->stateLocked($client, CarbonImmutable::parse($date)->startOfDay());
        }, 5);
    }

    /** @return array<string, mixed> */
    public function select(User $client, string $date, ?int $dayNumber): array
    {
        return DB::transaction(function () use ($client, $date, $dayNumber): array {
            User::whereKey($client->id)->lockForUpdate()->firstOrFail();
            Gate::authorize('manageCheckIns', $client);
            $calendarDate = CarbonImmutable::parse($date)->startOfDay();
            $split = $this->active($client, $calendarDate);
            if (! $split) {
                throw ValidationException::withMessages(['date' => 'There is no training split on this date.']);
            }
            if ($dayNumber !== null && $dayNumber > count($split->days)) {
                throw ValidationException::withMessages(['day_number' => 'Choose a day from this client’s split.']);
            }
            $existing = $split->selections()->whereDate('calendar_date', $date)->first();
            if (($existing === null && $dayNumber === null) || ($existing !== null && $existing->getAttribute('day_number') === $dayNumber)) {
                return $this->stateLocked($client, $calendarDate);
            }
            $workout = $split->workouts()->whereDate('workout_date', $date)->first();
            if ($workout?->hasPerformance()) {
                abort(409, 'This day already has logged performance. Its workout is preserved. Choose the split day before logging performance.');
            }
            if ($split->workouts()->whereDate('workout_date', '>', $date)->get()->contains(fn (Workout $session): bool => $session->hasPerformance())) {
                abort(409, 'Later workouts have already been logged. Change today’s split day instead to preserve that history.');
            }
            if ($dayNumber === null) {
                $existing->delete();
            } else {
                if ($existing) {
                    $existing->update(['day_number' => $dayNumber]);
                } else {
                    $split->selections()->create(['calendar_date' => $date, 'day_number' => $dayNumber]);
                }
            }
            // Replace this unlogged session; later materialised sessions will be
            // recreated using the new anchor. Never delete recorded performance.
            $split->workouts()->unlogged()->whereDate('workout_date', $date)->delete();
            $split->workouts()->unlogged()->whereDate('workout_date', '>', $date)
                ->where('workout_date', '>=', today()->toDateString())->delete();

            return $this->stateLocked($client, $calendarDate);
        }, 5);
    }

    /** @return array<string, mixed> */
    private function stateLocked(User $client, CarbonImmutable $date): array
    {
        $split = $this->active($client, $date);
        if (! $split) {
            return ['date' => $date->toDateString(), 'split' => null, 'workout' => null,
                'next_split' => TrainingSplit::where('client_id', $client->id)->whereDate('start_date', '>', $date->toDateString())->orderBy('start_date')->first()];
        }
        $expected = $this->expectedDay($split, $date);
        $selection = $split->selections()->whereDate('calendar_date', $date->toDateString())->first();
        $selected = $selection?->getAttribute('day_number');
        $number = $selected === null || $selected === 0 ? $expected : (int) $selected;
        $day = $split->days[$number - 1];
        $rest = $selected === 0 || (bool) $day['is_rest_day'];
        $workout = $split->workouts()->whereDate('workout_date', $date->toDateString())->first();
        $locked = $workout?->hasPerformance() ?? false;
        $snapshot = $workout && ($locked || $date->lt(CarbonImmutable::today()));
        if ($snapshot) {
            $rest = false;
            $number = (int) $workout->getAttribute('split_day_number');
        } elseif (! $rest && ! $workout) {
            $workout = $split->workouts()->create([
                'client_id' => $client->id, 'trainer_id' => $split->trainer_id,
                'workout_name' => $day['name'], 'workout_date' => $date->toDateString(),
                'split_day_number' => $number, 'notes' => $day['notes'] ?? null,
            ]);
            foreach ($day['exercises'] as $index => $exercise) {
                $entry = $workout->workoutExercises()->create([
                    'exercise_id' => $exercise['exercise_id'], 'exercise_order' => $index + 1, 'notes' => $exercise['notes'] ?? null,
                ]);
                foreach ($exercise['sets'] as $setIndex => $set) {
                    $entry->sets()->create([...$set, 'set_number' => $setIndex + 1]);
                }
            }
        }
        $tomorrow = $date->addDay();
        $nextSplit = $this->active($client, $tomorrow);
        $nextNumber = $nextSplit ? $this->expectedDay($nextSplit, $tomorrow) : null;
        $nextSelection = $nextSplit?->selections()->whereDate('calendar_date', $tomorrow->toDateString())->first();
        if ($nextSelection && $nextSelection->getAttribute('day_number') !== 0) {
            $nextNumber = (int) $nextSelection->getAttribute('day_number');
        }
        $nextDay = $nextSplit && $nextNumber ? $nextSplit->days[$nextNumber - 1] : null;

        return [
            'date' => $date->toDateString(), 'split' => $split,
            'expected_day' => $expected, 'day_number' => $number, 'selection' => $selected,
            'is_rest_day' => $rest, 'is_extra_rest' => $selected === 0,
            'day_name' => $snapshot ? $workout->workout_name : ($rest ? 'Rest day' : $day['name']),
            'day_notes' => $snapshot ? $workout->notes : ($day['notes'] ?? null),
            'history_locked' => $locked,
            'workout' => $workout?->refresh()->load('workoutExercises.exercise', 'workoutExercises.sets'),
            'tomorrow' => $nextDay ? ['day_number' => $nextNumber, 'name' => $nextDay['is_rest_day'] || $nextSelection?->getAttribute('day_number') === 0 ? 'Rest day' : $nextDay['name'], 'split_name' => $nextSplit->name] : null,
        ];
    }
}
