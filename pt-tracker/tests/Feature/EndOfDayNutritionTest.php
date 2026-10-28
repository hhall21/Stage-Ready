<?php

namespace Tests\Feature;

use App\Models\TrainerClient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EndOfDayNutritionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
        $this->travelTo(now()->setDate(2026, 10, 18)->setTime(21, 0));
    }

    public function test_client_completes_nutrition_without_changing_submitted_poses_or_other_daily_data(): void
    {
        $client = User::factory()->coached()->create();
        $record = $client->dailyCheckIns()->create(['check_in_date' => '2026-10-18', 'bodyweight' => 80, 'notes' => 'Morning check-in.']);
        $record->forceFill(['submitted_at' => now()->subHours(12), 'photo_version' => 7])->save();
        $submittedAt = $record->submitted_at->toISOString();
        $url = '/api/clients/'.$client->id.'/end-of-day-nutrition';
        $this->actingAs($client)->putJson($url, ['check_in_date' => '2026-10-18', 'hunger_level' => 4, 'appetite_level' => 2, 'dietary_difficulties' => 'Hard to finish dinner.', 'bodyweight' => 999])->assertOk()->assertJsonPath('data.hunger_level', 4)->assertJsonPath('data.nutrition_completed_at', now()->toISOString());
        $this->assertDatabaseCount('daily_check_ins', 1);
        $record->refresh();
        $this->assertSame('80.00', $record->bodyweight);
        $this->assertSame('Morning check-in.', $record->notes);
        $this->assertSame($submittedAt, $record->submitted_at->toISOString());
        $this->assertSame(7, $record->photo_version);
        $this->getJson($url.'?date=2026-10-18')->assertOk()->assertJsonPath('data.appetite_level', 2);
        $this->patchJson('/api/clients/'.$client->id.'/daily-check-ins/'.$record->id, ['bodyweight' => 81])->assertOk();
        $this->getJson($url.'?date=2026-10-18')->assertJsonPath('data.nutrition_completed_at', now()->toISOString())->assertJsonPath('data.hunger_level', 4);
    }

    public function test_nutrition_can_create_the_day_then_update_it_without_duplicates_and_clear_completion(): void
    {
        $client = User::factory()->coached()->create();
        $url = '/api/clients/'.$client->id.'/end-of-day-nutrition';
        $this->actingAs($client)->getJson($url.'?date=2026-10-17')->assertJsonPath('data', null);
        $this->putJson($url, ['check_in_date' => '2026-10-17', 'hunger_level' => 3])->assertOk()->assertJsonPath('data.hunger_level', 3);
        $this->putJson($url, ['check_in_date' => '2026-10-17', 'appetite_level' => 2])->assertOk()->assertJsonPath('data.hunger_level', 3);
        $this->assertDatabaseCount('daily_check_ins', 1);
        $this->putJson($url, ['check_in_date' => '2026-10-17', 'hunger_level' => null, 'appetite_level' => null])->assertOk()->assertJsonPath('data.nutrition_completed_at', null);
        $this->putJson($url, ['check_in_date' => '2026-10-16'])->assertOk()->assertJsonPath('data', null);
        $this->assertDatabaseCount('daily_check_ins', 1);
    }

    public function test_only_the_client_can_complete_nutrition_and_current_coach_can_read_it(): void
    {
        $client = User::factory()->create();
        $coach = User::factory()->create(['role' => 'coach']);
        TrainerClient::create(['trainer_id' => $coach->id, 'client_id' => $client->id, 'start_date' => today()]);
        $url = '/api/clients/'.$client->id.'/end-of-day-nutrition';
        $payload = ['check_in_date' => '2026-10-18', 'hunger_level' => 3];
        $this->putJson($url, $payload)->assertUnauthorized();
        $this->actingAs($coach)->putJson($url, $payload)->assertForbidden();
        $this->getJson($url.'?date=2026-10-18')->assertOk();
        $this->actingAs(User::factory()->create())->getJson($url.'?date=2026-10-18')->assertForbidden();
        $this->actingAs($client)->putJson($url, $payload)->assertOk();
        $this->actingAs($coach)->deleteJson('/api/clients/'.$client->id.'/coaching')->assertOk();
        $this->getJson($url.'?date=2026-10-18')->assertForbidden();
        $this->actingAs($client)->getJson($url.'?date=2026-10-18')->assertJsonPath('data.hunger_level', 3);
    }

    public function test_dates_scales_and_server_completion_fields_are_validated(): void
    {
        $client = User::factory()->coached()->create();
        $url = '/api/clients/'.$client->id.'/end-of-day-nutrition';
        $this->actingAs($client)->putJson($url, ['check_in_date' => '2026-10-19', 'hunger_level' => 0, 'appetite_level' => 6, 'nutrition_completed_at' => now()->toISOString(), 'submitted_at' => now()->toISOString(), 'client_id' => $client->id])->assertUnprocessable()->assertJsonValidationErrors(['check_in_date', 'hunger_level', 'appetite_level', 'nutrition_completed_at', 'submitted_at', 'client_id']);
        $this->getJson($url.'?date=2026-10-19')->assertUnprocessable();
        $this->assertDatabaseCount('daily_check_ins', 0);
    }
}
