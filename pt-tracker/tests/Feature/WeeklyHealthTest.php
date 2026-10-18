<?php

namespace Tests\Feature;

use App\Models\TrainerClient;
use App\Models\User;
use App\Models\WeeklyHealthReading;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WeeklyHealthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
        $this->travelTo(now()->setDate(2026, 10, 18)->setTime(12, 0));
    }

    public function test_only_the_current_coach_can_enable_monitoring_and_clients_cannot_enable_it(): void
    {
        [$coach, $client] = $this->pair();
        $url = '/api/clients/'.$client->id.'/weekly-health';
        $this->getJson($url)->assertUnauthorized();
        $this->actingAs($client)->getJson($url)->assertOk()->assertJsonPath('monitoring_enabled', false);
        $this->patchJson($url.'/settings', ['enabled' => true])->assertForbidden();
        $this->postJson($url.'/readings', $this->reading())->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'coach']))->patchJson($url.'/settings', ['enabled' => true])->assertForbidden();
        $this->actingAs($coach)->patchJson($url.'/settings', ['enabled' => true])->assertOk()
            ->assertJsonPath('monitoring_enabled', true)->assertJsonPath('is_due', true)->assertJsonPath('due_on', '2026-10-18');
        $this->postJson($url.'/readings', $this->reading())->assertForbidden();
        $this->actingAs($client)->postJson($url.'/readings', $this->reading())->assertCreated();
        $this->actingAs(User::factory()->create())->getJson($url)->assertForbidden();
    }

    public function test_reading_becomes_due_after_seven_days_and_older_entries_do_not_postpone_it(): void
    {
        [$coach, $client] = $this->pair();
        $url = '/api/clients/'.$client->id.'/weekly-health';
        $this->actingAs($coach)->patchJson($url.'/settings', ['enabled' => true])->assertOk();
        $this->actingAs($client)->postJson($url.'/readings', $this->reading())->assertCreated();
        $this->getJson($url)->assertJsonPath('is_due', false)->assertJsonPath('due_on', '2026-10-25');
        $this->travel(6)->days();
        $this->getJson($url)->assertJsonPath('is_due', false);
        $this->travel(1)->days();
        $this->getJson($url)->assertJsonPath('is_due', true);
        $this->postJson($url.'/readings', $this->reading('2026-10-17'))->assertCreated();
        $this->getJson($url)->assertJsonPath('is_due', true)->assertJsonPath('due_on', '2026-10-25');
        $this->postJson($url.'/readings', $this->reading('2026-10-25'))->assertCreated();
        $this->getJson($url)->assertJsonPath('is_due', false)->assertJsonPath('due_on', '2026-11-01');
    }

    public function test_validation_rejects_missing_invalid_future_and_duplicate_readings(): void
    {
        [$coach, $client] = $this->pair();
        $url = '/api/clients/'.$client->id.'/weekly-health';
        $this->actingAs($coach)->patchJson($url.'/settings', ['enabled' => true]);
        $this->actingAs($client)->postJson($url.'/readings', [])->assertUnprocessable()->assertJsonValidationErrors(['reading_date', 'systolic', 'diastolic', 'resting_heart_rate']);
        $this->postJson($url.'/readings', [...$this->reading('2026-10-19'), 'systolic' => 80, 'diastolic' => 120, 'resting_heart_rate' => 0, 'client_id' => $client->id])
            ->assertUnprocessable()->assertJsonValidationErrors(['reading_date', 'systolic', 'resting_heart_rate', 'client_id']);
        $this->postJson($url.'/readings', [...$this->reading(), 'systolic' => 120.5, 'diastolic' => -1, 'resting_heart_rate' => 301])
            ->assertUnprocessable()->assertJsonValidationErrors(['systolic', 'diastolic', 'resting_heart_rate']);
        $this->postJson($url.'/readings', $this->reading())->assertCreated();
        $this->postJson($url.'/readings', $this->reading())->assertUnprocessable()->assertJsonValidationErrors('reading_date');
        $this->assertDatabaseCount('weekly_health_readings', 1);
    }

    public function test_only_the_client_can_correct_their_own_readings_and_disabling_keeps_history(): void
    {
        [$coach, $client] = $this->pair();
        $url = '/api/clients/'.$client->id.'/weekly-health';
        $this->actingAs($coach)->patchJson($url.'/settings', ['enabled' => true]);
        $id = $this->actingAs($client)->postJson($url.'/readings', $this->reading())->json('data.id');
        $other = WeeklyHealthReading::create(['client_id' => User::factory()->create()->id, ...$this->reading()]);
        $this->putJson($url.'/readings/'.$other->id, $this->reading())->assertNotFound();
        $this->postJson($url.'/readings', $this->reading('2026-10-17'))->assertCreated();
        $this->putJson($url.'/readings/'.$id, $this->reading('2026-10-17'))->assertUnprocessable();
        $this->actingAs($coach)->putJson($url.'/readings/'.$id, $this->reading())->assertForbidden();
        $this->getJson($url)->assertOk()->assertJsonCount(2, 'readings.data');
        $this->patchJson($url.'/settings', ['enabled' => false])->assertOk()->assertJsonPath('monitoring_enabled', false)->assertJsonPath('due_on', null);
        $this->actingAs($client)->putJson($url.'/readings/'.$id, [...$this->reading(), 'systolic' => 122])->assertOk()->assertJsonPath('data.systolic', 122);
        $this->postJson($url.'/readings', $this->reading('2026-10-16'))->assertForbidden();
        $this->getJson($url)->assertJsonCount(2, 'readings.data');
        $this->actingAs($coach)->deleteJson('/api/clients/'.$client->id.'/coaching')->assertOk();
        $this->getJson($url)->assertForbidden();
        $this->actingAs($client)->getJson($url)->assertOk()->assertJsonCount(2, 'readings.data');
    }

    public function test_inactive_expired_and_future_assignments_cannot_require_monitoring(): void
    {
        [$coach, $client] = $this->pair();
        $assignment = TrainerClient::firstOrFail();
        $url = '/api/clients/'.$client->id.'/weekly-health';
        foreach ([['status' => 'inactive'], ['end_date' => '2026-10-17'], ['start_date' => '2026-10-19']] as $change) {
            $assignment->forceFill(['status' => 'active', 'start_date' => '2026-10-01', 'end_date' => null, 'health_monitoring_enabled_at' => now(), ...$change])->save();
            $this->actingAs($client)->getJson($url)->assertJsonPath('monitoring_enabled', false);
            $this->actingAs($coach)->patchJson($url.'/settings', ['enabled' => true])->assertForbidden();
        }
        $assignment->forceFill(['start_date' => '2026-10-01', 'end_date' => null])->save();
        $coach->forceFill(['is_active' => false])->save();
        $this->actingAs($client)->getJson($url)->assertJsonPath('monitoring_enabled', false);
    }

    public function test_reenabled_monitoring_requires_a_current_reading_and_repeat_enable_does_not_reset_date(): void
    {
        [$coach, $client] = $this->pair();
        $url = '/api/clients/'.$client->id.'/weekly-health';
        $this->actingAs($coach)->patchJson($url.'/settings', ['enabled' => true]);
        $this->actingAs($client)->postJson($url.'/readings', $this->reading());
        $this->travel(1)->days();
        $this->actingAs($coach)->patchJson($url.'/settings', ['enabled' => true])->assertJsonPath('is_due', false);
        $this->patchJson($url.'/settings', ['enabled' => false]);
        $this->patchJson($url.'/settings', ['enabled' => true])->assertJsonPath('is_due', true)->assertJsonPath('due_on', '2026-10-19');
    }

    public function test_each_coach_controls_their_own_requirement_and_history_is_paginated(): void
    {
        [$coach, $client] = $this->pair();
        $secondCoach = User::factory()->create(['role' => 'coach']);
        TrainerClient::create(['trainer_id' => $secondCoach->id, 'client_id' => $client->id, 'start_date' => '2026-10-01']);
        $url = '/api/clients/'.$client->id.'/weekly-health';
        $this->actingAs($coach)->patchJson($url.'/settings', ['enabled' => true])->assertOk();
        $this->actingAs($secondCoach)->getJson($url)->assertJsonPath('monitoring_enabled', true)->assertJsonPath('coach_enabled', false);
        $this->patchJson($url.'/settings', ['enabled' => false])->assertJsonPath('monitoring_enabled', true);
        for ($day = 1; $day <= 13; $day++) {
            WeeklyHealthReading::create(['client_id' => $client->id, ...$this->reading(sprintf('2026-10-%02d', $day))]);
        }
        $this->getJson($url)->assertJsonPath('readings.total', 13)->assertJsonPath('readings.last_page', 2)->assertJsonCount(12, 'readings.data');
        $this->getJson($url.'?page=2')->assertJsonCount(1, 'readings.data');
        $this->actingAs($coach)->patchJson($url.'/settings', ['enabled' => false])->assertJsonPath('monitoring_enabled', false);
    }

    /** @return array{User, User} */
    private function pair(): array
    {
        $coach = User::factory()->create(['role' => 'coach']);
        $client = User::factory()->create();
        TrainerClient::create(['trainer_id' => $coach->id, 'client_id' => $client->id, 'start_date' => '2026-10-01']);

        return [$coach, $client];
    }

    /** @return array{reading_date: string, systolic: int, diastolic: int, resting_heart_rate: int, notes: string} */
    private function reading(string $date = '2026-10-18'): array
    {
        return ['reading_date' => $date, 'systolic' => 120, 'diastolic' => 80, 'resting_heart_rate' => 65, 'notes' => 'Morning reading'];
    }
}
