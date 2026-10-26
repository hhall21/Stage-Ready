<?php

namespace Tests\Feature;

use App\Models\CoachingHandover;
use App\Models\CoachingReview;
use App\Models\Exercise;
use App\Models\NutritionPlan;
use App\Models\TrainerClient;
use App\Models\User;
use App\Services\CoachingHandoverPdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class CoachingHandoverTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
        $this->travelTo(now()->setDate(2026, 10, 26)->setTime(12, 0));
    }

    public function test_ending_coaching_keeps_login_and_history_and_provides_a_private_immutable_pdf(): void
    {
        [$coach, $client] = $this->pair();
        $check = $client->dailyCheckIns()->create(['check_in_date' => today(), 'bodyweight' => 82]);
        $workout = $client->workouts()->create(['trainer_id' => $coach->id, 'workout_date' => today(), 'workout_name' => 'Session', 'completed' => true]);
        $review = CoachingReview::create(['coach_id' => $coach->id, 'client_id' => $client->id, 'review_date' => today(), 'phase' => 'offseason', 'feedback' => 'Existing review']);
        $plan = NutritionPlan::create(['coach_id' => $coach->id, 'client_id' => $client->id, 'effective_from' => today(), 'training_meals' => [], 'rest_meals' => []]);
        $this->actingAs($coach)->deleteJson('/api/clients/'.$client->id.'/coaching', ['feedback' => 'Consistent training.', 'next_steps' => 'Keep your records.'])->assertOk();
        $handover = CoachingHandover::sole();
        $original = base64_decode($handover->pdf_base64);
        $this->assertStringStartsWith('%PDF-', $original);
        $this->assertSame('inactive', TrainerClient::sole()->status);
        $this->assertTrue($client->fresh()->is_active);
        $this->getJson('/api/clients/'.$client->id.'/workouts')->assertForbidden();
        $this->deleteJson('/api/clients/'.$client->id.'/coaching')->assertForbidden();
        $this->assertDatabaseCount('coaching_handovers', 1);
        $this->postJson('/api/logout')->assertOk();
        $this->postJson('/api/login', ['email' => $client->email, 'password' => 'password'])->assertOk()
            ->assertJsonPath('data.has_active_coaching', false)->assertJsonPath('data.has_coaching_handovers', true);
        $this->get('/dashboard')->assertOk();
        $this->getJson('/api/coaching-handovers')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.coach_name', $coach->name)->assertJsonMissingPath('data.0.pdf_base64')->assertJsonMissingPath('data.0.snapshot');
        $url = '/api/coaching-handovers/'.$handover->id.'/pdf';
        $this->get($url)->assertOk()->assertHeader('Content-Type', 'application/pdf')->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Content-Disposition', 'inline; filename=stage-ready-handover-'.$handover->id.'.pdf')->assertContent($original);
        $download = $this->get($url.'?download=1')->assertOk()->assertContent($original)
            ->assertHeader('Content-Disposition', 'attachment; filename=stage-ready-handover-'.$handover->id.'.pdf');
        $this->assertStringContainsString('no-store', $download->headers->get('Cache-Control'));
        $check->update(['bodyweight' => 83]);
        $coach->update(['name' => 'Changed name']);
        $this->get($url)->assertContent($original);
        $this->getJson('/api/clients/'.$client->id.'/workouts')->assertOk()->assertJsonPath('data.0.id', $workout->id);
        $this->getJson('/api/clients/'.$client->id.'/coaching-reviews')->assertOk()->assertJsonPath('reviews.data.0.id', $review->id);
        $this->getJson('/api/clients/'.$client->id.'/nutrition-plans')->assertOk()->assertJsonPath('versions.data.0.id', $plan->id);
        $this->assertDatabaseHas('daily_check_ins', ['id' => $check->id]);
    }

    public function test_only_the_report_owner_can_list_preview_or_download_it(): void
    {
        [$coach, $client] = $this->pair();
        $this->deleteJson('/api/clients/'.$client->id.'/coaching')->assertUnauthorized();
        $this->actingAs(User::factory()->create(['role' => 'coach']))->deleteJson('/api/clients/'.$client->id.'/coaching')->assertForbidden();
        $this->actingAs($client)->deleteJson('/api/clients/'.$client->id.'/coaching')->assertForbidden();
        $this->actingAs($coach)->deleteJson('/api/clients/'.$client->id.'/coaching')->assertOk();
        $handover = CoachingHandover::sole();
        foreach ([$coach, User::factory()->create(), User::factory()->create(['role' => 'coach'])] as $other) {
            $this->actingAs($other)->get('/api/coaching-handovers/'.$handover->id.'/pdf')->assertForbidden();
            $this->get('/api/coaching-handovers/'.$handover->id.'/pdf?download=1')->assertForbidden();
        }
        $this->actingAs($coach)->getJson('/api/coaching-handovers')->assertForbidden();
        $this->actingAs(User::factory()->create())->getJson('/api/coaching-handovers')->assertOk()->assertJsonCount(0, 'data');
        $client->forceFill(['is_active' => false])->save();
        $this->actingAs($client)->get('/api/coaching-handovers/'.$handover->id.'/pdf')->assertForbidden();
        $this->postJson('/api/login', ['email' => $client->email, 'password' => 'password'])->assertUnprocessable();
        $this->postJson('/api/logout');
        $this->getJson('/api/coaching-handovers')->assertUnauthorized();
        $this->getJson('/api/coaching-handovers/'.$handover->id.'/pdf')->assertUnauthorized();
    }

    public function test_snapshot_uses_recorded_weights_and_real_completed_sets_with_matching_reps(): void
    {
        [$coach, $client] = $this->pair(false);
        foreach ([['2026-09-30', 100], ['2026-10-13', 80], ['2026-10-19', 82], ['2026-10-20', null], ['2026-10-25', 84], ['2026-10-27', 120]] as [$date, $kg]) {
            $client->dailyCheckIns()->create(['check_in_date' => $date, 'bodyweight' => $kg]);
        }
        $workout = $client->workouts()->create(['trainer_id' => $coach->id, 'workout_date' => today(), 'workout_name' => 'Session', 'completed' => true]);
        $exercise = $workout->workoutExercises()->create(['exercise_id' => Exercise::create(['exercise_name' => 'Squat'])->id, 'exercise_order' => 1]);
        foreach ([[100, 5, true], [90, 12, true], [200, 0, true], [300, 5, false]] as $index => [$kg, $reps, $completed]) {
            $exercise->sets()->create(['set_number' => $index + 1, 'weight' => $kg, 'reps' => $reps, 'completed' => $completed]);
        }
        $bodyweight = $workout->workoutExercises()->create(['exercise_id' => Exercise::create(['exercise_name' => 'Pull-up'])->id, 'exercise_order' => 2]);
        $bodyweight->sets()->create(['set_number' => 1, 'weight' => null, 'reps' => 10, 'completed' => true]);
        $bodyweight->sets()->create(['set_number' => 2, 'weight' => 0, 'reps' => 5, 'completed' => true]);
        $client->workouts()->create(['workout_date' => '2026-09-30', 'workout_name' => 'Before coaching', 'completed' => true]);
        $client->workouts()->create(['workout_date' => '2026-10-27', 'workout_name' => 'Future', 'completed' => true]);
        $this->actingAs($coach)->deleteJson('/api/clients/'.$client->id.'/coaching')->assertOk();
        $snapshot = CoachingHandover::sole()->snapshot;
        $this->assertSame('Powerlifting', $snapshot['discipline']);
        $this->assertCount(3, $snapshot['weights']);
        $this->assertSame(1, $snapshot['completed_sessions']);
        $this->assertSame(4, $snapshot['working_sets']);
        $this->assertEquals(['exercise' => 'Squat', 'weight_kg' => 100, 'reps' => 5, 'date' => '2026-10-26'], $snapshot['personal_bests'][1]);
        $this->assertNull($snapshot['personal_bests'][0]['weight_kg']);
        $latest = $snapshot['weight_trend'][count($snapshot['weight_trend']) - 1];
        $this->assertEquals(84, $latest['average_kg']);
        $this->assertSame(1, $latest['readings']);
        $this->assertEquals(3, $latest['change_from_7_days_ago_kg']);
        $this->assertNull($snapshot['feedback']);
    }

    public function test_blank_history_and_optional_notes_produce_a_three_page_report(): void
    {
        [$coach, $client] = $this->pair();
        $client->dailyCheckIns()->create(['check_in_date' => today()]);
        $this->actingAs($coach)->deleteJson('/api/clients/'.$client->id.'/coaching')->assertOk();
        $handover = CoachingHandover::sole();
        $this->assertSame([], $handover->snapshot['weights']);
        $this->assertSame([], $handover->snapshot['personal_bests']);
        $this->assertNull($handover->snapshot['weight_trend'][0]['average_kg']);
        $this->assertSame(3, preg_match_all('~/Type\s*/Page\b~', base64_decode($handover->pdf_base64)));
    }

    public function test_notes_are_validated_before_coaching_ends_and_long_notes_paginate(): void
    {
        [$coach, $client] = $this->pair();
        $url = '/api/clients/'.$client->id.'/coaching';
        $this->actingAs($coach)->deleteJson($url, ['notes' => array_fill(0, 11, ['title' => 'Note', 'period' => '2026', 'body' => 'Text'])])->assertUnprocessable();
        $this->deleteJson($url, ['notes' => [['title' => 'Missing body']]])->assertUnprocessable();
        $this->deleteJson($url, ['feedback' => str_repeat('x', 10001)])->assertUnprocessable();
        $this->assertDatabaseCount('coaching_handovers', 0);
        $this->assertSame('active', TrainerClient::sole()->status);
        $body = str_repeat('Long plain text <script>not markup</script> & symbols. ', 140).'FINAL NOTE MARKER';
        $notes = array_fill(0, 10, ['title' => 'Recorded strategy', 'period' => 'October 2026', 'body' => 'A complete short note.']);
        $notes[0]['body'] = $body;
        $this->deleteJson($url, ['notes' => $notes])->assertOk();
        $handover = CoachingHandover::sole();
        $this->assertSame($body, $handover->snapshot['notes'][0]['body']);
        $this->assertCount(10, $handover->snapshot['notes']);
        $this->assertGreaterThan(5, preg_match_all('~/Type\s*/Page\b~', base64_decode($handover->pdf_base64)));
    }

    public function test_render_failure_does_not_end_coaching_or_leave_a_partial_handover(): void
    {
        [$coach, $client] = $this->pair();
        $this->mock(CoachingHandoverPdf::class, function ($mock): void {
            $mock->shouldReceive('render')->once()->andThrow(new RuntimeException('PDF renderer unavailable'));
        });
        $this->actingAs($coach)->deleteJson('/api/clients/'.$client->id.'/coaching')->assertServerError();
        $this->assertSame('active', TrainerClient::sole()->status);
        $this->assertNull(TrainerClient::sole()->end_date);
        $this->assertDatabaseCount('coaching_handovers', 0);
        $this->getJson('/api/clients/'.$client->id.'/workouts')->assertOk();
    }

    public function test_ending_one_assignment_keeps_other_coaching_and_repeated_periods_keep_separate_reports(): void
    {
        [$coach, $client] = $this->pair();
        $other = User::factory()->create(['role' => 'coach']);
        TrainerClient::create(['trainer_id' => $other->id, 'client_id' => $client->id, 'start_date' => today()]);
        $this->actingAs($coach)->deleteJson('/api/clients/'.$client->id.'/coaching')->assertOk();
        $firstPdf = CoachingHandover::sole()->pdf_base64;
        $this->actingAs($client)->getJson('/api/user')->assertJsonPath('data.has_active_coaching', true)->assertJsonPath('data.has_coaching_handovers', true);
        $assignment = TrainerClient::where('trainer_id', $coach->id)->firstOrFail();
        $assignment->update(['status' => 'active', 'start_date' => today(), 'end_date' => null]);
        $this->actingAs($coach)->deleteJson('/api/clients/'.$client->id.'/coaching', ['feedback' => 'Second coaching period.'])->assertOk();
        $this->assertDatabaseCount('coaching_handovers', 2);
        $this->assertSame($firstPdf, CoachingHandover::orderBy('id')->firstOrFail()->pdf_base64);
        $this->actingAs($client)->getJson('/api/coaching-handovers')->assertOk()->assertJsonCount(2, 'data');
        $this->actingAs($other)->getJson('/api/clients/'.$client->id.'/workouts')->assertOk();
    }

    public function test_existing_ended_assignments_can_be_backfilled_without_including_later_records_or_rewriting_reports(): void
    {
        [$coach, $client] = $this->pair();
        TrainerClient::sole()->update(['status' => 'inactive', 'end_date' => '2026-10-19']);
        $coach->forceFill(['is_active' => false])->save();
        $client->dailyCheckIns()->create(['check_in_date' => '2026-10-18', 'bodyweight' => 82]);
        $client->dailyCheckIns()->create(['check_in_date' => '2026-10-20', 'bodyweight' => 85]);
        $this->artisan('coaching:backfill-handovers')->assertSuccessful();
        $handover = CoachingHandover::sole();
        $this->assertSame('2026-10-19', $handover->snapshot['end_date']);
        $this->assertEquals([['date' => '2026-10-18', 'kg' => 82]], $handover->snapshot['weights']);
        $original = $handover->pdf_base64;
        $this->actingAs($client)->getJson('/api/user')->assertJsonPath('data.has_coaching_handovers', true)->assertJsonPath('data.has_active_coaching', false);
        $this->get('/api/coaching-handovers/'.$handover->id.'/pdf?download=1')->assertOk();
        $this->artisan('coaching:backfill-handovers')->assertSuccessful();
        $this->assertDatabaseCount('coaching_handovers', 1);
        $this->assertSame($original, $handover->fresh()->pdf_base64);
        $this->assertSame('inactive', TrainerClient::sole()->status);
        $this->assertTrue($client->fresh()->is_active);
    }

    /** @return array{User, User} */
    private function pair(bool $bodybuilding = true): array
    {
        $coach = User::factory()->create(['role' => 'coach', 'is_bodybuilding_coach' => $bodybuilding]);
        $client = User::factory()->create();
        TrainerClient::create(['trainer_id' => $coach->id, 'client_id' => $client->id, 'start_date' => '2026-10-01']);

        return [$coach, $client];
    }
}
