<?php

namespace Tests\Feature;

use App\Models\Exercise;
use App\Models\NutritionPlan;
use App\Models\TrainerClient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrainingAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
        $this->travelTo(now()->setDate(2026, 10, 23)->setTime(12, 0));
    }

    public function test_coach_compares_logged_performance_with_same_day_calories_and_historical_weight_averages(): void
    {
        [$coach, $client, $exercise] = $this->pair();
        $this->loggedSession($client, $exercise, '2026-10-01');
        $workout = $this->loggedSession($client, $exercise, '2026-10-23');
        $entry = $client->workouts()->findOrFail($workout)->workoutExercises()->firstOrFail();
        $entry->sets()->create(['set_number' => 2, 'reps' => 12, 'weight' => null, 'completed' => true]);
        $entry->sets()->create(['set_number' => 3, 'reps' => 99, 'weight' => 999, 'completed' => false]);
        $entry->sets()->create(['set_number' => 4, 'reps' => 0, 'weight' => 999, 'completed' => true]);
        $this->loggedSession($client, $exercise, '2026-10-24');
        foreach (['2026-10-10' => 74, '2026-10-16' => 76, '2026-10-17' => 80, '2026-10-19' => null, '2026-10-23' => 82, '2026-10-24' => 200] as $date => $weight) {
            $client->dailyCheckIns()->create(['check_in_date' => $date, 'bodyweight' => $weight]);
        }
        $meal = $client->meals()->create(['meal_date' => '2026-10-23', 'meal_type' => 'breakfast']);
        $meal->items()->createMany([['food_name' => 'Oats', 'calories' => 400], ['food_name' => 'Yoghurt', 'calories' => 200]]);
        $meal = $client->meals()->create(['meal_date' => '2026-10-23', 'meal_type' => 'lunch']);
        $meal->items()->create(['food_name' => 'Rice', 'calories' => 300]);
        $outsider = User::factory()->create();
        $outsider->dailyCheckIns()->create(['check_in_date' => '2026-10-23', 'bodyweight' => 999]);
        $otherMeal = $outsider->meals()->create(['meal_date' => '2026-10-23', 'meal_type' => 'dinner']);
        $otherMeal->items()->create(['food_name' => 'Private food', 'calories' => 9999]);

        $this->actingAs($coach)->getJson($this->url($client))->assertOk()->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.average_bodyweight_kg', null)->assertJsonPath('data.0.bodyweight_readings', 0)
            ->assertJsonPath('data.0.logged_calories_kcal', null)->assertJsonPath('data.0.prescribed_calories_kcal', null)
            ->assertJsonPath('data.1.workout_id', $workout)->assertJsonPath('data.1.max_weight_kg', 100)
            ->assertJsonPath('data.1.max_reps', 12)->assertJsonPath('data.1.working_sets', 2)
            ->assertJsonPath('data.1.logged_calories_kcal', 900)->assertJsonPath('data.1.logged_item_readings', 3)
            ->assertJsonPath('data.1.logged_item_count', 3)->assertJsonPath('data.1.logged_meal_count', 2)
            ->assertJsonPath('data.1.average_bodyweight_kg', 81)->assertJsonPath('data.1.bodyweight_readings', 2)
            ->assertJsonPath('data.1.bodyweight_change_from_7_days_ago_kg', 6)->assertJsonPath('data.1.prior_bodyweight_readings', 2);
    }

    public function test_prescribed_calories_use_the_version_effective_on_each_training_date(): void
    {
        [$coach, $client, $exercise] = $this->pair();
        $this->loggedSession($client, $exercise, '2026-10-10');
        $this->loggedSession($client, $exercise, '2026-10-23');
        $this->plan($client, $coach, '2026-10-01', [1000, 1000]);
        $this->plan($client, $coach, '2026-10-20', [1100, 1100]);
        $this->plan($client, $coach, '2026-10-24', [9999]);
        $cancelled = $this->plan($client, $coach, '2026-10-20', [8888]);
        $cancelled->forceFill(['cancelled_at' => now()])->save();
        $this->plan(User::factory()->create(), $coach, '2026-10-01', [7777]);
        $this->actingAs($coach)->getJson($this->url($client))->assertOk()
            ->assertJsonPath('data.0.prescribed_calories_kcal', 2000)
            ->assertJsonPath('data.1.prescribed_calories_kcal', 2200)
            ->assertJsonPath('data.1.prescribed_meal_readings', 2)->assertJsonPath('data.1.prescribed_meal_count', 2);
    }

    public function test_missing_calories_remain_missing_subtotals_are_identified_and_recorded_zero_is_valid(): void
    {
        [$coach, $client, $exercise] = $this->pair();
        foreach (['2026-10-21', '2026-10-22', '2026-10-23'] as $date) {
            $this->loggedSession($client, $exercise, $date);
        }
        $zero = $client->meals()->create(['meal_date' => '2026-10-21', 'meal_type' => 'breakfast']);
        $zero->items()->create(['food_name' => 'Recorded zero', 'calories' => 0]);
        $partial = $client->meals()->create(['meal_date' => '2026-10-22', 'meal_type' => 'lunch']);
        $partial->items()->createMany([['food_name' => 'Recorded', 'calories' => 500], ['food_name' => 'Unknown', 'calories' => null]]);
        $emptyDate = $client->meals()->create(['meal_date' => '2026-10-23', 'meal_type' => 'dinner']);
        $emptyDate->items()->create(['food_name' => 'Recorded', 'calories' => 300]);
        $client->meals()->create(['meal_date' => '2026-10-23', 'meal_type' => 'snack']);
        $this->plan($client, $coach, '2026-10-21', [0]);
        $this->plan($client, $coach, '2026-10-22', [500, null]);
        $this->actingAs($coach)->getJson($this->url($client))->assertOk()
            ->assertJsonPath('data.0.logged_calories_kcal', 0)->assertJsonPath('data.0.prescribed_calories_kcal', 0)
            ->assertJsonPath('data.1.logged_calories_kcal', null)->assertJsonPath('data.1.logged_calorie_subtotal_kcal', 500)
            ->assertJsonPath('data.1.logged_item_readings', 1)->assertJsonPath('data.1.logged_item_count', 2)
            ->assertJsonPath('data.1.prescribed_calories_kcal', null)->assertJsonPath('data.1.prescribed_meal_readings', 1)
            ->assertJsonPath('data.2.logged_calories_kcal', null)->assertJsonPath('data.2.logged_meal_count', 2)
            ->assertJsonPath('data.2.average_bodyweight_kg', null);
    }

    public function test_history_ranges_are_validated_and_exercise_choices_remain_available_outside_the_range(): void
    {
        [$coach, $client, $exercise] = $this->pair();
        $this->loggedSession($client, $exercise, '2026-09-01');
        $this->loggedSession($client, $exercise, '2026-10-23');
        $this->actingAs($coach)->getJson($this->url($client).'?from=2026-10-01&to=2026-10-23')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.date', '2026-10-23');
        $this->getJson($this->url($client).'?from=2026-10-01&to=2026-10-10')->assertOk()->assertJsonCount(0, 'data')->assertJsonCount(1, 'exercises');
        foreach (['from=bad', 'from=2026-10-24', 'to=2026-10-24', 'from=2026-10-23&to=2026-10-01'] as $invalid) {
            $this->getJson($this->url($client).'?'.$invalid)->assertUnprocessable();
        }
    }

    public function test_context_is_only_shared_with_current_assigned_coaches_and_existing_client_progress_remains_available(): void
    {
        [$coach, $client, $exercise] = $this->pair();
        $this->loggedSession($client, $exercise, '2026-10-23');
        $this->getJson($this->url($client))->assertUnauthorized();
        $this->actingAs($client)->getJson($this->url($client))->assertOk()->assertJsonMissingPath('data.0.logged_calories_kcal');
        $this->flushSession();
        $this->actingAs(User::factory()->create(['role' => 'coach']))->getJson($this->url($client))->assertForbidden();
        $this->flushSession();
        $this->actingAs($coach)->getJson($this->url($client))->assertOk()->assertJsonPath('data.0.logged_calories_kcal', null);
        TrainerClient::query()->update(['status' => 'inactive']);
        $this->getJson($this->url($client))->assertForbidden();
    }

    /** @return array{User, User, Exercise} */
    private function pair(): array
    {
        $coach = User::factory()->create(['role' => 'coach']);
        $client = User::factory()->create();
        TrainerClient::create(['trainer_id' => $coach->id, 'client_id' => $client->id, 'start_date' => '2026-10-01']);
        $exercise = Exercise::create(['exercise_name' => 'Bench press']);

        return [$coach, $client, $exercise];
    }

    private function url(User $client): string
    {
        return '/api/clients/'.$client->id.'/exercise-progress';
    }

    private function loggedSession(User $client, Exercise $exercise, string $date): int
    {
        $workout = $client->workouts()->create(['workout_name' => 'Training', 'workout_date' => $date]);
        $entry = $workout->workoutExercises()->create(['exercise_id' => $exercise->id, 'exercise_order' => 1]);
        $entry->sets()->create(['set_number' => 1, 'weight' => 100, 'reps' => 5, 'completed' => true]);

        return $workout->id;
    }

    /** @param list<int|null> $calories */
    private function plan(User $client, User $coach, string $from, array $calories): NutritionPlan
    {
        return NutritionPlan::create(['client_id' => $client->id, 'coach_id' => $coach->id, 'effective_from' => $from,
            'training_meals' => array_map(fn (?int $calories) => ['name' => 'Meal', 'time' => '08:00', 'foods' => 'Food', 'calories' => $calories], $calories),
            'rest_meals' => [['name' => 'Rest meal', 'time' => '09:00', 'foods' => 'Food', 'calories' => 999]],
        ]);
    }
}
