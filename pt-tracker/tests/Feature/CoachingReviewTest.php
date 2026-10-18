<?php

namespace Tests\Feature;

use App\Models\CoachingReview;
use App\Models\TrainerClient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CoachingReviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
        $this->travelTo(now()->setDate(2026, 10, 18)->setTime(12, 0));
    }

    public function test_prep_is_weekly_and_offseason_is_fortnightly_with_due_status(): void
    {
        [$coach, $client] = $this->pair();
        $url = '/api/clients/'.$client->id.'/coaching-reviews';
        $this->actingAs($coach)->getJson($url)->assertOk()->assertJsonPath('schedules.0.interval_days', 14)->assertJsonPath('schedules.0.due_on', '2026-11-01');
        $this->patchJson($url.'/schedule', ['phase' => 'prep'])->assertOk()->assertJsonPath('schedules.0.interval_days', 7)->assertJsonPath('schedules.0.due_on', '2026-10-25');
        $this->travel(6)->days();
        $this->patchJson($url.'/schedule', ['phase' => 'prep'])->assertJsonPath('schedules.0.due_on', '2026-10-25')->assertJsonPath('schedules.0.is_due', false);
        $this->travel(1)->days();
        $this->getJson($url)->assertJsonPath('schedules.0.is_due', true);
        $this->postJson($url, ['review_date' => '2026-10-25', 'feedback' => 'Conditioning improving; keep the current plan.', 'next_steps' => 'Keep cardio unchanged.'])->assertCreated()->assertJsonPath('data.phase', 'prep');
        $this->getJson($url)->assertJsonPath('schedules.0.due_on', '2026-11-01')->assertJsonPath('schedules.0.is_due', false);
        $this->patchJson($url.'/schedule', ['phase' => 'offseason'])->assertJsonPath('schedules.0.due_on', '2026-11-08')->assertJsonPath('reviews.total', 1);
    }

    public function test_only_assigned_coaches_write_reviews_and_clients_can_read_feedback(): void
    {
        [$coach, $client] = $this->pair();
        $url = '/api/clients/'.$client->id.'/coaching-reviews';
        $data = ['review_date' => '2026-10-18', 'feedback' => 'Good adherence.', 'next_steps' => 'Continue the plan.'];
        $this->postJson($url, $data)->assertUnauthorized();
        $this->actingAs($client)->postJson($url, $data)->assertForbidden();
        $this->patchJson($url.'/schedule', ['phase' => 'prep'])->assertForbidden();
        $stranger = User::factory()->create(['role' => 'coach']);
        $this->actingAs($stranger)->postJson($url, $data)->assertForbidden();
        $this->getJson($url)->assertForbidden();
        $this->actingAs($coach)->postJson($url, $data)->assertCreated()->assertJsonPath('data.coach_id', $coach->id);
        $this->actingAs($client)->getJson($url)->assertOk()->assertJsonPath('reviews.data.0.feedback', 'Good adherence.')->assertJsonPath('reviews.data.0.coach.name', $coach->name);
    }

    public function test_review_validation_rejects_empty_future_duplicate_and_forged_records(): void
    {
        [$coach, $client] = $this->pair();
        $url = '/api/clients/'.$client->id.'/coaching-reviews';
        $this->actingAs($coach)->postJson($url, ['review_date' => '2026-10-19', 'feedback' => ' ', 'coach_id' => $coach->id, 'client_id' => $client->id, 'phase' => 'prep'])->assertUnprocessable()->assertJsonValidationErrors(['review_date', 'feedback', 'coach_id', 'client_id', 'phase']);
        $this->postJson($url, ['review_date' => '2026-10-17', 'feedback' => 'Old review.'])->assertUnprocessable();
        $this->patchJson($url.'/schedule', ['phase' => 'invalid'])->assertUnprocessable();
        $data = ['review_date' => '2026-10-18', 'feedback' => 'Good progress.'];
        $this->postJson($url, $data)->assertCreated();
        $this->postJson($url, $data)->assertUnprocessable()->assertJsonValidationErrors('review_date');
        $this->assertDatabaseCount('coaching_reviews', 1);
    }

    public function test_summary_uses_recorded_values_and_includes_only_the_review_window(): void
    {
        [$coach, $client] = $this->pair();
        $url = '/api/clients/'.$client->id.'/coaching-reviews';
        $client->dailyCheckIns()->create(['check_in_date' => '2026-10-18', 'bodyweight' => 80, 'steps' => 10000]);
        $client->dailyCheckIns()->create(['check_in_date' => '2026-10-17', 'bodyweight' => 82]);
        $client->dailyCheckIns()->create(['check_in_date' => '2026-10-16']);
        $client->dailyCheckIns()->create(['check_in_date' => '2026-10-01', 'bodyweight' => 200, 'steps' => 99999]);
        $client->dailyCheckIns()->create(['check_in_date' => '2026-10-19', 'bodyweight' => 200]);
        $client->workouts()->create(['workout_date' => '2026-10-18', 'workout_name' => 'Done', 'completed' => true]);
        $client->workouts()->create(['workout_date' => '2026-10-17', 'workout_name' => 'Planned']);
        $client->workouts()->create(['workout_date' => '2026-10-19', 'workout_name' => 'Future']);
        $this->actingAs($coach)->patchJson($url.'/schedule', ['phase' => 'prep'])->assertJsonPath('summary.from', '2026-10-12')->assertJsonPath('summary.check_ins', 3)->assertJsonPath('summary.weigh_ins', 2)->assertJsonPath('summary.average_bodyweight', 81)->assertJsonPath('summary.average_steps', 10000)->assertJsonPath('summary.workouts_planned', 2)->assertJsonPath('summary.workouts_completed', 1);
        $this->actingAs($client)->getJson($url)->assertJsonPath('summary.from', '2026-10-12');
    }

    public function test_each_coach_has_their_own_phase_cadence_and_review_credit(): void
    {
        [$coach, $client] = $this->pair();
        $other = User::factory()->create(['role' => 'coach']);
        TrainerClient::create(['trainer_id' => $other->id, 'client_id' => $client->id, 'start_date' => today()]);
        $url = '/api/clients/'.$client->id.'/coaching-reviews';
        $this->actingAs($coach)->patchJson($url.'/schedule', ['phase' => 'prep'])->assertOk();
        $this->postJson($url, ['review_date' => '2026-10-18', 'feedback' => 'Coach one review.'])->assertCreated();
        $this->actingAs($other)->postJson($url, ['review_date' => '2026-10-18', 'feedback' => 'Coach two review.'])->assertCreated()->assertJsonPath('data.phase', 'offseason');
        $this->actingAs($client)->getJson($url)->assertJsonCount(2, 'schedules')->assertJsonCount(2, 'reviews.data');
    }

    public function test_review_history_survives_ending_coaching_and_is_paginated(): void
    {
        [$coach, $client] = $this->pair();
        for ($day = 1; $day <= 13; $day++) {
            CoachingReview::create(['client_id' => $client->id, 'coach_id' => $coach->id, 'phase' => 'prep', 'review_date' => sprintf('2026-10-%02d', $day), 'feedback' => 'Progress review.']);
        }
        $url = '/api/clients/'.$client->id.'/coaching-reviews';
        $this->actingAs($coach)->getJson($url)->assertJsonPath('reviews.total', 13)->assertJsonCount(12, 'reviews.data');
        $this->getJson($url.'?page=2')->assertJsonCount(1, 'reviews.data');
        $this->deleteJson('/api/clients/'.$client->id.'/coaching')->assertOk();
        $this->getJson($url)->assertForbidden();
        $this->postJson($url, ['review_date' => '2026-10-18', 'feedback' => 'Cannot write.'])->assertForbidden();
        $this->actingAs($client)->getJson($url)->assertOk()->assertJsonCount(0, 'schedules')->assertJsonPath('reviews.total', 13);
    }

    /** @return array{User, User} */
    private function pair(): array
    {
        $coach = User::factory()->create(['role' => 'coach']);
        $client = User::factory()->create();
        TrainerClient::create(['trainer_id' => $coach->id, 'client_id' => $client->id, 'start_date' => today()]);

        return [$coach, $client];
    }
}
