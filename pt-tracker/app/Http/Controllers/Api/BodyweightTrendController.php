<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class BodyweightTrendController extends Controller
{
    public function __invoke(User $client): JsonResponse
    {
        Gate::authorize('viewTracker', $client);
        $end = CarbonImmutable::today();
        $start = $end->subDays(55);
        $weights = $client->dailyCheckIns()->whereNotNull('bodyweight')
            ->whereDate('check_in_date', '>=', $start->subDays(6)->toDateString())
            ->whereDate('check_in_date', '<=', $end->toDateString())
            ->get()->map(fn ($record) => [
                'date' => CarbonImmutable::parse($record->getAttribute('check_in_date'))->toDateString(),
                'weight' => (float) $record->getAttribute('bodyweight'),
            ]);
        $points = [];
        for ($date = $start; $date->lte($end); $date = $date->addDay()) {
            $from = $date->subDays(6)->toDateString();
            $to = $date->toDateString();
            $window = $weights->filter(fn ($entry) => $entry['date'] >= $from && $entry['date'] <= $to);
            $average = $window->isEmpty() ? null : round((float) $window->avg('weight'), 2);
            $prior = $points[count($points) - 7]['average_kg'] ?? null;
            $points[] = ['date' => $to, 'average_kg' => $average, 'readings' => $window->count(),
                'change_from_7_days_ago_kg' => $average !== null && $prior !== null ? round($average - $prior, 2) : null];
        }

        return response()->json(['window_days' => 7, 'data' => $points]);
    }
}
