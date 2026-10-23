<?php

namespace Tests\Feature;

use App\Models\Exercise;
use App\Models\TrainerClient;
use App\Models\TrainingSplit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TrainingSplitTest extends TestCase
{
    use RefreshDatabase;

    private User $coach;

    private User $client;

    private Exercise $exercise;

    private string $base;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
        $this->travelTo(now()->setDate(2026, 10, 8)->setTime(12, 0));
        $this->coach = User::factory()->create(['role' => 'coach']);
        $this->client = User::factory()->create();
        TrainerClient::create(['trainer_id' => $this->coach->id, 'client_id' => $this->client->id, 'start_date' => '2026-10-01']);
        $this->exercise = Exercise::create(['exercise_name' => 'Squat']);
        $this->base = '/api/clients/'.$this->client->id;
    }

    private function training(string $name): array
    {
        return ['name' => $name, 'is_rest_day' => false, 'exercises' => [
            ['exercise_id' => $this->exercise->id, 'notes' => 'Controlled tempo', 'sets' => [['reps' => 8, 'weight' => 60, 'rir' => 2]]],
        ]];
    }

    private function plan(): array
    {
        return ['name' => 'Four-day split', 'start_date' => '2026-10-08', 'days' => [
            $this->training('Push'), $this->training('Pull'),
            ['name' => 'Rest day', 'is_rest_day' => true, 'exercises' => []], $this->training('Legs'),
        ]];
    }

    private function assign(?array $plan = null): int
    {
        return $this->actingAs($this->coach)->postJson($this->base.'/training-splits', $plan ?? $this->plan())->assertCreated()->json('data.id');
    }

    public function test_a_maximum_size_workout_is_materialised_in_batches_without_losing_set_details(): void
    {
        $entry = $this->training('Maximum workout')['exercises'][0];
        $entry['sets'] = array_fill(0, 100, ['reps' => 8, 'weight' => null, 'rir' => 2]);
        $plan = $this->plan();
        $plan['days'] = [['name' => 'Maximum workout', 'is_rest_day' => false, 'exercises' => array_fill(0, 10, $entry)]];
        $this->assign($plan);
        DB::enableQueryLog();
        $response = $this->actingAs($this->client)->getJson($this->base.'/training-schedule')->assertOk();
        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        $inserts = array_filter($queries, fn (array $query): bool => str_starts_with(strtolower($query['query']), 'insert '));
        $this->assertLessThan(30, count($inserts), 'Creating 1,000 sets must use bounded batch writes.');
        $response->assertJsonCount(10, 'data.workout.workout_exercises')
            ->assertJsonCount(100, 'data.workout.workout_exercises.9.sets')
            ->assertJsonPath('data.workout.workout_exercises.9.sets.99.set_number', 100)
            ->assertJsonPath('data.workout.workout_exercises.9.sets.99.weight', null)
            ->assertJsonPath('data.workout.workout_exercises.9.sets.99.reps', 8);
        $this->getJson($this->base.'/training-schedule')->assertJsonPath('data.workout.id', $response->json('data.workout.id'));
        $this->assertDatabaseCount('workouts', 1);
        $this->assertDatabaseCount('exercise_sets', 1000);
    }

    public function test_calendar_counts_rest_days_wraps_and_keeps_workouts_idempotent_after_long_gaps(): void
    {
        $this->assign();
        $this->actingAs($this->client);
        foreach (['2026-10-08' => [1, 'Push'], '2026-10-09' => [2, 'Pull'], '2026-10-10' => [3, 'Rest day'], '2026-10-11' => [4, 'Legs'], '2026-10-12' => [1, 'Push'], '2026-10-19' => [4, 'Legs']] as $date => [$day, $name]) {
            $response = $this->getJson($this->base.'/training-schedule?date='.$date)->assertOk()
                ->assertJsonPath('data.day_number', $day)->assertJsonPath('data.day_name', $name)
                ->assertJsonPath('data.is_rest_day', $day === 3);
            if ($day === 3) {
                $response->assertJsonPath('data.workout', null);
            } else {
                $id = $response->json('data.workout.id');
                $response->assertJsonPath('data.workout.trainer_id', $this->coach->id)->assertJsonCount(1, 'data.workout.workout_exercises');
                $this->getJson($this->base.'/training-schedule?date='.$date)->assertJsonPath('data.workout.id', $id);
            }
        }
        $this->getJson($this->base.'/training-schedule?date=2030-10-08')->assertOk()->assertJsonPath('data.day_number', 2);
        $this->assertDatabaseCount('workouts', 6);
        $this->getJson($this->base.'/training-schedule?date=2026-10-07')->assertJsonPath('data.split', null)->assertJsonPath('data.next_split.name', 'Four-day split');
    }

    public function test_extra_rest_pauses_and_selected_training_or_rest_days_recalibrate_tomorrow(): void
    {
        $this->assign();
        $this->actingAs($this->client)->putJson($this->base.'/training-schedule/selection', ['date' => '2026-10-08', 'day_number' => 0])
            ->assertOk()->assertJsonPath('data.is_extra_rest', true)->assertJsonPath('data.workout', null)
            ->assertJsonPath('data.tomorrow.day_number', 1);
        $this->travel(1)->days();
        $this->putJson($this->base.'/training-schedule/selection', ['date' => '2026-10-09', 'day_number' => 0])
            ->assertOk()->assertJsonPath('data.tomorrow.day_number', 1);
        $this->travel(1)->days();
        $this->getJson($this->base.'/training-schedule')->assertJsonPath('data.day_number', 1);
        $this->putJson($this->base.'/training-schedule/selection', ['date' => '2026-10-10', 'day_number' => 2])
            ->assertOk()->assertJsonPath('data.day_name', 'Pull')->assertJsonPath('data.tomorrow.day_number', 3);
        // A changed choice on the same date updates one selection, on both DB engines.
        $this->putJson($this->base.'/training-schedule/selection', ['date' => '2026-10-10', 'day_number' => 4])
            ->assertOk()->assertJsonPath('data.tomorrow.day_number', 1);
        $this->travel(1)->days();
        $this->putJson($this->base.'/training-schedule/selection', ['date' => '2026-10-11', 'day_number' => 3])
            ->assertOk()->assertJsonPath('data.is_rest_day', true)->assertJsonPath('data.tomorrow.day_number', 4);
        $this->travel(2)->days();
        $this->getJson($this->base.'/training-schedule')->assertJsonPath('data.day_number', 1);
        $this->assertDatabaseCount('split_day_selections', 4);
    }

    public function test_changing_or_resetting_an_unlogged_choice_rebuilds_cached_future_sessions(): void
    {
        $this->assign();
        $this->actingAs($this->client);
        $old = $this->getJson($this->base.'/training-schedule')->json('data.workout.id');
        $future = $this->getJson($this->base.'/training-schedule?date=2026-10-09')->json('data.workout.id');
        $this->putJson($this->base.'/training-schedule/selection', ['date' => '2026-10-08', 'day_number' => 2])
            ->assertOk()->assertJsonPath('data.day_name', 'Pull');
        $this->assertDatabaseMissing('workouts', ['id' => $old]);
        $this->assertDatabaseMissing('workouts', ['id' => $future]);
        $this->getJson($this->base.'/training-schedule?date=2026-10-09')->assertJsonPath('data.is_rest_day', true);
        $this->putJson($this->base.'/training-schedule/selection', ['date' => '2026-10-08', 'day_number' => 0])->assertOk();
        $this->putJson($this->base.'/training-schedule/selection', ['date' => '2026-10-08', 'day_number' => null])
            ->assertOk()->assertJsonPath('data.day_name', 'Push');
        $this->assertDatabaseCount('split_day_selections', 0);
        $this->getJson($this->base.'/training-schedule?date=2026-10-09')->assertJsonPath('data.day_name', 'Pull');
    }

    public function test_one_and_fifteen_day_splits_and_program_replacement_start_on_the_chosen_date(): void
    {
        $plan = $this->plan();
        $plan['days'] = [$this->training('Daily workout')];
        $this->assign($plan);
        $this->actingAs($this->client)->getJson($this->base.'/training-schedule?date=2026-10-12')->assertJsonPath('data.day_number', 1);
        $plan['start_date'] = '2026-10-13';
        $plan['name'] = 'Long split';
        $plan['days'] = array_fill(0, 15, $this->training('Training'));
        $plan['days'][14] = ['name' => 'Rest day', 'is_rest_day' => true, 'exercises' => []];
        $this->assign($plan);
        $this->actingAs($this->client)->getJson($this->base.'/training-schedule?date=2026-10-12')->assertJsonPath('data.split.name', 'Four-day split');
        $this->getJson($this->base.'/training-schedule?date=2026-10-13')->assertJsonPath('data.day_number', 1)->assertJsonPath('data.split.name', 'Long split');
        $this->getJson($this->base.'/training-schedule?date=2026-10-27')->assertJsonPath('data.day_number', 15)->assertJsonPath('data.is_rest_day', true);
        $this->getJson($this->base.'/training-schedule?date=2026-10-28')->assertJsonPath('data.day_number', 1);
    }

    public function test_program_limits_and_required_training_day_workouts_are_validated(): void
    {
        $plan = $this->plan();
        $this->actingAs($this->coach);
        foreach ([[], array_fill(0, 16, $this->training('Training'))] as $days) {
            $this->postJson($this->base.'/training-splits', [...$plan, 'days' => $days])->assertUnprocessable()->assertJsonValidationErrors('days');
        }
        $bad = $plan;
        $bad['days'][0]['exercises'] = [];
        $this->postJson($this->base.'/training-splits', $bad)->assertUnprocessable()->assertJsonValidationErrors('days.0.exercises');
        $bad['days'][0]['exercises'] = array_fill(0, 11, $plan['days'][0]['exercises'][0]);
        $this->postJson($this->base.'/training-splits', $bad)->assertUnprocessable()->assertJsonValidationErrors('days.0.exercises');
        $bad = $plan;
        $bad['days'][2]['exercises'] = $plan['days'][0]['exercises'];
        $this->postJson($this->base.'/training-splits', $bad)->assertUnprocessable()->assertJsonValidationErrors('days.2.exercises');
        $this->assertDatabaseCount('training_splits', 0);
    }

    public function test_meal_day_type_follows_the_split_and_extra_rest_before_any_workout_is_opened(): void
    {
        $this->assign();
        $this->actingAs($this->client)->getJson($this->base.'/nutrition-plans')->assertOk()->assertJsonPath('day_type', 'training');
        $this->getJson($this->base.'/nutrition-plans?date=2026-10-10')->assertJsonPath('day_type', 'rest');
        $this->assertDatabaseCount('workouts', 0);
        $this->client->workouts()->create(['trainer_id' => $this->coach->id, 'workout_name' => 'Earlier standalone plan', 'workout_date' => '2026-10-08']);
        $this->putJson($this->base.'/training-schedule/selection', ['date' => '2026-10-08', 'day_number' => 0])->assertOk();
        $this->getJson($this->base.'/nutrition-plans')->assertJsonPath('day_type', 'rest');
    }

    public function test_only_assigned_coaches_create_programs_and_clients_cannot_create_or_rewrite_workouts(): void
    {
        $this->postJson($this->base.'/training-splits', $this->plan())->assertUnauthorized();
        $this->actingAs($this->client)->postJson($this->base.'/training-splits', $this->plan())->assertForbidden();
        $this->postJson($this->base.'/workouts', ['workout_name' => 'Client-created', 'workout_date' => '2026-10-08'])->assertForbidden();
        $id = $this->assign();
        $this->putJson($this->base.'/training-schedule/selection', ['date' => '2026-10-08', 'day_number' => 2])->assertForbidden();
        $workout = $this->actingAs($this->client)->getJson($this->base.'/training-schedule')->json('data.workout');
        $this->patchJson($this->base.'/workouts/'.$workout['id'], ['completed' => true, 'workout_name' => 'Hacked', 'exercises' => []])
            ->assertUnprocessable()->assertJsonValidationErrors('workout_name');
        $this->deleteJson($this->base.'/workouts/'.$workout['id'])->assertForbidden();
        $this->putJson($this->base.'/training-splits/'.$id, $this->plan())->assertForbidden();
        $this->actingAs(User::factory()->create())->getJson($this->base.'/training-schedule')->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'coach']))->getJson($this->base.'/training-splits')->assertForbidden();
    }

    public function test_logging_locks_history_but_coach_edits_change_future_unlogged_workouts(): void
    {
        $id = $this->assign();
        $workout = $this->actingAs($this->client)->getJson($this->base.'/training-schedule')->json('data.workout');
        $set = $workout['workout_exercises'][0]['sets'][0];
        $this->patchJson($this->base.'/workouts/'.$workout['id'].'/sets/'.$set['id'], ['reps' => 9, 'weight' => 65, 'completed' => true])->assertOk();
        $this->putJson($this->base.'/training-schedule/selection', ['date' => '2026-10-08', 'day_number' => 0])->assertConflict();
        $replacement = Exercise::create(['exercise_name' => 'Leg press']);
        $plan = $this->plan();
        $plan['days'][0]['name'] = 'Updated push';
        foreach ($plan['days'] as &$day) {
            if (! $day['is_rest_day']) {
                $day['exercises'][0]['exercise_id'] = $replacement->id;
            }
        }
        unset($day);
        $this->actingAs($this->coach)->putJson($this->base.'/training-splits/'.$id, $plan)->assertOk();
        $this->deleteJson($this->base.'/workouts/'.$workout['id'])->assertConflict();
        $this->patchJson($this->base.'/workouts/'.$workout['id'], ['workout_name' => 'Overwrite history'])->assertConflict();
        $this->actingAs($this->client)->getJson($this->base.'/training-schedule')->assertJsonPath('data.day_name', 'Push')
            ->assertJsonPath('data.workout.workout_exercises.0.exercise.id', $this->exercise->id)
            ->assertJsonPath('data.workout.workout_exercises.0.sets.0.reps', 9);
        $this->getJson($this->base.'/training-schedule?date=2026-10-09')->assertJsonPath('data.workout.workout_exercises.0.exercise.id', $replacement->id);
        $plan['days'][] = $this->training('Extra day');
        $this->actingAs($this->coach)->putJson($this->base.'/training-splits/'.$id, $plan)->assertUnprocessable()->assertJsonValidationErrors('days');
        $this->postJson($this->base.'/training-splits', $this->plan())->assertUnprocessable();
        $this->assertDatabaseHas('exercise_sets', ['id' => $set['id'], 'reps' => 9]);
    }

    public function test_a_past_change_cannot_shift_later_logged_history_and_foreign_split_ids_are_scoped(): void
    {
        $id = $this->assign();
        $this->travel(3)->days();
        $workout = $this->actingAs($this->client)->getJson($this->base.'/training-schedule')->json('data.workout');
        $this->patchJson($this->base.'/workouts/'.$workout['id'], ['completed' => true])->assertOk();
        $this->putJson($this->base.'/training-schedule/selection', ['date' => '2026-10-08', 'day_number' => 0])->assertConflict();
        $this->putJson($this->base.'/training-schedule/selection', ['date' => '2026-10-12', 'day_number' => 1])->assertUnprocessable();
        $this->putJson($this->base.'/training-schedule/selection', ['date' => '2026-10-11', 'day_number' => 15])->assertUnprocessable();
        $other = User::factory()->create();
        TrainerClient::create(['trainer_id' => $this->coach->id, 'client_id' => $other->id, 'start_date' => '2026-10-01']);
        $this->actingAs($this->coach)->putJson('/api/clients/'.$other->id.'/training-splits/'.$id, $this->plan())->assertNotFound();
        $this->assertSame(4, count(TrainingSplit::findOrFail($id)->days));
    }
}
