<?php

namespace App\Services;

use App\Models\User;
use App\Support\DateBounds;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TrainingPerformanceContext
{
    /**
     * @param  Collection<int, non-empty-array<string, mixed>>  $sessions
     * @return Collection<int, non-empty-array<string, mixed>>
     */
    public function attach(User $client, Collection $sessions): Collection
    {
        if ($sessions->isEmpty()) {
            return $sessions;
        }

        $first = CarbonImmutable::parse($sessions->first()['date']);
        $last = (string) $sessions->last()['date'];
        $weights = DB::table('daily_check_ins')->where('client_id', $client->id)->whereNotNull('bodyweight')
            ->where('check_in_date', '>=', DateBounds::start($first->subDays(13)->toDateString()))->where('check_in_date', '<', DateBounds::after($last))
            ->selectRaw('DATE(check_in_date) AS check_in_date, bodyweight')->get();
        $calories = DB::table('meals')->leftJoin('meal_items', 'meal_items.meal_id', '=', 'meals.id')
            ->where('meals.client_id', $client->id)->where('meals.meal_date', '>=', DateBounds::start($first->toDateString()))->where('meals.meal_date', '<', DateBounds::after($last))
            ->selectRaw('DATE(meals.meal_date) AS meal_date')
            ->selectRaw('SUM(meal_items.calories) AS subtotal, COUNT(meal_items.id) AS item_count, COUNT(meal_items.calories) AS readings')
            ->selectRaw('COUNT(DISTINCT meals.id) AS meal_count, COUNT(DISTINCT CASE WHEN meal_items.id IS NOT NULL THEN meals.id END) AS meals_with_items')
            ->groupByRaw('DATE(meals.meal_date)')->get()->keyBy('meal_date');
        $plans = DB::table('nutrition_plans')->where('client_id', $client->id)->whereNull('cancelled_at')
            ->where('effective_from', '<', DateBounds::after($last))->orderByDesc('effective_from')->orderByDesc('id')
            ->get(['effective_from', 'training_meals']);

        return $sessions->map(function (array $session) use ($weights, $calories, $plans): array {
            $date = CarbonImmutable::parse($session['date']);
            $from = $date->subDays(6)->toDateString();
            $to = $date->toDateString();
            $window = $weights->filter(fn (object $reading): bool => $reading->check_in_date >= $from && $reading->check_in_date <= $to);
            $priorFrom = $date->subDays(13)->toDateString();
            $priorTo = $date->subDays(7)->toDateString();
            $prior = $weights->filter(fn (object $reading): bool => $reading->check_in_date >= $priorFrom && $reading->check_in_date <= $priorTo);
            $average = $window->isEmpty() ? null : round((float) $window->avg('bodyweight'), 2);
            $priorAverage = $prior->isEmpty() ? null : round((float) $prior->avg('bodyweight'), 2);
            $food = $calories->get($to);
            $itemCount = (int) ($food->item_count ?? 0);
            $itemReadings = (int) ($food->readings ?? 0);
            // An incomplete food log is a subtotal, never a complete calorie total.
            $loggedComplete = $food !== null && $itemReadings > 0 && $itemReadings === $itemCount && $food->meal_count === $food->meals_with_items;
            $subtotal = $food !== null && $itemReadings > 0 ? round((float) $food->subtotal, 2) : null;
            $plan = $plans->first(fn (object $candidate): bool => substr($candidate->effective_from, 0, 10) <= $to);
            // These sessions contain logged training, so use that date's training-day prescription.
            $decoded = $plan === null ? [] : json_decode($plan->training_meals, true, 512, JSON_THROW_ON_ERROR);
            $meals = collect(is_array($decoded) ? $decoded : []);
            $mealReadings = $meals->filter(fn (mixed $meal): bool => is_array($meal) && isset($meal['calories']) && is_numeric($meal['calories']));
            $prescribed = $meals->isNotEmpty() && $mealReadings->count() === $meals->count()
                ? round((float) $mealReadings->sum('calories'), 2) : null;

            return [...$session,
                'prescribed_calories_kcal' => $prescribed,
                'prescribed_meal_readings' => $mealReadings->count(), 'prescribed_meal_count' => $meals->count(),
                'logged_calories_kcal' => $loggedComplete ? $subtotal : null,
                'logged_calorie_subtotal_kcal' => $subtotal,
                'logged_item_readings' => $itemReadings, 'logged_item_count' => $itemCount,
                'logged_meal_count' => (int) ($food->meal_count ?? 0),
                'average_bodyweight_kg' => $average, 'bodyweight_readings' => $window->count(),
                'bodyweight_change_from_7_days_ago_kg' => $average !== null && $priorAverage !== null ? round($average - $priorAverage, 2) : null,
                'prior_bodyweight_readings' => $prior->count(),
            ];
        });
    }
}
