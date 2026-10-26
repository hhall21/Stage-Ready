<?php

namespace App\Console\Commands;

use App\Models\CoachingHandover;
use App\Models\TrainerClient;
use App\Models\User;
use App\Services\CoachingHandoverBuilder;
use App\Services\CoachingHandoverPdf;
use App\Support\DateBounds;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillCoachingHandovers extends Command
{
    protected $signature = 'coaching:backfill-handovers';

    protected $description = 'Create missing client PDFs for coaching assignments that already ended';

    public function handle(CoachingHandoverBuilder $builder, CoachingHandoverPdf $pdf): int
    {
        $created = 0;
        TrainerClient::where('status', 'inactive')->whereNotNull('end_date')
            ->where('end_date', '<', DateBounds::after(today()))
            ->whereHas('client', fn ($query) => $query->where('role', 'client'))
            ->chunkById(50, function ($assignments) use ($builder, $pdf, &$created): void {
                foreach ($assignments as $candidate) {
                    $created += DB::transaction(function () use ($candidate, $builder, $pdf): int {
                        $coach = User::whereKey($candidate->trainer_id)->lockForUpdate()->firstOrFail();
                        $client = User::whereKey($candidate->client_id)->lockForUpdate()->firstOrFail();
                        $assignment = TrainerClient::whereKey($candidate->id)->lockForUpdate()->firstOrFail();
                        if ($client->role !== 'client' || $assignment->status !== 'inactive' || $assignment->end_date === null) {
                            return 0;
                        }
                        $start = CarbonImmutable::parse($assignment->start_date);
                        $end = CarbonImmutable::parse($assignment->end_date);
                        if ($start->gt($end) || $end->gt(CarbonImmutable::today())) {
                            return 0;
                        }
                        $exists = CoachingHandover::where('coach_id', $coach->id)->where('client_id', $client->id)
                            ->where('started_on', '>=', DateBounds::start($assignment->start_date))->where('started_on', '<', DateBounds::after($assignment->start_date))
                            ->where('ended_at', '>=', DateBounds::start($assignment->end_date))->where('ended_at', '<', DateBounds::after($assignment->end_date))->exists();
                        if ($exists) {
                            return 0;
                        }
                        $snapshot = $builder->build($coach, $client, $assignment, []);
                        CoachingHandover::create(['client_id' => $client->id, 'coach_id' => $coach->id, 'started_on' => $assignment->start_date,
                            'ended_at' => $end->endOfDay(), 'snapshot' => $snapshot, 'pdf_base64' => base64_encode($pdf->render($snapshot))]);

                        return 1;
                    });
                }
            });
        $this->info('Created '.$created.' missing coaching handover(s). Existing reports and assignments were preserved.');

        return self::SUCCESS;
    }
}
