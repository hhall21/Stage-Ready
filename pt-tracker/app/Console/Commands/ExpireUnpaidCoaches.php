<?php

namespace App\Console\Commands;

use App\Models\CoachMembership;
use App\Services\ExpiredCoachData;
use Illuminate\Console\Command;
use Throwable;

class ExpireUnpaidCoaches extends Command
{
    protected $signature = 'coaching:expire-unpaid {--dry-run : Count overdue memberships without changing data}';

    protected $description = 'Create client handovers and remove eligible coaching data seven days after paid access expires';

    public function handle(ExpiredCoachData $cleanup): int
    {
        $query = CoachMembership::whereNotNull('last_paid_at')->whereNotNull('paid_through')
            ->where('retention_expires_at', '<=', now())->whereNull('data_purged_at')
            ->whereHas('coach', fn ($query) => $query->where('role', 'coach')->where('is_admin', false)->where('billing_exempt', false));
        if ($this->option('dry-run')) {
            $this->info($query->count().' overdue membership(s). No records changed.');

            return self::SUCCESS;
        }
        $processed = 0;
        $failed = 0;
        $query->chunkById(25, function ($memberships) use ($cleanup, &$processed, &$failed): void {
            foreach ($memberships as $membership) {
                try {
                    $processed += (int) $cleanup->process($membership);
                } catch (Throwable $error) {
                    report($error);
                    $failed++;
                    $this->warn('Cleanup deferred for membership '.$membership->id.'. Its transaction was not completed.');
                }
            }
        });
        $this->info('Processed '.$processed.' overdue membership(s); deferred '.$failed.'.');

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
