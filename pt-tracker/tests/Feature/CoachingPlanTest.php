<?php

namespace Tests\Feature;

use App\Models\NutritionPlan;
use App\Models\TrainerClient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CoachingPlanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
        $this->travelTo(now()->setDate(2026, 10, 18)->setTime(12, 0));
    }

    public function test_current_coach_prescribes_two_meal_plans_with_independent_foods_and_times(): void
    {
        [$coach, $client] = $this->pair();
        $url = '/api/clients/'.$client->id.'/nutrition-plans';
        $this->actingAs($coach)->postJson($url, $this->nutrition())->assertCreated();
        $this->actingAs($client)->getJson($url)->assertOk()->assertJsonPath('current.training_meals.0.time', '08:00')
            ->assertJsonPath('current.rest_meals.0.time', '09:00')->assertJsonPath('current.rest_meals.0.foods', '60 g oats, 200 g yogurt');
        $this->assertDatabaseCount('meals', 0);
    }

    public function test_workout_date_selects_the_suggested_day_type_but_keeps_both_plans_available(): void
    {
        [$coach, $client] = $this->pair();
        $url = '/api/clients/'.$client->id.'/nutrition-plans';
        $this->actingAs($coach)->postJson($url, $this->nutrition());
        $client->workouts()->create(['workout_date' => '2026-10-19', 'workout_name' => 'Leg day']);
        $this->actingAs($client)->getJson($url.'?date=2026-10-19')->assertJsonPath('day_type', 'training')->assertJsonCount(1, 'current.rest_meals');
        $this->getJson($url.'?date=2026-10-20')->assertJsonPath('day_type', 'rest');
        $this->getJson($url.'?date=bad-date')->assertUnprocessable();
    }

    public function test_prescriptions_are_coach_only_and_private_to_the_client_and_active_assigned_coaches(): void
    {
        [$coach, $client] = $this->pair();
        foreach (['nutrition-plans' => $this->nutrition(), 'practice-plans' => $this->practice()] as $resource => $data) {
            $url = '/api/clients/'.$client->id.'/'.$resource;
            $this->postJson($url, $data)->assertUnauthorized();
            $this->actingAs($client)->postJson($url, $data)->assertForbidden();
            $this->actingAs(User::factory()->create(['role' => 'coach']))->postJson($url, $data)->assertForbidden();
            $this->actingAs($coach)->postJson($url, $data)->assertCreated();
            $this->actingAs(User::factory()->create())->getJson($url)->assertForbidden();
            $this->actingAs($coach)->getJson($url)->assertOk();
            $this->postJson('/api/logout')->assertOk();
        }
    }

    public function test_meals_require_both_day_types_valid_times_foods_and_safe_numeric_fields(): void
    {
        [$coach, $client] = $this->pair();
        $url = '/api/clients/'.$client->id.'/nutrition-plans';
        $bad = $this->nutrition();
        $bad['rest_meals'] = [];
        $bad['training_meals'][0]['time'] = '25:99';
        $bad['training_meals'][0]['calories'] = -100;
        $bad['training_meals'][0]['foods'] = '';
        $this->actingAs($coach)->postJson($url, $bad)->assertUnprocessable()->assertJsonValidationErrors(['rest_meals', 'training_meals.0.time', 'training_meals.0.calories', 'training_meals.0.foods']);
        $this->postJson($url, [...$this->nutrition(), 'effective_from' => '2026-10-17', 'client_id' => $client->id, 'coach_id' => $coach->id])->assertUnprocessable()->assertJsonValidationErrors(['effective_from', 'client_id', 'coach_id']);
        $this->postJson($url, [...$this->nutrition(), 'rest_meals' => array_fill(0, 13, $this->nutrition()['rest_meals'][0])])->assertUnprocessable()->assertJsonValidationErrors('rest_meals');
        $bad = $this->nutrition();
        $bad['training_meals'][0]['injected_field'] = 'unexpected';
        $this->postJson($url, $bad)->assertUnprocessable();
        $this->assertDatabaseCount('nutrition_plans', 0);
    }

    public function test_future_versions_activate_on_the_right_date_and_history_survives_revisions(): void
    {
        [$coach, $client] = $this->pair();
        foreach (['nutrition-plans' => $this->nutrition(), 'practice-plans' => $this->practice()] as $resource => $data) {
            $url = '/api/clients/'.$client->id.'/'.$resource;
            $first = $this->actingAs($coach)->postJson($url, $data)->json('data.id');
            $second = $this->postJson($url, [...$data, 'effective_from' => '2026-10-25'])->json('data.id');
            $third = $this->postJson($url, [...$data, 'effective_from' => '2026-10-25', 'notes' => 'Revised scheduled version'])->json('data.id');
            $this->getJson($url.'?date=2026-10-17')->assertJsonPath('current', null);
            $this->getJson($url)->assertJsonPath('current.id', $first)->assertJsonPath('versions.total', 3);
            $this->getJson($url.'?date=2026-10-24')->assertJsonPath('current.id', $first);
            $this->getJson($url.'?date=2026-10-25')->assertJsonPath('current.id', $third);
            $this->deleteJson($url.'/'.$third)->assertNoContent();
            $this->getJson($url.'?date=2026-10-25')->assertJsonPath('current.id', $second);
            $this->deleteJson($url.'/'.$first)->assertUnprocessable();
        }
    }

    public function test_daily_cardio_posing_and_vacuums_support_scheduled_round_increases(): void
    {
        [$coach, $client] = $this->pair();
        $url = '/api/clients/'.$client->id.'/practice-plans';
        $first = $this->practice();
        $this->actingAs($coach)->postJson($url, $first)->assertCreated();
        $next = $this->practice();
        $next['effective_from'] = '2026-10-25';
        $next['days'][0]['posing_rounds'] = 6;
        $this->postJson($url, $next)->assertCreated();
        $this->actingAs($client)->getJson($url.'?date=2026-10-19')->assertJsonPath('weekday', 1)
            ->assertJsonPath('current.days.0.posing_rounds', 3)->assertJsonPath('current.days.0.vacuum_sets', 4)
            ->assertJsonPath('current.days.0.cardio_minutes', 25)->assertJsonPath('current.days.0.cardio_mode', 'Incline walk');
        $this->getJson($url.'?date=2026-10-26')->assertJsonPath('current.days.0.posing_rounds', 6);
        $this->getJson($url.'?date=2026-10-19')->assertJsonPath('current.days.0.posing_rounds', 3);
    }

    public function test_daily_prescriptions_require_all_seven_distinct_weekdays_and_valid_durations(): void
    {
        [$coach, $client] = $this->pair();
        $url = '/api/clients/'.$client->id.'/practice-plans';
        $bad = $this->practice();
        $bad['days'][1]['weekday'] = '1';
        $bad['days'][0]['cardio_minutes'] = -1;
        $bad['days'][0]['vacuum_hold_seconds'] = 0;
        $bad['days'][0]['posing_rounds'] = 1.5;
        $bad['show_date'] = '2026-10-01';
        $this->actingAs($coach)->postJson($url, $bad)->assertUnprocessable()->assertJsonValidationErrors(['days.1.weekday', 'days.0.cardio_minutes', 'days.0.vacuum_hold_seconds', 'days.0.posing_rounds', 'show_date']);
        $bad = $this->practice();
        array_pop($bad['days']);
        $this->postJson($url, $bad)->assertUnprocessable()->assertJsonValidationErrors('days');
        $bad = $this->practice();
        $bad['days'][0]['cardio_mode'] = '';
        $this->postJson($url, $bad)->assertUnprocessable()->assertJsonValidationErrors('days.0.cardio_mode');
        $this->assertDatabaseCount('practice_plans', 0);
    }

    public function test_ended_coaching_preserves_plans_but_removes_the_former_coachs_access(): void
    {
        [$coach, $client] = $this->pair();
        $this->actingAs($coach)->postJson('/api/clients/'.$client->id.'/nutrition-plans', $this->nutrition())->assertCreated();
        $this->postJson('/api/clients/'.$client->id.'/practice-plans', $this->practice())->assertCreated();
        $this->deleteJson('/api/clients/'.$client->id.'/coaching')->assertOk();
        foreach (['nutrition-plans', 'practice-plans'] as $resource) {
            $url = '/api/clients/'.$client->id.'/'.$resource;
            $this->actingAs($coach)->getJson($url)->assertForbidden();
            $this->actingAs($client)->getJson($url)->assertOk()->assertJsonPath('versions.total', 1);
        }
    }

    public function test_plan_cancellation_is_scoped_to_the_client_and_author_and_lists_are_paginated(): void
    {
        [$coach, $client] = $this->pair();
        $otherCoach = User::factory()->create(['role' => 'coach']);
        TrainerClient::create(['trainer_id' => $otherCoach->id, 'client_id' => $client->id, 'start_date' => today()]);
        $url = '/api/clients/'.$client->id.'/nutrition-plans';
        $id = $this->actingAs($coach)->postJson($url, [...$this->nutrition(), 'effective_from' => '2026-10-25'])->json('data.id');
        $this->actingAs($client)->deleteJson($url.'/'.$id)->assertForbidden();
        $this->actingAs($otherCoach)->deleteJson($url.'/'.$id)->assertForbidden();
        $foreign = NutritionPlan::create(['client_id' => User::factory()->create()->id, 'coach_id' => $coach->id, ...$this->nutrition()]);
        $this->actingAs($coach)->deleteJson($url.'/'.$foreign->id)->assertNotFound();
        for ($index = 0; $index < 12; $index++) {
            NutritionPlan::create(['client_id' => $client->id, 'coach_id' => $coach->id, ...$this->nutrition()]);
        }
        $this->getJson($url)->assertJsonPath('versions.total', 13)->assertJsonCount(12, 'versions.data');
        $this->getJson($url.'?page=2')->assertJsonCount(1, 'versions.data');
    }

    public function test_inactive_accounts_and_expired_or_future_assignments_cannot_prescribe(): void
    {
        [$coach, $client] = $this->pair();
        $url = '/api/clients/'.$client->id.'/practice-plans';
        $assignment = TrainerClient::firstOrFail();
        foreach ([['status' => 'inactive'], ['end_date' => '2026-10-17'], ['start_date' => '2026-10-19']] as $change) {
            $assignment->forceFill(['status' => 'active', 'start_date' => '2026-10-01', 'end_date' => null, ...$change])->save();
            $this->actingAs($coach)->postJson($url, $this->practice())->assertForbidden();
        }
        $assignment->forceFill(['start_date' => '2026-10-01'])->save();
        $client->forceFill(['is_active' => false])->save();
        $this->postJson($url, $this->practice())->assertForbidden();
    }

    public function test_list_shapes_are_required_and_numeric_weekdays_are_stored_as_integers(): void
    {
        [$coach, $client] = $this->pair();
        $base = '/api/clients/'.$client->id;
        $bad = $this->nutrition();
        $bad['training_meals'] = ['breakfast' => $bad['training_meals'][0]];
        $this->actingAs($coach)->postJson($base.'/nutrition-plans', $bad)->assertUnprocessable()->assertJsonValidationErrors('training_meals');
        $bad = $this->practice();
        $bad['days'] = array_combine(['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'], $bad['days']);
        $this->postJson($base.'/practice-plans', $bad)->assertUnprocessable()->assertJsonValidationErrors('days');
        $data = $this->practice();
        $data['days'][0]['weekday'] = '1';
        $data['days'][0]['cardio_minutes'] = '25';
        $this->postJson($base.'/practice-plans', $data)->assertCreated()->assertJsonPath('data.days.0.weekday', 1)->assertJsonPath('data.days.0.cardio_minutes', 25);
    }

    /** @return array{User, User} */
    private function pair(): array
    {
        $coach = User::factory()->create(['role' => 'coach']);
        $client = User::factory()->create();
        TrainerClient::create(['trainer_id' => $coach->id, 'client_id' => $client->id, 'start_date' => '2026-10-01']);

        return [$coach, $client];
    }

    /** @return array<string, mixed> */
    private function nutrition(): array
    {
        $meal = ['name' => 'Breakfast', 'time' => '08:00', 'foods' => '80 g oats, 200 g yogurt', 'calories' => 500, 'protein' => 30, 'carbohydrates' => 70, 'fat' => 10];

        return ['effective_from' => '2026-10-18', 'training_meals' => [$meal], 'rest_meals' => [[...$meal, 'time' => '09:00', 'foods' => '60 g oats, 200 g yogurt']], 'notes' => 'Weigh oats dry.'];
    }

    /** @return array<string, mixed> */
    private function practice(): array
    {
        $days = [];
        for ($day = 1; $day <= 7; $day++) {
            $days[] = ['weekday' => $day, 'cardio_mode' => 'Incline walk', 'cardio_minutes' => 25, 'cardio_intensity' => 'Easy conversational pace', 'posing_rounds' => 3, 'pose_hold_seconds' => 10, 'vacuum_sets' => 4, 'vacuum_hold_seconds' => 15, 'notes' => null];
        }

        return ['effective_from' => '2026-10-18', 'show_date' => '2026-11-15', 'days' => $days, 'notes' => 'Mandatory rounds after training.'];
    }
}
