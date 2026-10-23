<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveWeeklyHealthReadingRequest;
use App\Models\TrainerClient;
use App\Models\User;
use App\Models\WeeklyHealthReading;
use App\Support\DateBounds;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class WeeklyHealthController extends Controller
{
    public function index(Request $request, User $client): JsonResponse
    {
        Gate::authorize('viewTracker', $client);
        $assignments = $this->activeAssignments($client)->get();
        $enabledAt = $assignments->pluck('health_monitoring_enabled_at')->filter()->sort()->first();
        $latest = WeeklyHealthReading::where('client_id', $client->id)
            ->whereNotNull('systolic')->whereNotNull('diastolic')->whereNotNull('resting_heart_rate')
            ->orderByDesc('reading_date')->first();
        $dueOn = null;
        if ($enabledAt !== null) {
            $start = Carbon::parse($enabledAt)->startOfDay();
            $lastDate = $latest ? Carbon::parse($latest->reading_date) : null;
            $dueOn = $lastDate && $lastDate->greaterThanOrEqualTo($start) ? $lastDate->addDays(7) : $start;
        }

        return response()->json([
            'monitoring_enabled' => $enabledAt !== null,
            'coach_enabled' => $request->user()?->role === 'coach'
                ? $assignments->where('trainer_id', $request->user()->id)->whereNotNull('health_monitoring_enabled_at')->isNotEmpty()
                : null,
            'due_on' => $dueOn?->toDateString(),
            'is_due' => $dueOn !== null && $dueOn->lessThanOrEqualTo(today()),
            'readings' => WeeklyHealthReading::where('client_id', $client->id)->orderByDesc('reading_date')->paginate(12),
        ]);
    }

    public function settings(Request $request, User $client): JsonResponse
    {
        abort_unless($request->user()?->role === 'coach', 403);
        Gate::authorize('viewTracker', $client);
        $data = $request->validate(['enabled' => ['required', 'boolean']]);
        DB::transaction(function () use ($request, $client, $data): void {
            User::whereKey($client->id)->lockForUpdate()->firstOrFail();
            Gate::authorize('viewTracker', $client);
            $assignment = $this->activeAssignments($client)->where('trainer_id', $request->user()->id)->firstOrFail();
            if (! $data['enabled']) {
                $assignment->forceFill(['health_monitoring_enabled_at' => null])->save();
            } elseif ($assignment->health_monitoring_enabled_at === null) {
                $assignment->forceFill(['health_monitoring_enabled_at' => now()])->save();
            }
        });

        return $this->index($request, $client);
    }

    public function store(SaveWeeklyHealthReadingRequest $request, User $client): JsonResponse
    {
        Gate::authorize('manageCheckIns', $client);
        $data = $request->validated();
        $data['reading_date'] ??= today()->toDateString();
        $record = DB::transaction(function () use ($client, $data) {
            User::whereKey($client->id)->lockForUpdate()->firstOrFail();
            Gate::authorize('manageCheckIns', $client);
            abort_unless($this->activeAssignments($client)->whereNotNull('health_monitoring_enabled_at')->exists(), 403, 'Your coach has not enabled weekly health monitoring.');
            $this->checkDuplicate($client, $data['reading_date']);
            $this->checkBloodPressure($data);

            return WeeklyHealthReading::create(['client_id' => $client->id, ...$data]);
        });

        return response()->json(['data' => $record->refresh()], 201);
    }

    public function update(SaveWeeklyHealthReadingRequest $request, User $client, int $reading): JsonResponse
    {
        Gate::authorize('manageCheckIns', $client);
        $data = $request->validated();
        $record = DB::transaction(function () use ($client, $reading, $data) {
            User::whereKey($client->id)->lockForUpdate()->firstOrFail();
            Gate::authorize('manageCheckIns', $client);
            $record = WeeklyHealthReading::where('client_id', $client->id)->findOrFail($reading);
            $data['reading_date'] ??= Carbon::parse($record->reading_date)->toDateString();
            $this->checkDuplicate($client, $data['reading_date'], $reading);
            $this->checkBloodPressure([...$record->only(['systolic', 'diastolic']), ...$data]);
            $record->update($data);

            return $record;
        });

        return response()->json(['data' => $record]);
    }

    /** @return Builder<TrainerClient> */
    private function activeAssignments(User $client): Builder
    {
        return TrainerClient::where('client_id', $client->id)->where('status', 'active')
            ->where('start_date', '<', DateBounds::after(today()->toDateString()))
            ->where(fn ($query) => $query->whereNull('end_date')->orWhereDate('end_date', '>=', today()->toDateString()))
            ->whereHas('trainer', fn ($query) => $query->where('role', 'coach')->where('is_active', true));
    }

    private function checkDuplicate(User $client, string $date, ?int $except = null): void
    {
        if (WeeklyHealthReading::where('client_id', $client->id)->where('reading_date', '>=', DateBounds::start($date))->where('reading_date', '<', DateBounds::after($date))
            ->when($except !== null, fn ($query) => $query->whereKeyNot($except))->exists()) {
            throw ValidationException::withMessages(['reading_date' => 'A health reading already exists for this date. Edit that reading instead.']);
        }
    }

    /** @param array<string, mixed> $data */
    private function checkBloodPressure(array $data): void
    {
        if (isset($data['systolic'], $data['diastolic']) && $data['systolic'] <= $data['diastolic']) {
            throw ValidationException::withMessages(['systolic' => 'Systolic pressure must be greater than diastolic pressure when both are recorded.']);
        }
    }
}
