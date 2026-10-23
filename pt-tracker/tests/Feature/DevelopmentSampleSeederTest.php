<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DevelopmentSampleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class DevelopmentSampleSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('checkin_photos');
        Mail::fake();
    }

    public function test_samples_cover_every_application_table_and_preserve_database_rules(): void
    {
        $framework = ['migrations', 'sessions', 'password_reset_tokens', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs'];
        $applicationTables = array_values(array_diff(Schema::getTableListing(schema: Schema::getCurrentSchemaName(), schemaQualified: false), $framework));
        $this->assertEqualsCanonicalizing($applicationTables, DevelopmentSampleSeeder::TABLES);
        $before = [];
        foreach ($framework as $table) {
            $before[$table] = DB::table($table)->count();
        }
        $this->seed(DevelopmentSampleSeeder::class);
        foreach (DevelopmentSampleSeeder::TABLES as $table) {
            $this->assertGreaterThanOrEqual(10, DB::table($table)->count(), $table.' needs sample records.');
        }
        foreach ($before as $table => $count) {
            $this->assertDatabaseCount($table, $count);
        }
        if (DB::getDriverName() === 'sqlite') {
            $this->assertSame([], DB::select('PRAGMA foreign_key_check'));
        } else {
            $this->assertSame(1, (int) DB::scalar('SELECT @@foreign_key_checks'));
        }
        $this->assertSame(0, DB::table('workout_exercises')->select('workout_id')->groupBy('workout_id')->havingRaw('COUNT(*) > 10')->get()->count());
        $this->assertGreaterThan(0, DB::table('exercise_sets')->whereNull('weight')->where('completed', true)->where('reps', '>', 0)->count());
        $this->assertDatabaseHas('daily_check_ins', ['bodyweight' => null]);
        $this->assertDatabaseHas('weekly_health_readings', ['systolic' => null, 'diastolic' => null]);
        foreach (DB::table('progress_photos')->get() as $photo) {
            Storage::disk('checkin_photos')->assertExists($photo->file_path);
            $this->assertStringStartsWith("\x89PNG", Storage::disk('checkin_photos')->get($photo->file_path));
        }
        foreach (DB::table('training_clips')->get() as $clip) {
            Storage::disk('local')->assertExists($clip->file_path);
            $this->assertStringStartsWith('RIFF', Storage::disk('local')->get($clip->file_path));
        }
        Mail::assertNothingSent();
    }

    public function test_repeating_the_loader_preserves_existing_records_edits_and_history(): void
    {
        $existing = User::factory()->create(['name' => 'Existing client']);
        $checkIn = $existing->dailyCheckIns()->create(['check_in_date' => today()->toDateString(), 'notes' => 'Keep this draft']);
        $original = hash('sha256', $existing->getRawOriginal('password'));
        $this->seed(DevelopmentSampleSeeder::class);
        $sample = User::where('email', 'sample.client01@stage-ready.test')->firstOrFail();
        $sample->dailyCheckIns()->firstOrFail()->update(['notes' => 'Edited after seeding', 'bodyweight' => null]);
        $counts = [];
        foreach (DevelopmentSampleSeeder::TABLES as $table) {
            $counts[$table] = DB::table($table)->count();
        }
        $this->travel(10)->days();
        $this->seed(DevelopmentSampleSeeder::class);
        foreach ($counts as $table => $count) {
            $this->assertDatabaseCount($table, $count);
        }
        $this->assertSame($original, hash('sha256', $existing->fresh()->getRawOriginal('password')));
        $this->assertSame('Keep this draft', $checkIn->fresh()->notes);
        $this->assertNull($checkIn->fresh()->bodyweight);
        $this->assertDatabaseHas('daily_check_ins', ['client_id' => $sample->id, 'notes' => 'Edited after seeding', 'bodyweight' => null]);
        $this->assertCount(10, Storage::disk('checkin_photos')->allFiles());
    }

    public function test_sample_clients_have_usable_prescriptions_and_private_photos(): void
    {
        $this->seed(DevelopmentSampleSeeder::class);
        $client = User::where('email', 'sample.client01@stage-ready.test')->firstOrFail();
        $this->assertTrue(Hash::check('password', $client->password));
        $this->actingAs($client)->getJson('/api/clients/'.$client->id.'/nutrition-plans')
            ->assertOk()->assertJsonPath('current.training_meals.0.time', '08:00')->assertJsonPath('current.rest_meals.0.time', '09:00');
        $this->getJson('/api/clients/'.$client->id.'/practice-plans')->assertOk()->assertJsonCount(7, 'current.days');
        $this->getJson('/api/clients/'.$client->id.'/bodyweight-trend')->assertOk();
        $this->getJson('/api/clients/'.$client->id.'/training-schedule')->assertOk()->assertJsonPath('data.split.name', 'Five-day split (sample)');
        $photo = DB::table('progress_photos')->whereIn('check_in_id', $client->dailyCheckIns()->select('id'))->first();
        $url = '/api/daily-check-in-photos/'.$photo->id;
        $this->get($url)->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->postJson('/api/logout')->assertOk();
        $this->actingAs(User::factory()->create())->get($url)->assertForbidden();
    }

    public function test_samples_are_refused_outside_development_environments(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('local and testing');
        app(DevelopmentSampleSeeder::class)->run();
    }
}
