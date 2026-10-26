<?php

namespace Database\Seeders;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DevelopmentCompetitionSeeder extends Seeder
{
    private const PREFIX = 'Stage-Ready Sample / ';

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Sample competitions can only be added in local and testing environments.');
        }

        $client = User::where('role', 'client')->where('email', 'adam@example.com')->first()
            ?? User::where('role', 'client')->where('email', 'sample.client01@stage-ready.test')->first();
        if ($client === null) {
            throw new RuntimeException('Load the demo accounts or development samples before adding sample competitions.');
        }

        DB::transaction(function () use ($client): void {
            User::whereKey($client->id)->lockForUpdate()->firstOrFail();
            $firstRun = $client->competitions()->where('competition_name', 'like', self::PREFIX.'%')->min('created_at');
            $anchor = CarbonImmutable::parse($firstRun ?? today())->startOfDay();
            $shows = [
                [-90, 'Regional Qualifier', '3rd', 'Fictional result: practise holding the rear poses.'],
                [-45, 'Open Classic', '2nd', 'Fictional result: improved presentation and transitions.'],
                [-14, 'Season Final', null, null],
                [21, 'City Championships', null, null],
                [56, 'Coastal Classic', null, null],
                [112, 'National Qualifier', null, null],
            ];

            foreach ($shows as [$offset, $name, $placing, $notes]) {
                $client->competitions()->firstOrCreate(['competition_name' => self::PREFIX.$name], [
                    'competition_date' => $anchor->addDays($offset)->toDateString(),
                    'location' => 'Fictional sample venue, UK',
                    'category' => 'Classic physique',
                    'placing' => $placing,
                    'notes' => $notes,
                ]);
            }
        });

        $this->command->info('Six sample shows are available for '.$client->name.'. Existing records and edits are preserved.');
    }
}
