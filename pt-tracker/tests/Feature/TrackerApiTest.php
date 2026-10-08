<?php

namespace Tests\Feature;

use App\Models\Exercise;
use App\Models\TrainerClient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrackerApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
    }

    public function test_login_user_and_logout(): void
    {
        $client = User::factory()->create();
        $this->getJson('/api/user')->assertUnauthorized();
        $this->getJson('/api/csrf-token')->assertOk()->assertJsonStructure(['csrf_token']);
        $this->postJson('/api/login', ['email' => $client->email, 'password' => 'wrong'])->assertUnprocessable();
        $this->postJson('/api/login', ['email' => $client->email, 'password' => 'password'])
            ->assertOk()->assertJsonPath('data.id', $client->id)->assertJsonMissingPath('data.password');
        $this->getJson('/api/user')->assertOk();
        $this->postJson('/api/logout')->assertOk();
        $this->assertGuest();
        $this->getJson('/api/user')->assertUnauthorized();
    }

    public function test_coach_client_list_only_returns_current_active_assignments(): void
    {
        $coach = User::factory()->create(['role' => 'coach']);
        $client = User::factory()->create();
        $unassigned = User::factory()->create();
        $inactive = User::factory()->create(['is_active' => false]);
        foreach ([$client, $inactive] as $person) {
            TrainerClient::create(['trainer_id' => $coach->id, 'client_id' => $person->id, 'start_date' => today()->toDateString()]);
        }
        $this->getJson('/api/clients')->assertUnauthorized();
        $this->actingAs($client)->getJson('/api/clients')->assertForbidden();
        $this->actingAs($coach)->getJson('/api/clients')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $client->id)
            ->assertJsonMissingPath('data.0.password')->assertJsonMissingPath('data.0.pivot');
        $this->assertNotSame($unassigned->id, $client->id);
    }

    public function test_frontend_pages_and_dashboard_access(): void
    {
        $this->withoutVite();
        $this->get('/')->assertOk();
        $this->get('/login')->assertOk();
        $this->get('/dashboard')->assertRedirect('/login');
        $client = User::factory()->create();
        $this->actingAs($client)->get('/dashboard')->assertOk();
        $client->forceFill(['is_active' => false])->save();
        $this->actingAs($client->fresh())->get('/dashboard')->assertForbidden();
    }

    public function test_inactive_accounts_cannot_login_or_continue_using_a_session(): void
    {
        $client = User::factory()->create(['is_active' => false]);
        $this->postJson('/api/login', ['email' => $client->email, 'password' => 'password'])->assertUnprocessable();
        $this->actingAs($client)->getJson('/api/user')->assertForbidden();
        $this->assertGuest();
    }

    public function test_login_is_rate_limited_and_session_writes_require_csrf(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/login', ['email' => 'missing@example.test', 'password' => 'wrong'])->assertUnprocessable();
        }
        $this->postJson('/api/login', ['email' => 'missing@example.test', 'password' => 'wrong'])->assertStatus(429);
        $this->app['env'] = 'local';
        $this->postJson('/api/login', ['email' => 'other@example.test', 'password' => 'wrong'])->assertStatus(419);
    }

    public function test_clients_can_manage_check_ins_but_cannot_access_other_clients(): void
    {
        $client = User::factory()->create();
        $other = User::factory()->create();
        $base = "/api/clients/{$client->id}/daily-check-ins";
        $this->actingAs($client)->postJson($base, ['check_in_date' => '2026-10-08', 'bodyweight' => 82.4])
            ->assertCreated()->assertJsonPath('data.client_id', $client->id);
        $id = $client->dailyCheckIns()->firstOrFail()->id;
        $this->postJson($base, ['check_in_date' => '2026-10-08'])->assertUnprocessable();
        $this->postJson($base, ['check_in_date' => '2026-10-09', 'sleep_quality' => 6])->assertUnprocessable();
        $this->postJson($base, ['check_in_date' => '2026-10-09', 'client_id' => $other->id])->assertUnprocessable();
        $this->getJson($base)->assertOk()->assertJsonCount(1, 'data');
        $this->getJson("$base/$id")->assertOk();
        $this->patchJson("$base/$id", ['notes' => 'Updated'])->assertOk()->assertJsonPath('data.notes', 'Updated');
        $this->getJson("/api/clients/{$other->id}/daily-check-ins")->assertForbidden();
        $this->postJson("/api/clients/{$other->id}/daily-check-ins", ['check_in_date' => '2026-10-08'])->assertForbidden();
        $this->deleteJson("$base/$id")->assertNoContent();
        $this->assertDatabaseMissing('daily_check_ins', ['id' => $id]);
    }

    public function test_coach_access_requires_a_current_active_assignment(): void
    {
        $coach = User::factory()->create(['role' => 'coach']);
        $client = User::factory()->create();
        $base = "/api/clients/{$client->id}";
        $this->actingAs($coach)->getJson("$base/daily-check-ins")->assertForbidden();
        $assignment = TrainerClient::create(['trainer_id' => $coach->id, 'client_id' => $client->id, 'start_date' => today()->toDateString()]);
        $this->getJson("$base/daily-check-ins")->assertOk();
        $this->postJson("$base/daily-check-ins", ['check_in_date' => '2026-10-08'])->assertForbidden();
        $this->postJson("$base/workouts", ['workout_name' => 'Push', 'workout_date' => '2026-10-08'])
            ->assertCreated()->assertJsonPath('data.trainer_id', $coach->id);
        $assignment->update(['status' => 'inactive']);
        $this->getJson("$base/workouts")->assertForbidden();
        $assignment->update(['status' => 'active', 'start_date' => today()->addDay()->toDateString()]);
        $this->getJson("$base/workouts")->assertForbidden();
        $assignment->update(['start_date' => today()->subWeek()->toDateString(), 'end_date' => today()->subDay()->toDateString()]);
        $this->getJson("$base/workouts")->assertForbidden();
    }

    public function test_workout_limit_nested_sets_and_record_ownership(): void
    {
        $client = User::factory()->create();
        $other = User::factory()->create();
        $exercise = Exercise::create(['exercise_name' => 'Squat']);
        $base = "/api/clients/{$client->id}/workouts";
        $payload = [
            'workout_name' => 'Legs', 'workout_date' => '2026-10-08',
            'exercises' => array_fill(0, 10, ['exercise_id' => $exercise->id, 'sets' => [['reps' => 8, 'weight' => 60, 'rir' => 2]]]),
        ];
        $this->actingAs($client)->postJson($base, $payload)->assertCreated()->assertJsonCount(10, 'data.workout_exercises');
        $workout = $client->workouts()->firstOrFail();
        $this->assertSame(10, $workout->workoutExercises()->count());
        $this->assertSame(1, $workout->workoutExercises()->first()->sets()->first()->set_number);
        $payload['exercises'][] = ['exercise_id' => $exercise->id];
        $this->postJson($base, $payload)->assertUnprocessable()->assertJsonValidationErrors('exercises');
        $this->patchJson("$base/{$workout->id}", ['exercises' => $payload['exercises']])->assertUnprocessable();
        $this->assertSame(10, $workout->workoutExercises()->count());
        $this->patchJson("$base/{$workout->id}", ['completed' => true])->assertOk()->assertJsonCount(10, 'data.workout_exercises');
        $this->patchJson("$base/{$workout->id}", ['exercises' => [['exercise_id' => $exercise->id]]])
            ->assertOk()->assertJsonCount(1, 'data.workout_exercises');
        $foreign = $other->workouts()->create(['workout_name' => 'Private', 'workout_date' => '2026-10-08']);
        $this->getJson("$base/{$foreign->id}")->assertNotFound();
        $this->patchJson("$base/{$foreign->id}", ['completed' => true])->assertNotFound();
        $this->deleteJson("$base/{$foreign->id}")->assertNotFound();
        $this->getJson("/api/clients/{$other->id}/workouts")->assertForbidden();
        $this->deleteJson("$base/{$workout->id}")->assertNoContent();
        $this->assertDatabaseCount('workout_exercises', 0);
        $this->assertDatabaseCount('exercise_sets', 0);
    }
}
