<?php

namespace App\Services;

use App\Jobs\DeleteCheckInPhotos;
use App\Jobs\DeleteTrainingClipFiles;
use App\Models\CoachingHandover;
use App\Models\CoachMembership;
use App\Models\TrainerClient;
use App\Models\User;
use App\Support\DateBounds;
use Illuminate\Support\Facades\DB;

class ExpiredCoachData
{
    public function __construct(private CoachBilling $billing, private CoachingHandoverBuilder $builder, private CoachingHandoverPdf $pdf, private TemporaryPosePhotos $temporary) {}

    public function process(CoachMembership $candidate): bool
    {
        // Confirm Stripe's current records before making an irreversible local deletion.
        $this->billing->reconcile($candidate);

        return DB::transaction(function () use ($candidate): bool {
            $coach = User::whereKey($candidate->user_id)->lockForUpdate()->firstOrFail();
            $membership = CoachMembership::whereKey($candidate->id)->lockForUpdate()->firstOrFail();
            if ($coach->role !== 'coach' || $coach->is_admin || $coach->billing_exempt || $membership->data_purged_at !== null
                || $membership->paid_through === null || $membership->retention_expires_at === null
                || $membership->retention_expires_at->isFuture() || $coach->hasPaidCoachAccess()) {
                return false;
            }
            $assignments = $coach->trainerAssignments()->where('status', 'active')
                ->where('start_date', '<', DateBounds::after($membership->paid_through))->orderBy('client_id')->lockForUpdate()->get();
            foreach ($assignments as $assignment) {
                $client = User::whereKey($assignment->client_id)->lockForUpdate()->firstOrFail();
                if ($client->role !== 'client') {
                    throw new \RuntimeException('A coaching assignment does not point to a client account.');
                }
                $assignment->forceFill(['status' => 'inactive', 'end_date' => $membership->paid_through->toDateString(), 'health_monitoring_enabled_at' => null])->save();
                $snapshot = $this->builder->build($coach, $client, $assignment, [
                    'next_steps' => 'Coaching access ended after the coach membership remained unpaid for seven days. Save this handover for your own records.',
                ]);
                CoachingHandover::firstOrCreate(['client_id' => $client->id, 'coach_id' => $coach->id,
                    'billing_period_ended_at' => $membership->paid_through], [
                        'started_on' => $assignment->start_date, 'ended_at' => $membership->paid_through, 'snapshot' => $snapshot,
                        'pdf_base64' => base64_encode($this->pdf->render($snapshot)), 'delete_after_view' => true,
                    ]);
                // Other ongoing coaching relationships may still need this client's shared records.
                $otherCoach = TrainerClient::where('client_id', $client->id)->where('trainer_id', '!=', $coach->id)
                    ->where('status', 'active')->whereHas('trainer', fn ($query) => $query->where('role', 'coach')->where('is_active', true))->exists();
                if (! $otherCoach) {
                    $this->purgeClientRecords($client);
                }
            }
            $membership->update(['data_purged_at' => now()]);

            return true;
        });
    }

    private function purgeClientRecords(User $client): void
    {
        $checkInIds = [];
        foreach ($client->dailyCheckIns()->select('id')->get() as $checkIn) {
            $checkInIds[] = $checkIn->id;
        }
        $photos = DB::table('progress_photos')->whereIn('check_in_id', $client->dailyCheckIns()->select('id'))
            ->pluck('file_path')->unique()->values()->all();
        $clips = DB::table('training_clips')->where('client_id', $client->id)->pluck('file_path')->unique()->values()->all();
        foreach (['training_clips', 'workouts', 'training_splits', 'mesocycles', 'daily_check_ins', 'meals',
            'weekly_health_readings', 'nutrition_plans', 'practice_plans', 'coaching_reviews', 'supplement_logs', 'phases', 'competitions'] as $table) {
            DB::table($table)->where('client_id', $client->id)->delete();
        }
        $client->forceFill(['date_of_birth' => null, 'phone' => null])->save();
        DeleteCheckInPhotos::dispatch($this->mediaPaths($photos))->afterCommit();
        DeleteTrainingClipFiles::dispatch($this->mediaPaths($clips))->afterCommit();
        $this->temporary->afterCheckInDeletion($client->id, $checkInIds);
    }

    /** @param array<int, mixed> $values
     * @return list<string>
     */
    private function mediaPaths(array $values): array
    {
        $paths = [];
        foreach ($values as $value) {
            if (! is_string($value)) {
                throw new \RuntimeException('A stored media path is invalid.');
            }
            $paths[] = $value;
        }

        return $paths;
    }
}
