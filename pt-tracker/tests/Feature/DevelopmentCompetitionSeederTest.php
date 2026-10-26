<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DevelopmentCompetitionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class DevelopmentCompetitionSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 26)->setTime(12, 0));
    }

    public function test_six_shows_are_added_to_one_demo_client_and_reruns_preserve_existing_records_and_edits(): void
    {
        $client = User::factory()->create(['name' => 'Adam Brown', 'email' => 'adam@example.com']);
        $other = User::factory()->create(['email' => 'sample.client01@stage-ready.test']);
        $existing = $client->competitions()->create([
            'competition_name' => 'Existing show', 'competition_date' => '2027-01-01', 'notes' => 'Keep this record',
        ]);
        $unrelated = $other->competitions()->create(['competition_name' => 'Another client show', 'competition_date' => '2027-02-01']);
        $password = $client->getRawOriginal('password');

        $this->seed(DevelopmentCompetitionSeeder::class);
        $samples = $client->competitions()->where('competition_name', 'like', 'Stage-Ready Sample / %');
        $this->assertSame(6, (clone $samples)->count());
        $this->assertSame(3, (clone $samples)->where('competition_date', '<', today()->toDateString())->count());
        $future = (clone $samples)->where('competition_date', '>=', today()->toDateString())->get();
        $this->assertCount(3, $future);
        foreach ($future as $competition) {
            $this->assertNull($competition->placing);
            $this->assertNull($competition->notes);
        }
        $edited = (clone $samples)->orderBy('competition_date')->firstOrFail();
        $edited->update(['competition_date' => '2026-01-01', 'placing' => '1st', 'notes' => 'Edited by coach']);
        $this->travel(10)->days();
        $this->seed(DevelopmentCompetitionSeeder::class);

        $this->assertDatabaseCount('competitions', 8);
        $this->assertSame('2026-01-01', $edited->fresh()->competition_date->toDateString());
        $this->assertSame('1st', $edited->fresh()->placing);
        $this->assertSame('Edited by coach', $edited->fresh()->notes);
        $this->assertSame('Keep this record', $existing->fresh()->notes);
        $this->assertSame('Another client show', $unrelated->fresh()->competition_name);
        $this->assertSame(1, $other->competitions()->count());
        $this->assertSame($password, $client->fresh()->getRawOriginal('password'));
    }

    public function test_the_first_sample_client_is_used_when_the_original_demo_client_is_absent(): void
    {
        $sample = User::factory()->create(['email' => 'sample.client01@stage-ready.test']);
        $other = User::factory()->create();
        $this->seed(DevelopmentCompetitionSeeder::class);
        $this->assertSame(6, $sample->competitions()->count());
        $this->assertSame(0, $other->competitions()->count());
        $this->assertDatabaseCount('users', 2);
    }

    public function test_it_does_not_add_shows_to_an_unrelated_account(): void
    {
        User::factory()->create();
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Load the demo accounts or development samples');
        $this->seed(DevelopmentCompetitionSeeder::class);
    }

    public function test_sample_competitions_are_refused_outside_development_environments(): void
    {
        User::factory()->create(['email' => 'adam@example.com']);
        $this->app->detectEnvironment(fn () => 'production');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('local and testing');
        app(DevelopmentCompetitionSeeder::class)->run();
    }
}
