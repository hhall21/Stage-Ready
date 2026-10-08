<?php

namespace Tests\Feature;

use App\Models\DailyCheckIn;
use App\Models\Exercise;
use App\Models\Meal;
use App\Models\Phase;
use App\Models\Supplement;
use App\Models\SupplementLog;
use App\Models\TrainerClient;
use App\Models\TrainingClip;
use App\Models\User;
use App\Models\Workout;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrackerModelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_tracker_records_can_be_saved_and_loaded_through_their_relationships(): void
    {
        $trainer = User::factory()->create(['role' => 'coach']);
        $client = User::factory()->create(['role' => 'client']);
        $assignment = TrainerClient::create(['trainer_id' => $trainer->id, 'client_id' => $client->id, 'start_date' => '2026-10-08']);
        $this->assertTrue($trainer->clients()->first()->is($client));
        $this->assertTrue($client->trainers()->first()->is($trainer));
        $this->assertTrue($assignment->client->is($client));
        $this->assertTrue($assignment->trainer->is($trainer));

        $checkIn = $client->dailyCheckIns()->create(['check_in_date' => '2026-10-08', 'bodyweight' => '82.40']);
        $photo = $checkIn->progressPhotos()->create(['photo_type' => 'front', 'file_path' => 'photos/test.jpg']);
        $this->assertTrue($photo->checkIn->is($checkIn));
        $this->assertInstanceOf(CarbonInterface::class, $checkIn->check_in_date);
        $this->assertSame('82.40', $checkIn->bodyweight);

        $meal = $client->meals()->create(['meal_date' => '2026-10-08', 'meal_type' => 'breakfast']);
        $item = $meal->items()->create(['food_name' => 'Oats', 'protein' => '10.50']);
        $this->assertTrue($item->meal->is($meal));
        $this->assertSame('10.50', $item->protein);

        $workout = $client->workouts()->create(['trainer_id' => $trainer->id, 'workout_name' => 'Push', 'workout_date' => '2026-10-08']);
        $exercise = Exercise::create(['exercise_name' => 'Bench press']);
        $later = $workout->workoutExercises()->create(['exercise_id' => $exercise->id, 'exercise_order' => 2]);
        $first = $workout->workoutExercises()->create(['exercise_id' => $exercise->id, 'exercise_order' => 1]);
        $this->assertTrue($workout->workoutExercises()->first()->is($first));
        $this->assertTrue($first->exercise->is($exercise));
        $this->assertTrue($first->workout->is($workout));
        $this->assertTrue($workout->trainer->is($trainer));
        $this->assertFalse($workout->fresh()->completed);
        $this->assertSame(10, Workout::MAX_EXERCISES);

        $later->sets()->create(['set_number' => 2]);
        $set = $later->sets()->create(['set_number' => 1, 'weight' => '70.25', 'rir' => '2.5', 'completed' => true]);
        $this->assertTrue($later->sets()->first()->is($set));
        $this->assertTrue($set->workoutExercise->is($later));
        $this->assertSame('2.5', $set->rir);
        $this->assertTrue($set->completed);
        $clip = $set->trainingClips()->create(['client_id' => $client->id, 'file_path' => 'clips/test.mp4', 'uploaded_at' => now()]);
        $feedback = $clip->feedback()->create(['trainer_id' => $trainer->id, 'feedback' => 'Good form', 'feedback_date' => now()]);
        $this->assertTrue($feedback->trainingClip->is($clip));
        $this->assertTrue($feedback->trainer->is($trainer));
        $this->assertTrue($clip->exerciseSet->is($set));

        $supplement = Supplement::create(['supplement_name' => 'Creatine']);
        $log = $supplement->logs()->create(['client_id' => $client->id, 'taken_at' => now(), 'amount' => '5.00', 'unit' => 'g']);
        $this->assertTrue($log->supplement->is($supplement));
        $phase = $client->phases()->create(['phase_name' => 'Strength', 'start_date' => '2026-10-08']);
        $entry = $phase->cycleEntries()->create(['substance' => 'Creatine', 'taken_at' => now(), 'amount' => '5.00', 'unit' => 'g']);
        $this->assertTrue($entry->phase->is($phase));
        $competition = $client->competitions()->create(['competition_name' => 'Meet', 'competition_date' => '2026-12-05']);
        $this->assertTrue($competition->client->is($client));

        foreach ([DailyCheckIn::class, Meal::class, Workout::class, TrainingClip::class, SupplementLog::class, Phase::class] as $model) {
            $this->assertTrue($model::firstOrFail()->client->is($client));
        }
    }
}
