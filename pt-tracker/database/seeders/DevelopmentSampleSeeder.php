<?php

namespace Database\Seeders;

use App\Services\CoachingHandoverPdf;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class DevelopmentSampleSeeder extends Seeder
{
    public const TABLES = [
        'users', 'trainer_clients', 'client_invitations', 'contact_enquiries',
        'daily_check_ins', 'progress_photos', 'check_in_posing_reviews',
        'weekly_health_readings', 'nutrition_plans', 'practice_plans', 'coaching_reviews',
        'meals', 'meal_items', 'exercises', 'training_splits', 'split_day_selections',
        'mesocycles', 'workouts', 'workout_exercises', 'exercise_sets',
        'training_clips', 'clip_feedback', 'supplements', 'supplement_logs',
        'phases', 'cycle_entries', 'competitions', 'coaching_handovers',
    ];

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Development samples can only be added in local and testing environments.');
        }

        DB::transaction(function (): void {
            $password = Hash::make('password');
            $assignedCoach = DB::table('trainer_clients')->join('users', 'users.id', '=', 'trainer_clients.client_id')
                ->where('users.email', 'sample.client01@stage-ready.test')->value('trainer_clients.trainer_id');
            if ($assignedCoach !== null) {
                $coachId = (int) $assignedCoach;
            } else {
                $coach = DB::table('users')->where('email', 'james@pttracker.test')->where('role', 'coach')->first();
                $coachId = $coach !== null && Hash::check('password', $coach->password)
                    ? (int) $coach->id
                    : $this->ensure('users', ['email' => 'sample.coach@stage-ready.test'], [
                        'name' => 'Sample Coach', 'role' => 'coach', 'password' => $password,
                        'email_verified_at' => now(), 'is_active' => true,
                    ]);
            }

            $catalogue = [
                ['Push-up', 'Chest'], ['Machine chest press', 'Chest'],
                ['Chest-supported row', 'Back'], ['Neutral-grip pulldown', 'Back'],
                ['Hack squat', 'Quadriceps'], ['Seated leg curl', 'Hamstrings'],
                ['Cable lateral raise', 'Shoulders'], ['Cable triceps extension', 'Triceps'],
                ['Incline dumbbell curl', 'Biceps'], ['Standing calf raise', 'Calves'],
            ];
            $exercises = [];
            foreach ($catalogue as [$name, $group]) {
                $exercises[] = $this->ensure('exercises', ['exercise_name' => $name.' (sample)'], [
                    'muscle_group' => $group, 'description' => 'Fictional Stage-Ready training example.',
                ]);
            }
            $supplements = [];
            foreach (['Whey isolate', 'Creatine monohydrate', 'Electrolyte mix', 'Vitamin D', 'Omega-3', 'Multivitamin', 'Magnesium', 'Casein', 'Caffeine-free pre-workout', 'Fibre blend'] as $name) {
                $supplements[] = $this->ensure('supplements', ['supplement_name' => $name.' (sample)'], [
                    'description' => 'Sample catalogue entry; not a supplement recommendation.',
                ]);
            }
            foreach (range(0, 9) as $index) {
                $this->scenario($index, $coachId, $password, $exercises, $supplements);
            }
        });

    }

    /** @param list<int> $exercises
     * @param  list<int>  $supplements
     */
    private function scenario(int $index, int $coachId, string $password, array $exercises, array $supplements): void
    {
        $number = str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT);
        $name = ['Alex Morgan', 'Sam Taylor', 'Jamie Parker', 'Casey Reed', 'Jordan Ellis', 'Riley Brooks', 'Charlie Lane', 'Drew Carter', 'Cameron Blake', 'Robin Hayes'][$index].' (sample)';
        $email = 'sample.client'.$number.'@stage-ready.test';
        $clientId = $this->ensure('users', ['email' => $email], [
            'name' => $name, 'role' => 'client', 'password' => $password,
            'email_verified_at' => now(), 'is_active' => true,
            'date_of_birth' => '199'.($index % 10).'-04-12', 'phone' => null,
        ]);
        // Anchor dates to the first run, so later runs do not duplicate or rewrite history.
        $anchor = CarbonImmutable::parse(DB::table('users')->where('id', $clientId)->value('created_at'))->startOfDay();
        $this->handover($clientId, $coachId, $anchor);
        $yesterday = $anchor->subDay();
        $reviewPhase = $index % 2 === 0 ? 'prep' : 'offseason';
        $this->ensure('trainer_clients', ['trainer_id' => $coachId, 'client_id' => $clientId], [
            'start_date' => $anchor->subDays(42)->toDateString(), 'status' => 'active',
            'health_monitoring_enabled_at' => $anchor->subDays(14),
            'review_phase' => $reviewPhase, 'review_started_on' => $anchor->subDays(14)->toDateString(),
        ]);
        $this->ensure('client_invitations', ['trainer_id' => $coachId, 'email' => $email], [
            'name' => $name, 'token_hash' => hash('sha256', 'Stage-Ready sample invitation '.$number),
            'expires_at' => $anchor->subDays(35), 'accepted_at' => $anchor->subDays(42),
        ]);
        $this->ensure('contact_enquiries', ['email' => 'sample.enquiry'.$number.'@stage-ready.test'], [
            'name' => $name, 'service' => $reviewPhase === 'prep' ? 'Competition preparation' : 'Off-season coaching',
            'message' => 'Fictional enquiry '.$number.': I would like structured coaching and regular progress reviews.',
            'status' => ['new', 'contacted', 'closed'][$index % 3], 'privacy_consent_at' => $anchor->subDays(45),
        ]);

        $checkInId = 0;
        foreach (range(0, $index === 0 ? 9 : 0) as $offset) {
            $date = $yesterday->subDays($offset);
            $id = $this->ensure('daily_check_ins', ['client_id' => $clientId, 'check_in_date' => $date->toDateString()], [
                'bodyweight' => $index === 3 ? null : 78 + $index * 1.4 + $offset * 0.12,
                'sleep_hours' => $index === 4 ? null : 7 + ($index % 3) * 0.25,
                'sleep_quality' => $index % 4 === 0 ? null : 4,
                'energy_level' => 3 + $index % 3, 'stress_level' => 1 + $index % 3,
                'steps' => 7800 + $index * 350, 'water_litres' => $index === 6 ? null : 3.1,
                'notes' => $index % 3 === 0 ? null : 'Sample check-in: consistent training and recovery this week.',
                'submitted_at' => $offset === 0 ? $date->setTime(19, 0) : null,
                'photo_version' => $offset === 0 ? 1 : 0,
                'hunger_level' => $index % 3 === 0 ? null : 2 + $index % 3,
                'appetite_level' => $index % 4 === 0 ? null : 3,
                'digestive_discomfort' => $index % 3 === 0 ? null : 1 + $index % 2,
                'digestion_notes' => $index % 2 === 0 ? null : 'Sample response: digestion comfortable today.',
                'dietary_difficulties' => $index === 5 ? 'Sample response: meal timing was difficult around work.' : null,
                'nutrition_completed_at' => $index % 3 === 0 ? null : $date->setTime(21, 0),
            ]);
            if ($offset === 0) {
                $checkInId = $id;
            }
        }
        $pose = $index === 9 ? 'favourite_classic_pose' : 'front_relaxed';
        $photoPath = 'samples/'.$clientId.'/'.$checkInId.'/sample-pose.png';
        $this->media('checkin_photos', $photoPath, 'sample-pose.png');
        $this->ensure('progress_photos', ['check_in_id' => $checkInId, 'pose_key' => $pose], [
            'photo_type' => $index === 9 ? 'other' : 'front', 'file_path' => $photoPath, 'mime_type' => 'image/png',
        ]);
        $this->ensure('check_in_posing_reviews', ['check_in_id' => $checkInId, 'coach_id' => $coachId], [
            'photo_version' => 1, 'posing_rating' => 6 + $index % 4,
            'feedback' => 'Sample review: maintain a tall posture and consistent transitions between poses.',
            'is_competition_prep' => $reviewPhase === 'prep',
            'flatness_rating' => $reviewPhase === 'prep' ? 2 + $index % 3 : null,
            'fullness_rating' => $reviewPhase === 'prep' ? 7 + $index % 3 : null,
            'reviewed_at' => $yesterday->setTime(20, 0),
        ]);
        $this->ensure('weekly_health_readings', ['client_id' => $clientId, 'reading_date' => $yesterday->toDateString()], [
            'systolic' => $index % 3 === 0 ? null : 116 + $index,
            'diastolic' => $index % 3 === 0 ? null : 72 + $index,
            'resting_heart_rate' => $index === 4 ? null : 58 + $index,
            'notes' => 'Fictional health reading for interface testing.',
        ]);
        $this->plans($clientId, $coachId, $anchor, $reviewPhase);

        $templateExercises = [
            ['exercise_id' => $exercises[$index], 'notes' => 'Controlled tempo.', 'sets' => [
                ['reps' => 10, 'weight' => $index === 0 ? null : 30 + $index * 2.5, 'rir' => 2],
                ['reps' => 10, 'weight' => $index === 0 ? null : 30 + $index * 2.5, 'rir' => 1],
            ]],
            ['exercise_id' => $exercises[($index + 1) % 10], 'notes' => null, 'sets' => [
                ['reps' => 12, 'weight' => null, 'rir' => 2],
            ]],
        ];
        $splitId = $this->ensure('training_splits', ['client_id' => $clientId, 'name' => 'Five-day split (sample)'], [
            'trainer_id' => $coachId, 'start_date' => $anchor->subDays(14)->toDateString(),
            'notes' => 'Fictional prescribed split with training and rest days.',
            'days' => $this->json([
                ['name' => 'Session A', 'is_rest_day' => false, 'notes' => null, 'exercises' => $templateExercises],
                ['name' => 'Session B', 'is_rest_day' => false, 'notes' => null, 'exercises' => array_reverse($templateExercises)],
                ['name' => 'Rest', 'is_rest_day' => true, 'notes' => 'Recovery day.', 'exercises' => []],
                ['name' => 'Session C', 'is_rest_day' => false, 'notes' => null, 'exercises' => $templateExercises],
                ['name' => 'Rest', 'is_rest_day' => true, 'notes' => null, 'exercises' => []],
            ]),
        ]);
        $this->ensure('split_day_selections', ['training_split_id' => $splitId, 'calendar_date' => $yesterday->toDateString()], [
            'day_number' => 1,
        ]);
        $this->ensure('mesocycles', ['client_id' => $clientId, 'name' => 'Earlier weekly program (sample)'], [
            'trainer_id' => $coachId, 'start_date' => $anchor->subDays(42)->toDateString(),
            'end_date' => $anchor->subDays(15)->toDateString(), 'notes' => 'Legacy program example; replaced by the repeating split.',
            'templates' => $this->json([['weekday' => 1, 'name' => 'Earlier session', 'notes' => null, 'exercises' => $templateExercises]]),
        ]);
        $workoutId = $this->ensure('workouts', ['training_split_id' => $splitId, 'workout_date' => $yesterday->toDateString()], [
            'client_id' => $clientId, 'trainer_id' => $coachId, 'workout_name' => 'Session A',
            'split_day_number' => 1, 'completed' => $index % 4 !== 0,
            'logged_at' => $yesterday->setTime(18, 0), 'notes' => 'Sample logged session. Volume is measured in working sets.',
        ]);
        $firstSet = 0;
        foreach ($templateExercises as $order => $exercise) {
            $entryId = $this->ensure('workout_exercises', ['workout_id' => $workoutId, 'exercise_order' => $order + 1], [
                'exercise_id' => $exercise['exercise_id'], 'notes' => $exercise['notes'],
            ]);
            foreach ($exercise['sets'] as $setIndex => $set) {
                $setId = $this->ensure('exercise_sets', ['workout_exercise_id' => $entryId, 'set_number' => $setIndex + 1], [
                    ...$set, 'completed' => true, 'notes' => 'Sample completed working set.',
                ]);
                if ($firstSet === 0) {
                    $firstSet = $setId;
                }
            }
        }
        $clipPath = 'sample-training/'.$clientId.'/sample-training.avi';
        $this->media('local', $clipPath, 'sample-training.avi');
        $clipId = $this->ensure('training_clips', ['client_id' => $clientId, 'file_path' => $clipPath], [
            'exercise_set_id' => $firstSet, 'uploaded_at' => $yesterday->setTime(18, 15),
            'client_notes' => 'Labelled placeholder video; no client training footage.',
        ]);
        $this->ensure('clip_feedback', ['training_clip_id' => $clipId, 'trainer_id' => $coachId], [
            'feedback' => 'Fictional technique feedback: keep repetitions controlled.',
            'video_timestamp_seconds' => 1, 'review_status' => 'reviewed', 'feedback_date' => $yesterday->setTime(20, 0),
        ]);

        $mealId = $this->ensure('meals', ['client_id' => $clientId, 'meal_date' => $yesterday->toDateString(), 'meal_type' => 'breakfast'], [
            'notes' => 'Sample meal log.',
        ]);
        foreach ([['Oats', 60, 'g', 230, 8, 39, 4], ['Greek yoghurt', 200, 'g', 140, 20, 10, 2]] as [$food, $quantity, $unit, $calories, $protein, $carbs, $fat]) {
            $this->ensure('meal_items', ['meal_id' => $mealId, 'food_name' => $food], [
                'quantity' => $quantity, 'unit' => $unit, 'calories' => $calories,
                'protein' => $protein, 'carbohydrates' => $carbs, 'fat' => $fat,
            ]);
        }
        $this->ensure('supplement_logs', ['client_id' => $clientId, 'supplement_id' => $supplements[$index], 'taken_at' => $yesterday->setTime(8, 0)->toDateTimeString()], [
            'amount' => 1, 'unit' => 'serving', 'notes' => 'Fictional log; not a dosage recommendation.',
        ]);
        $phaseId = $this->ensure('phases', ['client_id' => $clientId, 'phase_name' => 'Training phase (sample)'], [
            'start_date' => $anchor->subDays(42)->toDateString(), 'end_date' => $anchor->addDays(42)->toDateString(),
            'notes' => 'Fictional training-phase record.',
        ]);
        $this->ensure('cycle_entries', ['cycle_id' => $phaseId, 'substance' => 'Sample creatine entry'], [
            'taken_at' => $yesterday->setTime(8, 0), 'amount' => 1, 'unit' => 'serving', 'route' => 'oral',
            'notes' => 'Fictional record for relationship testing; not a prescription.',
        ]);
        $this->ensure('competitions', ['client_id' => $clientId, 'competition_name' => 'Stage-Ready Sample Show '.$number], [
            'competition_date' => $anchor->addDays(84)->toDateString(), 'location' => 'Sample venue, UK',
            'category' => $index % 2 === 0 ? 'Classic physique' : 'Bodybuilding',
            'target_weight' => $index % 3 === 0 ? null : 75 + $index * 1.4, 'placing' => null,
            'notes' => 'Fictional competition; not a real scheduled event.',
        ]);
    }

    private function handover(int $clientId, int $coachId, CarbonImmutable $anchor): void
    {
        $start = $anchor->subDays(84)->toDateString();
        $key = ['client_id' => $clientId, 'coach_id' => $coachId, 'started_on' => $start];
        if (DB::table('coaching_handovers')->where($key)->exists()) {
            return;
        }
        // This earlier fictional period ends before the current sample assignment begins.
        $end = $anchor->subDays(43);
        $coach = DB::table('users')->where('id', $coachId)->firstOrFail();
        $snapshot = [
            'client_name' => (string) DB::table('users')->where('id', $clientId)->value('name'),
            'coach_name' => (string) $coach->name,
            'discipline' => $coach->is_bodybuilding_coach ? 'Bodybuilding' : 'Powerlifting',
            'start_date' => $start, 'end_date' => $end->toDateString(),
            'weights' => [], 'weight_trend' => [], 'completed_sessions' => 0, 'working_sets' => 0,
            'personal_bests' => [], 'feedback' => 'Fictional handover from an earlier coaching period. No performance readings were recorded in that period.',
            'next_steps' => 'Keep recording your sessions and check-ins during the next coaching period.', 'notes' => [],
        ];
        $this->ensure('coaching_handovers', $key, [
            'ended_at' => $end->setTime(18, 0), 'snapshot' => $this->json($snapshot),
            'pdf_base64' => base64_encode(app(CoachingHandoverPdf::class)->render($snapshot)),
        ]);
    }

    private function plans(int $clientId, int $coachId, CarbonImmutable $anchor, string $phase): void
    {
        $training = [];
        $rest = [];
        foreach (['Oats, Greek yoghurt and berries', 'Chicken, rice and vegetables', 'White fish, potatoes and greens', 'Eggs, toast and fruit'] as $index => $foods) {
            $meal = ['name' => 'Meal '.($index + 1), 'foods' => $foods, 'calories' => 550,
                'protein' => 40, 'carbohydrates' => 65, 'fat' => 14];
            $hour = 8 + $index * 4;
            $training[] = [...$meal, 'time' => sprintf('%02d:00', $hour)];
            $rest[] = [...$meal, 'time' => sprintf('%02d:00', $hour + 1), 'calories' => 480, 'carbohydrates' => 48];
        }
        $effective = $anchor->subDays(7)->toDateString();
        $this->ensure('nutrition_plans', ['client_id' => $clientId, 'effective_from' => $effective], [
            'coach_id' => $coachId, 'training_meals' => $this->json($training), 'rest_meals' => $this->json($rest),
            'notes' => 'Sample plan with separate day types and rest meals one hour later; not dietary advice.',
        ]);
        $days = [];
        foreach (range(1, 7) as $weekday) {
            $days[] = ['weekday' => $weekday, 'cardio_mode' => 'Walking', 'cardio_minutes' => 20,
                'cardio_intensity' => 'Easy conversational pace', 'posing_rounds' => $phase === 'prep' ? 4 : 2,
                'pose_hold_seconds' => 10, 'vacuum_sets' => 3, 'vacuum_hold_seconds' => 15, 'notes' => null];
        }
        $this->ensure('practice_plans', ['client_id' => $clientId, 'effective_from' => $effective], [
            'coach_id' => $coachId, 'show_date' => $phase === 'prep' ? $anchor->addDays(84)->toDateString() : null,
            'days' => $this->json($days), 'notes' => 'Fictional daily practice prescription.',
        ]);
        $this->ensure('coaching_reviews', ['client_id' => $clientId, 'coach_id' => $coachId, 'review_date' => $anchor->subDay()->toDateString()], [
            'phase' => $phase, 'feedback' => 'Sample review: consistency has improved; recovery and technique remain the focus.',
            'next_steps' => 'Continue the prescribed split and share any difficulties at the next check-in.',
        ]);
    }

    /** @param array<string, mixed> $key
     * @param  array<string, mixed>  $values
     */
    private function ensure(string $table, array $key, array $values): int
    {
        $id = DB::table($table)->where($key)->value('id');
        if ($id !== null) {
            return (int) $id;
        }

        return DB::table($table)->insertGetId([...$key, ...$values, 'created_at' => now(), 'updated_at' => now()]);
    }

    /** @param array<array-key, mixed> $value */
    private function json(array $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR);
    }

    private function media(string $disk, string $path, string $asset): void
    {
        if (! Storage::disk($disk)->exists($path) && ! Storage::disk($disk)->put($path, File::get(__DIR__.'/assets/'.$asset))) {
            throw new RuntimeException('Cannot create private sample media.');
        }
    }
}
