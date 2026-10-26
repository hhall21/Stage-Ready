<?php

namespace Tests\Feature;

use App\Models\TrainerClient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompetitionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
        $this->travelTo(now()->setDate(2026, 10, 26)->setTime(12, 0));
    }

    public function test_only_coaches_can_view_the_competition_list_and_manage_assigned_clients(): void
    {
        [$coach, $client] = $this->pair();
        $url = '/api/clients/'.$client->id.'/competitions';
        $this->getJson('/api/competitions')->assertUnauthorized();
        $this->postJson($url, $this->competition())->assertUnauthorized();
        $this->actingAs($client)->getJson('/api/competitions')->assertForbidden();
        $this->postJson($url, $this->competition())->assertForbidden();
        $otherCoach = User::factory()->create(['role' => 'coach']);
        $this->actingAs($otherCoach)->postJson($url, $this->competition())->assertForbidden();
        $this->getJson('/api/competitions')->assertOk()->assertJsonCount(0, 'data');

        $id = $this->actingAs($coach)->postJson($url, $this->competition())->assertCreated()
            ->assertJsonPath('data.competition_date', '2026-12-05')
            ->assertJsonPath('data.location', null)->json('data.id');
        foreach ([$client, $otherCoach] as $user) {
            $this->actingAs($user)->putJson($url.'/'.$id, $this->competition())->assertForbidden();
            $this->deleteJson($url.'/'.$id)->assertForbidden();
        }
        $this->assertDatabaseCount('competitions', 1);
    }

    public function test_list_groups_all_dates_by_client_and_excludes_clients_without_competitions(): void
    {
        [$coach, $client] = $this->pair('Alice');
        $later = $client->competitions()->create($this->competition('2027-06-05', 'Summer show'));
        $past = $client->competitions()->create($this->competition('2025-11-01', 'Past show'));
        $emptyClient = User::factory()->create(['name' => 'No competitions']);
        $coach->clients()->attach($emptyClient, ['start_date' => today(), 'status' => 'active']);
        $second = User::factory()->create(['name' => 'Beth']);
        $coach->clients()->attach($second, ['start_date' => today(), 'status' => 'active']);
        $second->competitions()->create($this->competition());
        $unassigned = User::factory()->create();
        $unassigned->competitions()->create($this->competition());

        $this->actingAs($coach)->getJson('/api/competitions')->assertOk()
            ->assertJsonPath('total', 2)->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $client->id)->assertJsonPath('data.0.name', 'Alice')
            ->assertJsonCount(2, 'data.0.competitions')
            ->assertJsonPath('data.0.competitions.0.id', $past->id)
            ->assertJsonPath('data.0.competitions.1.id', $later->id)
            ->assertJsonPath('data.1.id', $second->id)
            ->assertJsonMissingPath('data.0.email')->assertJsonMissingPath('data.0.pivot');
    }

    public function test_inactive_expired_future_and_non_client_assignments_are_excluded_and_cannot_be_managed(): void
    {
        [$coach, $client] = $this->pair();
        $competition = $client->competitions()->create($this->competition());
        $assignment = TrainerClient::firstOrFail();
        $url = '/api/clients/'.$client->id.'/competitions';
        foreach ([['status' => 'inactive'], ['end_date' => '2026-10-25'], ['start_date' => '2026-10-27']] as $change) {
            $assignment->forceFill(['status' => 'active', 'start_date' => '2026-10-01', 'end_date' => null, ...$change])->save();
            $this->actingAs($coach)->getJson('/api/competitions')->assertOk()
                ->assertJsonCount(0, 'data')->assertJsonCount(0, 'client_options');
            $this->postJson($url, $this->competition())->assertForbidden();
            $this->putJson($url.'/'.$competition->id, $this->competition())->assertForbidden();
            $this->deleteJson($url.'/'.$competition->id)->assertForbidden();
        }
        $assignment->forceFill(['status' => 'active', 'start_date' => '2026-10-01', 'end_date' => '2026-10-26'])->save();
        $this->getJson('/api/competitions')->assertJsonCount(1, 'data');
        foreach ([['is_active' => false], ['is_active' => true, 'role' => 'coach']] as $change) {
            $client->forceFill($change)->save();
            $this->getJson('/api/competitions')->assertJsonCount(0, 'data');
            $this->postJson($url, $this->competition())->assertForbidden();
        }
        $coach->forceFill(['is_active' => false])->save();
        $this->actingAs($coach)->getJson('/api/competitions')->assertForbidden();
    }

    public function test_names_and_dates_are_required_and_optional_fields_and_client_ownership_are_validated(): void
    {
        [$coach, $client] = $this->pair();
        $url = '/api/clients/'.$client->id.'/competitions';
        $this->actingAs($coach)->postJson($url, [])->assertUnprocessable()
            ->assertJsonValidationErrors(['competition_name', 'competition_date']);
        $this->postJson($url, [
            'competition_name' => str_repeat('a', 201), 'competition_date' => '2026-02-30',
            'location' => str_repeat('a', 201), 'category' => str_repeat('a', 151),
            'notes' => str_repeat('a', 5001), 'client_id' => $client->id, 'coach_id' => $coach->id,
        ])->assertUnprocessable()->assertJsonValidationErrors([
            'competition_name', 'competition_date', 'location', 'category', 'notes', 'client_id', 'coach_id',
        ]);
        $this->postJson($url, [...$this->competition(), 'competition_date' => '05/12/2026'])->assertUnprocessable();
        $id = $this->postJson($url, [
            ...$this->competition(), 'location' => '', 'category' => '',
        ])->assertCreated()->assertJsonPath('data.location', null)
            ->assertJsonPath('data.category', null)->assertJsonMissingPath('data.notes')
            ->assertJsonMissingPath('data.placing')->json('data.id');
        $this->assertDatabaseHas('competitions', ['id' => $id, 'client_id' => $client->id]);
    }

    public function test_dates_can_be_corrected_without_changing_the_client_or_legacy_details(): void
    {
        [$coach, $client] = $this->pair();
        $competition = $client->competitions()->create([
            ...$this->competition(), 'location' => 'Old venue', 'category' => 'Classic',
            'target_weight' => 85, 'placing' => '2nd', 'notes' => 'Existing notes',
        ]);
        $url = '/api/clients/'.$client->id.'/competitions/'.$competition->id;
        $this->actingAs($coach)->putJson($url, [
            ...$this->competition('2027-01-23', 'Rescheduled show'),
            'location' => 'New venue', 'category' => null, 'target_weight' => 0,
        ])->assertOk()->assertJsonPath('data.competition_date', '2027-01-23')
            ->assertJsonPath('data.location', 'New venue')->assertJsonPath('data.category', null)
            ->assertJsonMissingPath('data.placing')->assertJsonMissingPath('data.notes');
        $this->assertDatabaseHas('competitions', [
            'id' => $competition->id, 'client_id' => $client->id, 'competition_name' => 'Rescheduled show',
            'target_weight' => 85, 'placing' => '2nd', 'notes' => 'Existing notes',
        ]);
        $this->putJson($url, $this->competition('invalid'))->assertUnprocessable();
        $this->assertSame('Rescheduled show', $competition->fresh()->competition_name);
    }

    public function test_competitions_cannot_be_edited_or_removed_through_another_clients_url(): void
    {
        [$coach, $client] = $this->pair();
        $other = User::factory()->create();
        $competition = $other->competitions()->create($this->competition());
        $url = '/api/clients/'.$client->id.'/competitions/'.$competition->id;
        $this->actingAs($coach)->putJson($url, $this->competition())->assertNotFound();
        $this->deleteJson($url)->assertNotFound();
        $this->assertDatabaseHas('competitions', ['id' => $competition->id, 'client_id' => $other->id]);
    }

    public function test_removing_the_last_competition_hides_the_client_and_preserves_other_records(): void
    {
        [$coach, $client] = $this->pair();
        $first = $client->competitions()->create($this->competition());
        $last = $client->competitions()->create($this->competition('2027-05-08'));
        $workout = $client->workouts()->create(['workout_name' => 'Existing workout', 'workout_date' => today()]);
        $url = '/api/clients/'.$client->id.'/competitions/';
        $this->actingAs($coach)->deleteJson($url.$first->id)->assertOk();
        $this->getJson('/api/competitions')->assertJsonCount(1, 'data')->assertJsonCount(1, 'data.0.competitions');
        $this->deleteJson($url.$last->id)->assertOk();
        $this->getJson('/api/competitions')->assertJsonCount(0, 'data')->assertJsonPath('total', 0);
        $this->assertDatabaseHas('users', ['id' => $client->id]);
        $this->assertDatabaseHas('workouts', ['id' => $workout->id]);
        $this->assertDatabaseHas('trainer_clients', ['client_id' => $client->id, 'status' => 'active']);
    }

    public function test_ending_coaching_removes_access_and_keeps_competitions(): void
    {
        [$coach, $client] = $this->pair();
        $competition = $client->competitions()->create($this->competition());
        $this->actingAs($coach)->deleteJson('/api/clients/'.$client->id.'/coaching')->assertOk();
        $this->getJson('/api/competitions')->assertJsonCount(0, 'data');
        $this->putJson('/api/clients/'.$client->id.'/competitions/'.$competition->id, $this->competition())->assertForbidden();
        $this->assertDatabaseHas('competitions', ['id' => $competition->id, 'client_id' => $client->id]);
    }

    public function test_client_groups_are_paginated_without_losing_any_competitions(): void
    {
        [$coach, $first] = $this->pair('Client 00');
        $first->competitions()->create($this->competition('2026-10-25'));
        for ($number = 1; $number <= 12; $number++) {
            $client = User::factory()->create(['name' => sprintf('Client %02d', $number)]);
            $coach->clients()->attach($client, ['start_date' => today(), 'status' => 'active']);
            $client->competitions()->create($this->competition());
            $client->competitions()->create($this->competition('2027-04-10'));
        }
        $this->actingAs($coach)->getJson('/api/competitions')->assertOk()
            ->assertJsonPath('total', 13)->assertJsonPath('last_page', 2)->assertJsonCount(12, 'data');
        $this->getJson('/api/competitions?page=2')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Client 12')->assertJsonCount(2, 'data.0.competitions');
        $this->getJson('/api/competitions?period=upcoming')->assertOk()->assertJsonPath('total', 12)
            ->assertJsonPath('last_page', 1)->assertJsonCount(12, 'data');
        $this->getJson('/api/competitions?period=past')->assertOk()->assertJsonPath('total', 1)
            ->assertJsonPath('last_page', 1)->assertJsonPath('data.0.id', $first->id);
        $this->getJson('/api/competitions?period=upcoming&client_id='.$client->id)->assertOk()
            ->assertJsonPath('total', 1)->assertJsonPath('last_page', 1)
            ->assertJsonPath('data.0.name', 'Client 12')->assertJsonCount(2, 'data.0.competitions');
    }

    public function test_client_filter_combines_with_period_and_options_only_include_assigned_clients_with_competitions(): void
    {
        [$coach, $alice] = $this->pair('Alice');
        $past = $alice->competitions()->create($this->competition('2026-10-25'));
        $future = $alice->competitions()->create($this->competition('2026-11-25'));
        $bob = User::factory()->create(['name' => 'Bob']);
        $empty = User::factory()->create(['name' => 'Empty client']);
        foreach ([$bob, $empty] as $client) {
            $coach->clients()->attach($client, ['start_date' => today(), 'status' => 'active']);
        }
        $bob->competitions()->create($this->competition('2026-12-01'));
        $unassigned = User::factory()->create(['name' => 'Unassigned']);
        $unassigned->competitions()->create($this->competition());
        $url = '/api/competitions?client_id='.$alice->id;

        $this->actingAs($coach)->getJson($url)->assertOk()->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.id', $alice->id)->assertJsonCount(2, 'data.0.competitions')
            ->assertJsonCount(2, 'client_options')->assertJsonPath('client_options.0.id', $alice->id)
            ->assertJsonPath('client_options.1.id', $bob->id)->assertJsonMissingPath('client_options.0.email');
        $this->getJson($url.'&period=past')->assertOk()->assertJsonPath('total', 1)
            ->assertJsonCount(1, 'data.0.competitions')->assertJsonPath('data.0.competitions.0.id', $past->id)
            ->assertJsonCount(2, 'client_options');
        $this->getJson($url.'&period=upcoming')->assertOk()->assertJsonPath('total', 1)
            ->assertJsonCount(1, 'data.0.competitions')->assertJsonPath('data.0.competitions.0.id', $future->id);
        $this->getJson('/api/competitions?client_id='.$bob->id.'&period=past')->assertOk()
            ->assertJsonCount(0, 'data')->assertJsonCount(2, 'client_options');
        foreach ([$empty->id, $unassigned->id, 999999] as $id) {
            $this->getJson('/api/competitions?client_id='.$id)->assertOk()
                ->assertJsonCount(0, 'data')->assertJsonCount(2, 'client_options');
        }
    }

    public function test_invalid_client_filters_are_rejected(): void
    {
        [$coach] = $this->pair();
        $this->actingAs($coach);
        foreach (['0', '-1', 'invalid', '1.5', '', '1 OR 1=1'] as $id) {
            $this->getJson('/api/competitions?client_id='.urlencode($id))
                ->assertUnprocessable()->assertJsonValidationErrors('client_id');
        }
        $this->getJson('/api/competitions?client_id[]=1')->assertUnprocessable()->assertJsonValidationErrors('client_id');
    }

    public function test_period_filter_limits_both_clients_and_their_competitions_and_includes_today_as_upcoming(): void
    {
        [$coach, $mixed] = $this->pair('Alice');
        $past = $mixed->competitions()->create($this->competition('2026-10-25', 'Past show'));
        $today = $mixed->competitions()->create($this->competition('2026-10-26', 'Today show'));
        $future = $mixed->competitions()->create($this->competition('2026-10-27', 'Future show'));
        $pastOnly = User::factory()->create(['name' => 'Beth']);
        $futureOnly = User::factory()->create(['name' => 'Cara']);
        $empty = User::factory()->create(['name' => 'Dave']);
        foreach ([$pastOnly, $futureOnly, $empty] as $client) {
            $coach->clients()->attach($client, ['start_date' => today(), 'status' => 'active']);
        }
        $pastOnly->competitions()->create($this->competition('2026-01-01'));
        $futureOnly->competitions()->create($this->competition('2027-01-01'));

        $this->actingAs($coach)->getJson('/api/competitions?period=all')->assertOk()
            ->assertJsonPath('today', '2026-10-26')->assertJsonPath('total', 3)
            ->assertJsonCount(3, 'data.0.competitions');
        $this->getJson('/api/competitions?period=upcoming')->assertOk()
            ->assertJsonPath('total', 2)->assertJsonPath('data.0.id', $mixed->id)
            ->assertJsonCount(2, 'data.0.competitions')
            ->assertJsonPath('data.0.competitions.0.id', $today->id)
            ->assertJsonPath('data.0.competitions.0.period', 'upcoming')
            ->assertJsonPath('data.0.competitions.1.id', $future->id)
            ->assertJsonPath('data.1.id', $futureOnly->id);
        $this->getJson('/api/competitions?period=past')->assertOk()
            ->assertJsonPath('total', 2)->assertJsonPath('data.0.id', $mixed->id)
            ->assertJsonCount(1, 'data.0.competitions')
            ->assertJsonPath('data.0.competitions.0.id', $past->id)
            ->assertJsonPath('data.0.competitions.0.period', 'past')
            ->assertJsonPath('data.1.id', $pastOnly->id);
        foreach (['invalid', '', 'past[]'] as $period) {
            $this->getJson('/api/competitions?period='.urlencode($period))->assertUnprocessable()->assertJsonValidationErrors('period');
        }
        $this->getJson('/api/competitions?period[]=past')->assertUnprocessable()->assertJsonValidationErrors('period');
    }

    public function test_results_cannot_be_entered_or_read_until_the_day_after_the_show(): void
    {
        [$coach, $client] = $this->pair();
        $url = '/api/clients/'.$client->id.'/competitions';
        $today = $client->competitions()->create([
            ...$this->competition('2026-10-26'), 'placing' => '2nd', 'notes' => 'Existing result',
        ]);
        $future = $client->competitions()->create([
            ...$this->competition('2026-10-27'), 'placing' => '3rd', 'notes' => 'Existing future notes',
        ]);
        $this->actingAs($coach)->getJson('/api/competitions')->assertOk()
            ->assertJsonMissingPath('data.0.competitions.0.placing')->assertJsonMissingPath('data.0.competitions.0.notes')
            ->assertJsonMissingPath('data.0.competitions.1.placing')->assertJsonMissingPath('data.0.competitions.1.notes');
        foreach (['2026-10-26', '2026-10-27'] as $date) {
            foreach ([['placing' => '1st', 'notes' => 'Too early'], ['placing' => null, 'notes' => '']] as $results) {
                $payload = [...$this->competition($date), ...$results];
                $this->postJson($url, $payload)->assertUnprocessable()->assertJsonValidationErrors(['placing', 'notes']);
                $this->putJson($url.'/'.$today->id, $payload)->assertUnprocessable()->assertJsonValidationErrors(['placing', 'notes']);
            }
        }
        $this->assertDatabaseCount('competitions', 2);
        $this->travelTo(now()->setDate(2026, 10, 27)->startOfDay());
        $this->getJson('/api/competitions?period=past')->assertOk()->assertJsonCount(1, 'data.0.competitions')
            ->assertJsonPath('today', '2026-10-27')->assertJsonPath('data.0.competitions.0.id', $today->id)
            ->assertJsonPath('data.0.competitions.0.placing', '2nd')->assertJsonPath('data.0.competitions.0.notes', 'Existing result');
        $this->getJson('/api/competitions?period=upcoming')->assertOk()
            ->assertJsonPath('data.0.competitions.0.id', $future->id)
            ->assertJsonMissingPath('data.0.competitions.0.placing')->assertJsonMissingPath('data.0.competitions.0.notes');
        $this->putJson($url.'/'.$today->id, [
            ...$this->competition('2026-10-26'), 'placing' => '1st', 'notes' => 'Coach review',
        ])->assertOk()->assertJsonPath('data.placing', '1st')->assertJsonPath('data.notes', 'Coach review');
    }

    public function test_past_results_are_optional_can_be_corrected_and_cleared_and_have_length_limits(): void
    {
        [$coach, $client] = $this->pair();
        $url = '/api/clients/'.$client->id.'/competitions';
        $payload = [...$this->competition('2026-10-25'), 'placing' => '2nd', 'notes' => 'Strong routine'];
        $id = $this->actingAs($coach)->postJson($url, $payload)->assertCreated()
            ->assertJsonPath('data.period', 'past')->assertJsonPath('data.placing', '2nd')
            ->assertJsonPath('data.notes', 'Strong routine')->json('data.id');
        $this->putJson($url.'/'.$id, [
            ...$payload, 'placing' => str_repeat('a', 51), 'notes' => str_repeat('a', 5001),
        ])->assertUnprocessable()->assertJsonValidationErrors(['placing', 'notes']);
        $this->putJson($url.'/'.$id, [...$payload, 'placing' => [], 'notes' => []])
            ->assertUnprocessable()->assertJsonValidationErrors(['placing', 'notes']);
        $this->putJson($url.'/'.$id, $this->competition('2026-10-25'))->assertOk()
            ->assertJsonPath('data.placing', '2nd')->assertJsonPath('data.notes', 'Strong routine');
        $this->putJson($url.'/'.$id, [...$payload, 'placing' => '', 'notes' => null])->assertOk()
            ->assertJsonPath('data.placing', null)->assertJsonPath('data.notes', null);
        $this->postJson($url, $this->competition('2026-10-25'))->assertCreated()
            ->assertJsonPath('data.placing', null)->assertJsonPath('data.notes', null);
    }

    public function test_rescheduling_hides_results_without_clearing_them(): void
    {
        [$coach, $client] = $this->pair();
        $competition = $client->competitions()->create([
            ...$this->competition('2026-10-25'), 'placing' => '1st', 'notes' => 'Completed show',
        ]);
        $url = '/api/clients/'.$client->id.'/competitions/'.$competition->id;
        $this->actingAs($coach)->putJson($url, $this->competition('2026-10-27'))->assertOk()
            ->assertJsonPath('data.period', 'upcoming')->assertJsonMissingPath('data.placing')->assertJsonMissingPath('data.notes');
        $this->assertDatabaseHas('competitions', ['id' => $competition->id, 'placing' => '1st', 'notes' => 'Completed show']);
        $this->getJson('/api/competitions?period=past')->assertJsonCount(0, 'data');
        $this->putJson($url, $this->competition('2026-10-25'))->assertOk()
            ->assertJsonPath('data.period', 'past')->assertJsonPath('data.placing', '1st')->assertJsonPath('data.notes', 'Completed show');
    }

    /** @return array{User, User} */
    private function pair(string $name = 'Client'): array
    {
        $coach = User::factory()->create(['role' => 'coach']);
        $client = User::factory()->create(['name' => $name]);
        $coach->clients()->attach($client, ['start_date' => '2026-10-01', 'status' => 'active']);

        return [$coach, $client];
    }

    /** @return array{competition_name: string, competition_date: string} */
    private function competition(string $date = '2026-12-05', string $name = 'Winter show'): array
    {
        return ['competition_name' => $name, 'competition_date' => $date];
    }
}
