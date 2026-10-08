<?php

namespace Tests\Feature;

use App\Models\Exercise;
use App\Models\Mesocycle;
use App\Models\TrainerClient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MesocycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
        $this->travelTo(now()->setDate(2026, 10, 8)->startOfDay());
    }

    private function assign(User $coach, User $client): void
    {
        TrainerClient::create(['trainer_id' => $coach->id, 'client_id' => $client->id, 'start_date' => '2026-10-01']);
    }

    private function plan(int $exercise): array
    {
        return ['name' => 'Strength block', 'start_date' => '2026-10-08', 'end_date' => '2026-10-21', 'templates' => [
            ['name' => 'Push', 'weekday' => 1, 'exercises' => [['exercise_id' => $exercise, 'sets' => [['reps' => 8, 'weight' => 60, 'rir' => 2]]]]],
            ['name' => 'Legs', 'weekday' => 4, 'exercises' => [['exercise_id' => $exercise, 'sets' => [['reps' => 10, 'weight' => 50, 'rir' => 3]]]]],
        ]];
    }

    public function test_coach_assigns_repeating_workouts_for_the_entered_period(): void
    {
        $coach = User::factory()->create(['role' => 'coach']);
        $client = User::factory()->create();
        $this->assign($coach, $client);
        $exercise = Exercise::create(['exercise_name' => 'Squat']);
        $this->actingAs($coach)->postJson("/api/clients/{$client->id}/mesocycles", $this->plan($exercise->id))->assertCreated();
        $this->assertDatabaseCount('workouts', 4);
        $this->assertDatabaseCount('exercise_sets', 4);
        $this->assertDatabaseHas('workouts', ['workout_name' => 'Push', 'trainer_id' => $coach->id, 'client_id' => $client->id]);
        $this->assertSame(['2026-10-08', '2026-10-12', '2026-10-15', '2026-10-19'], $client->workouts()->orderBy('workout_date')->get()->map(fn ($workout) => $workout->workout_date->toDateString())->all());
        $this->actingAs($client)->getJson("/api/clients/{$client->id}/mesocycles")->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_edit_changes_future_sessions_and_preserves_past_and_logged_sessions(): void
    {
        $coach = User::factory()->create(['role' => 'coach']);
        $client = User::factory()->create();
        $this->assign($coach, $client);
        $old = Exercise::create(['exercise_name' => 'Squat']);
        $replacement = Exercise::create(['exercise_name' => 'Leg press']);
        $base = "/api/clients/{$client->id}";
        $this->actingAs($coach)->postJson("$base/mesocycles", $this->plan($old->id))->assertCreated();
        $cycle = Mesocycle::firstOrFail();
        $this->travelTo(now()->setDate(2026, 10, 10)->startOfDay());
        $todayWorkout = $client->workouts()->whereDate('workout_date', '2026-10-08')->firstOrFail();
        $logged = $client->workouts()->whereDate('workout_date', '2026-10-12')->firstOrFail();
        $set = $logged->workoutExercises()->firstOrFail()->sets()->firstOrFail();
        $this->actingAs($client)->patchJson("$base/workouts/{$logged->id}/sets/{$set->id}", ['reps' => 7, 'weight' => 65, 'completed' => true])->assertOk();
        $newPlan = $this->plan($replacement->id);
        $this->actingAs($coach)->putJson("$base/mesocycles/{$cycle->id}", $newPlan)->assertOk();
        $this->assertDatabaseHas('exercise_sets', ['id' => $set->id, 'reps' => 7, 'weight' => 65]);
        $this->assertSame($old->id, $todayWorkout->workoutExercises()->firstOrFail()->exercise_id);
        $future = $client->workouts()->whereDate('workout_date', '2026-10-15')->firstOrFail();
        $this->assertSame($replacement->id, $future->workoutExercises()->firstOrFail()->exercise_id);
        $this->assertDatabaseCount('workouts', 4);
        $newPlan['end_date'] = '2026-10-14';
        $this->putJson("$base/mesocycles/{$cycle->id}", $newPlan)->assertOk();
        $this->assertDatabaseCount('workouts', 2);
        $this->assertDatabaseHas('workouts', ['id' => $logged->id]);
    }

    public function test_permissions_limits_overlaps_and_foreign_records_are_rejected(): void
    {
        $coach = User::factory()->create(['role' => 'coach']);
        $client = User::factory()->create();
        $other = User::factory()->create();
        $this->assign($coach, $client);
        $exercise = Exercise::create(['exercise_name' => 'Squat']);
        $plan = $this->plan($exercise->id);
        $base = "/api/clients/{$client->id}/mesocycles";
        $this->postJson($base, $plan)->assertUnauthorized();
        $this->actingAs($client)->postJson($base, $plan)->assertForbidden();
        $this->actingAs($coach)->postJson("/api/clients/{$other->id}/mesocycles", $plan)->assertForbidden();
        $invalid = $plan;
        $invalid['templates'][0]['exercises'] = array_fill(0, 11, $plan['templates'][0]['exercises'][0]);
        $this->postJson($base, $invalid)->assertUnprocessable()->assertJsonValidationErrors('templates.0.exercises');
        $this->assertDatabaseCount('mesocycles', 0);
        $this->postJson($base, $plan)->assertCreated();
        $this->postJson($base, $plan)->assertUnprocessable();
        $this->putJson("$base/99999", $plan)->assertNotFound();
        $this->actingAs($other)->getJson($base)->assertForbidden();
    }

    public function test_injury_changes_can_update_todays_unlogged_session(): void
    {
        $coach = User::factory()->create(['role' => 'coach']);
        $client = User::factory()->create();
        $this->assign($coach, $client);
        $old = Exercise::create(['exercise_name' => 'Squat']);
        $replacement = Exercise::create(['exercise_name' => 'Leg press']);
        $base = "/api/clients/{$client->id}/mesocycles";
        $this->actingAs($coach)->postJson($base, $this->plan($old->id))->assertCreated();
        $cycle = Mesocycle::firstOrFail();
        $this->putJson("$base/{$cycle->id}", $this->plan($replacement->id))->assertOk();
        $session = $client->workouts()->whereDate('workout_date', today()->toDateString())->firstOrFail();
        $this->assertSame($replacement->id, $session->workoutExercises()->firstOrFail()->exercise_id);
        $set = $session->workoutExercises()->firstOrFail()->sets()->firstOrFail();
        $this->patchJson("/api/clients/{$client->id}/workouts/{$session->id}/sets/{$set->id}", ['completed' => true])->assertForbidden();
        $this->actingAs($client)->patchJson("/api/clients/{$client->id}/workouts/{$session->id}/sets/99999", ['completed' => true])->assertNotFound();
    }

    public function test_rolling_averages_use_recorded_weigh_ins_only_and_compare_disjoint_windows(): void
    {
        $client = User::factory()->create();
        foreach (['2026-09-25' => 83, '2026-09-30' => 81, '2026-10-02' => 80, '2026-10-08' => 78, '2026-10-09' => 1] as $date => $weight) {
            $client->dailyCheckIns()->create(['check_in_date' => $date, 'bodyweight' => $weight]);
        }
        $client->dailyCheckIns()->create(['check_in_date' => '2026-10-07', 'notes' => 'No weigh-in']);
        $response = $this->actingAs($client)->getJson("/api/clients/{$client->id}/bodyweight-trend")->assertOk()->assertJsonCount(56, 'data');
        $response->assertJsonPath('data.55.average_kg', 79)
            ->assertJsonPath('data.55.readings', 2)
            ->assertJsonPath('data.55.change_from_7_days_ago_kg', -3)
            ->assertJsonPath('data.0.average_kg', null)->assertJsonPath('data.0.readings', 0);
        $other = User::factory()->create();
        $this->getJson("/api/clients/{$other->id}/bodyweight-trend")->assertForbidden();
        $coach = User::factory()->create(['role' => 'coach']);
        $this->assign($coach, $client);
        $this->actingAs($coach)->getJson("/api/clients/{$client->id}/bodyweight-trend")->assertOk();
    }
}
