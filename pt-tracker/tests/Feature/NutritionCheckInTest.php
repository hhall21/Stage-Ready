<?php

namespace Tests\Feature;

use App\Models\TrainerClient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NutritionCheckInTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
        $this->travelTo(now()->setDate(2026, 10, 18)->setTime(12, 0));
    }

    public function test_clients_record_edit_clear_and_retrieve_nutrition_without_pose_photos(): void
    {
        $client = User::factory()->create();
        $url = '/api/clients/'.$client->id.'/daily-check-ins';
        $record = $this->actingAs($client)->postJson($url, ['check_in_date' => '2026-10-18', 'hunger_level' => 4, 'appetite_level' => 2, 'digestive_discomfort' => 3, 'digestion_notes' => 'Bloating after dinner.', 'dietary_difficulties' => 'Struggled to finish the last meal.'])->assertCreated()->assertJsonPath('data.submitted_at', null);
        $id = $record->json('data.id');
        $this->getJson($url.'/'.$id)->assertJsonPath('data.hunger_level', 4)->assertJsonPath('data.appetite_level', 2)->assertJsonPath('data.digestive_discomfort', 3);
        $this->patchJson($url.'/'.$id, ['hunger_level' => 5])->assertOk()->assertJsonPath('data.appetite_level', 2)->assertJsonPath('data.dietary_difficulties', 'Struggled to finish the last meal.');
        $this->patchJson($url.'/'.$id, ['digestive_discomfort' => null, 'digestion_notes' => null])->assertOk()->assertJsonPath('data.digestive_discomfort', null)->assertJsonPath('data.digestion_notes', null);
        $this->getJson($url)->assertJsonPath('data.0.hunger_level', 5);
    }

    public function test_optional_fields_preserve_old_check_ins_and_validate_scales_and_text(): void
    {
        $client = User::factory()->create();
        $url = '/api/clients/'.$client->id.'/daily-check-ins';
        $this->actingAs($client)->postJson($url, ['check_in_date' => '2026-10-17', 'bodyweight' => 80])->assertCreated()->assertJsonPath('data.hunger_level', null);
        $this->postJson($url, ['check_in_date' => '2026-10-18', 'hunger_level' => 0, 'appetite_level' => 6, 'digestive_discomfort' => 1.5, 'digestion_notes' => str_repeat('a', 5001), 'dietary_difficulties' => ['invalid']])->assertUnprocessable()->assertJsonValidationErrors(['hunger_level', 'appetite_level', 'digestive_discomfort', 'digestion_notes', 'dietary_difficulties']);
        $this->assertDatabaseCount('daily_check_ins', 1);
    }

    public function test_coaches_can_read_but_cannot_write_client_nutrition_and_strangers_cannot_read_it(): void
    {
        $coach = User::factory()->create(['role' => 'coach']);
        $client = User::factory()->create();
        TrainerClient::create(['trainer_id' => $coach->id, 'client_id' => $client->id, 'start_date' => '2026-10-01']);
        $record = $client->dailyCheckIns()->create(['check_in_date' => '2026-10-18', 'dietary_difficulties' => 'Private nutrition note.']);
        $url = '/api/clients/'.$client->id.'/daily-check-ins';
        $this->actingAs($coach)->getJson($url)->assertJsonPath('data.0.dietary_difficulties', 'Private nutrition note.');
        $this->patchJson($url.'/'.$record->id, ['hunger_level' => 3])->assertForbidden();
        $this->actingAs(User::factory()->create())->getJson($url)->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'coach']))->getJson($url)->assertForbidden();
        $this->actingAs($coach)->deleteJson('/api/clients/'.$client->id.'/coaching')->assertOk();
        $this->getJson($url)->assertForbidden();
        $this->actingAs($client)->getJson($url)->assertOk();
    }

    public function test_review_summary_excludes_missing_values_and_out_of_window_reports(): void
    {
        $coach = User::factory()->create(['role' => 'coach']);
        $client = User::factory()->create();
        TrainerClient::create(['trainer_id' => $coach->id, 'client_id' => $client->id, 'start_date' => '2026-10-01']);
        $client->dailyCheckIns()->create(['check_in_date' => '2026-10-18', 'hunger_level' => 5, 'appetite_level' => 2, 'digestive_discomfort' => 1, 'dietary_difficulties' => 'Could not finish dinner.']);
        $client->dailyCheckIns()->create(['check_in_date' => '2026-10-17', 'hunger_level' => 3, 'digestion_notes' => 'Some bloating.']);
        $client->dailyCheckIns()->create(['check_in_date' => '2026-10-16']);
        $client->dailyCheckIns()->create(['check_in_date' => '2026-10-08', 'hunger_level' => 1, 'dietary_difficulties' => 'Off-season only note.']);
        $client->dailyCheckIns()->create(['check_in_date' => '2026-10-19', 'hunger_level' => 1, 'dietary_difficulties' => 'Future note.']);
        $url = '/api/clients/'.$client->id.'/coaching-reviews';
        $this->actingAs($coach)->getJson($url)->assertJsonPath('summary.nutrition.hunger_level.readings', 3)->assertJsonCount(3, 'summary.nutrition.reports');
        $this->patchJson($url.'/schedule', ['phase' => 'prep'])->assertOk()->assertJsonPath('summary.nutrition.hunger_level.average', 4)->assertJsonPath('summary.nutrition.hunger_level.readings', 2)->assertJsonPath('summary.nutrition.appetite_level.average', 2)->assertJsonPath('summary.nutrition.appetite_level.readings', 1)->assertJsonPath('summary.nutrition.digestive_discomfort.average', 1)->assertJsonCount(2, 'summary.nutrition.reports')->assertJsonPath('summary.nutrition.reports.0.date', '2026-10-18');
        $this->assertDatabaseCount('coaching_reviews', 0);
    }

    public function test_empty_nutrition_summary_is_unknown_instead_of_zero(): void
    {
        $client = User::factory()->create();
        $client->dailyCheckIns()->create(['check_in_date' => '2026-10-18', 'bodyweight' => 80]);
        $this->actingAs($client)->getJson('/api/clients/'.$client->id.'/coaching-reviews')->assertOk()->assertJsonPath('summary.nutrition.hunger_level.average', null)->assertJsonPath('summary.nutrition.appetite_level.readings', 0)->assertJsonPath('summary.nutrition.digestive_discomfort.average', null)->assertJsonCount(0, 'summary.nutrition.reports');
    }
}
