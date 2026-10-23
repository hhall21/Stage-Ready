<?php

namespace Tests\Feature;

use App\Models\Exercise;
use App\Models\TrainerClient;
use App\Models\User;
use Database\Seeders\DevelopmentGraphHistorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class DevelopmentGraphHistorySeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 23)->setTime(12, 0));
    }

    public function test_every_client_gets_past_performance_and_bodyweight_points_without_rewriting_existing_records(): void
    {
        $coach = User::factory()->create(['role' => 'coach']);
        $bench = Exercise::create(['exercise_name' => 'Bench press']);
        $pushup = Exercise::create(['exercise_name' => 'Push-up']);
        foreach (range(1, 2) as $number) {
            $client = User::factory()->create();
            TrainerClient::create(['trainer_id' => $coach->id, 'client_id' => $client->id, 'start_date' => '2026-09-01']);
            $client->dailyCheckIns()->create(['check_in_date' => '2026-10-22', 'bodyweight' => $number === 1 ? 82 : null, 'notes' => 'Keep this check-in']);
            $workout = $client->workouts()->create(['trainer_id' => $coach->id, 'workout_name' => 'Keep this session', 'workout_date' => '2026-10-22']);
            foreach ([$bench, $pushup] as $index => $exercise) {
                $entry = $workout->workoutExercises()->create(['exercise_id' => $exercise->id, 'exercise_order' => $index + 1]);
                $entry->sets()->create(['set_number' => 1, 'reps' => null, 'weight' => null, 'completed' => false]);
            }
        }
        $original = $this->records();
        $this->seed(DevelopmentGraphHistorySeeder::class);
        foreach ($original as $table => $rows) {
            foreach ($rows as $row) {
                $this->assertEquals($row, DB::table($table)->where('id', $row->id)->first());
            }
        }
        foreach (User::where('role', 'client')->get() as $client) {
            $this->assertSame(12, $client->workouts()->where('completed', true)->count());
            $this->assertSame(0, $client->workouts()->where('completed', true)->where('workout_date', '>=', today()->toDateString())->count());
            $this->actingAs($coach);
            $response = $this->getJson('/api/clients/'.$client->id.'/exercise-progress?exercise_id='.$bench->id)->assertOk()->assertJsonCount(12, 'data');
            $points = collect($response->json('data'));
            $this->assertSame(12, $points->pluck('date')->unique()->count());
            $this->assertGreaterThan(1, $points->pluck('max_weight_kg')->unique()->count());
            $this->assertGreaterThan(1, $points->pluck('max_reps')->unique()->count());
            $this->assertSame(12, $points->whereNotNull('average_bodyweight_kg')->count());
            $this->assertSame(12, $points->whereNotNull('logged_calories_kcal')->count());
            $bodyweightSets = $this->getJson('/api/clients/'.$client->id.'/exercise-progress?exercise_id='.$pushup->id)->assertOk()->json('data');
            foreach ($bodyweightSets as $point) {
                $this->assertNull($point['max_weight_kg']);
                $this->assertSame(3, $point['working_sets']);
            }
            $trend = $this->getJson('/api/clients/'.$client->id.'/bodyweight-trend')->assertOk()->json('data');
            $this->assertGreaterThanOrEqual(40, collect($trend)->whereNotNull('average_kg')->count());
            $expectedReadings = $client->dailyCheckIns()->whereNotNull('bodyweight')->where('check_in_date', '>=', '2026-10-17')->count();
            $this->assertSame($expectedReadings, $trend[array_key_last($trend)]['readings']);
        }
        $this->assertDatabaseCount('nutrition_plans', 0);
        $this->assertDatabaseCount('training_splits', 0);
    }

    public function test_repeating_later_preserves_edits_missing_readings_and_partial_food_logs(): void
    {
        $client = User::factory()->create();
        $blank = $client->dailyCheckIns()->create(['check_in_date' => '2026-10-22', 'bodyweight' => null]);
        $mealId = DB::table('meals')->insertGetId(['client_id' => $client->id, 'meal_date' => '2026-10-15', 'meal_type' => 'lunch']);
        DB::table('meal_items')->insert(['meal_id' => $mealId, 'food_name' => 'Keep this partial log', 'quantity' => 1, 'unit' => 'serving', 'calories' => null]);
        $this->seed(DevelopmentGraphHistorySeeder::class);
        DB::table('workouts')->orderBy('id')->limit(1)->update(['workout_name' => 'Edited session name']);
        DB::table('exercise_sets')->orderBy('id')->limit(1)->update(['weight' => 123.5, 'reps' => 11, 'notes' => 'Edited after seeding']);
        $before = $this->records();
        $this->travel(10)->days();
        $this->seed(DevelopmentGraphHistorySeeder::class);
        $this->assertEquals($before, $this->records());
        $this->assertNull($blank->fresh()->bodyweight);
        $this->assertSame(1, DB::table('meals')->where('client_id', $client->id)->where('meal_date', '2026-10-15')->count());
        $this->assertDatabaseHas('meal_items', ['food_name' => 'Keep this partial log', 'calories' => null]);
    }

    public function test_more_than_ten_exercises_are_split_into_safe_workout_entries(): void
    {
        $client = User::factory()->create();
        foreach (range(1, 2) as $group) {
            $workout = $client->workouts()->create(['workout_name' => 'Existing group '.$group, 'workout_date' => '2026-10-22']);
            foreach (range(1, $group === 1 ? 10 : 1) as $number) {
                $exercise = Exercise::create(['exercise_name' => 'Exercise '.$group.'-'.$number]);
                $workout->workoutExercises()->create(['exercise_id' => $exercise->id, 'exercise_order' => $number]);
            }
        }
        $this->seed(DevelopmentGraphHistorySeeder::class);
        $this->assertSame(24, $client->workouts()->where('completed', true)->count());
        $this->assertSame(0, DB::table('workout_exercises')->select('workout_id')->groupBy('workout_id')->havingRaw('COUNT(*) > 10')->get()->count());
        $this->assertDatabaseCount('exercise_sets', 396);
    }

    public function test_graph_history_is_refused_in_production(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('local and testing');
        app(DevelopmentGraphHistorySeeder::class)->run();
    }

    /** @return array<string, list<object>> */
    private function records(): array
    {
        $records = [];
        foreach (['users', 'daily_check_ins', 'workouts', 'workout_exercises', 'exercise_sets', 'meals', 'meal_items'] as $table) {
            $records[$table] = DB::table($table)->orderBy('id')->get()->all();
        }

        return $records;
    }
}
