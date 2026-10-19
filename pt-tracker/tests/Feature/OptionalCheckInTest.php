<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OptionalCheckInTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
        $this->travelTo(now()->setDate(2026, 10, 19)->setTime(21, 0));
    }

    public function test_a_client_can_save_a_blank_day_without_entering_a_date_or_measurements(): void
    {
        $client = User::factory()->create();
        $this->actingAs($client)->postJson('/api/clients/'.$client->id.'/daily-check-ins', [])
            ->assertCreated()->assertJsonPath('data.bodyweight', null)->assertJsonPath('data.sleep_hours', null)
            ->assertJsonPath('data.steps', null)->assertJsonPath('data.notes', null);
        $this->assertTrue($client->dailyCheckIns()->whereDate('check_in_date', '2026-10-19')->whereNull('bodyweight')->exists());
    }

    public function test_clearing_daily_answers_preserves_the_date_and_pose_submission(): void
    {
        $client = User::factory()->create();
        $record = $client->dailyCheckIns()->create(['check_in_date' => '2026-10-18', 'bodyweight' => 80, 'steps' => 5000, 'notes' => 'Earlier details']);
        $record->forceFill(['submitted_at' => now(), 'photo_version' => 2])->save();
        $submittedAt = $record->submitted_at->toISOString();
        $this->actingAs($client)->patchJson('/api/clients/'.$client->id.'/daily-check-ins/'.$record->id, [
            'check_in_date' => '', 'bodyweight' => '', 'steps' => '', 'notes' => '',
        ])->assertOk()->assertJsonPath('data.bodyweight', null)->assertJsonPath('data.steps', null)
            ->assertJsonPath('data.notes', null)->assertJsonPath('data.submitted_at', $submittedAt)
            ->assertJsonPath('data.photo_version', 2);
        $this->assertSame('2026-10-18', $record->fresh()->check_in_date->toDateString());
        $this->assertDatabaseCount('daily_check_ins', 1);
    }

    public function test_nutrition_accepts_blank_answers_and_defaults_a_blank_date_without_changing_other_data(): void
    {
        $client = User::factory()->create();
        $url = '/api/clients/'.$client->id.'/end-of-day-nutrition';
        $this->actingAs($client)->putJson($url, [])->assertOk()->assertJsonPath('data', null);
        $this->assertDatabaseCount('daily_check_ins', 0);
        $this->putJson($url, ['check_in_date' => '', 'hunger_level' => 3])->assertOk()->assertJsonPath('data.hunger_level', 3);
        $this->getJson($url.'?date=')->assertOk()->assertJsonPath('data.hunger_level', 3);
        $this->putJson($url, ['check_in_date' => '', 'hunger_level' => ''])->assertOk()
            ->assertJsonPath('data.hunger_level', null)->assertJsonPath('data.nutrition_completed_at', null);
        $this->assertTrue($client->dailyCheckIns()->whereDate('check_in_date', '2026-10-19')->whereNull('hunger_level')->exists());
    }
}
