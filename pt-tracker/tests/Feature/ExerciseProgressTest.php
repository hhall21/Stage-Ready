<?php

namespace Tests\Feature;

use App\Models\Exercise;
use App\Models\TrainerClient;
use App\Models\User;
use App\Models\Workout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExerciseProgressTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
    }

    public function test_progress_uses_completed_recorded_sets_and_excludes_planned_future_and_other_client_data(): void
    {
        $client = User::factory()->create();
        $exercise = Exercise::create(['exercise_name' => 'Bench press']);
        $workout = $this->loggedWorkout($client, $exercise, today()->subDay()->toDateString());
        $entry = $workout->workoutExercises()->firstOrFail();
        $entry->sets()->create(['set_number' => 2, 'weight' => 80, 'reps' => 12, 'completed' => true]);
        $entry->sets()->create(['set_number' => 3, 'weight' => 200, 'reps' => 20, 'completed' => false]);
        $entry->sets()->create(['set_number' => 4, 'weight' => null, 'reps' => 30, 'completed' => true]);
        $entry->sets()->create(['set_number' => 5, 'weight' => 250, 'reps' => 0, 'completed' => true]);
        $this->loggedWorkout($client, $exercise, today()->addDay()->toDateString());
        $this->loggedWorkout(User::factory()->create(), $exercise, today()->toDateString());
        $this->actingAs($client)->getJson('/api/clients/'.$client->id.'/exercise-progress')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonCount(1, 'exercises')->assertJsonPath('exercise_id', $exercise->id)
            ->assertJsonPath('data.0.workout_id', $workout->id)->assertJsonPath('data.0.max_weight_kg', 100)
            ->assertJsonPath('data.0.max_reps', 12)->assertJsonPath('data.0.logged_sets', 2)->assertJsonPath('data.0.volume_kg', 1460);
    }

    public function test_access_is_limited_to_the_client_and_their_current_coach(): void
    {
        $coach = User::factory()->create(['role' => 'coach']);
        $client = User::factory()->create();
        TrainerClient::create(['trainer_id' => $coach->id, 'client_id' => $client->id, 'start_date' => today()]);
        $url = '/api/clients/'.$client->id.'/exercise-progress';
        $this->getJson($url)->assertUnauthorized();
        $this->actingAs(User::factory()->create())->getJson($url)->assertForbidden();
        $this->actingAs($coach)->getJson($url)->assertOk()->assertJsonCount(0, 'data');
        TrainerClient::query()->update(['status' => 'inactive']);
        $this->getJson($url)->assertForbidden();
    }

    public function test_progress_is_bounded_to_the_latest_sixty_sessions_in_chronological_order(): void
    {
        $client = User::factory()->create();
        $exercise = Exercise::create(['exercise_name' => 'Squat']);
        for ($days = 64; $days >= 0; $days--) {
            $this->loggedWorkout($client, $exercise, today()->subDays($days)->toDateString());
        }
        $this->actingAs($client)->getJson('/api/clients/'.$client->id.'/exercise-progress?exercise_id='.$exercise->id)->assertOk()
            ->assertJsonCount(60, 'data')->assertJsonPath('data.0.date', today()->subDays(59)->toDateString())->assertJsonPath('data.59.date', today()->toDateString());
    }

    private function loggedWorkout(User $client, Exercise $exercise, string $date): Workout
    {
        $workout = $client->workouts()->create(['workout_name' => 'Training', 'workout_date' => $date]);
        $entry = $workout->workoutExercises()->create(['exercise_id' => $exercise->id, 'exercise_order' => 1]);
        $entry->sets()->create(['set_number' => 1, 'weight' => 100, 'reps' => 5, 'completed' => true]);

        return $workout;
    }
}
