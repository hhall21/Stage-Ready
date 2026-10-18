<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CoachingReview;
use App\Models\TrainerClient;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CoachingReviewController extends Controller
{
    public function index(Request $request, User $client): JsonResponse
    {
        Gate::authorize('viewTracker', $client);
        $assignments = $this->assignments($client)->with('trainer:id,name')->get();
        $schedules = $assignments->map(function (TrainerClient $assignment) use ($client) {
            $start = $assignment->review_started_on ?? $assignment->start_date;
            $latest = CoachingReview::where('client_id', $client->id)->where('coach_id', $assignment->trainer_id)
                ->where('phase', $assignment->review_phase)->whereDate('review_date', '>=', $start)
                ->orderByDesc('review_date')->first();
            $interval = $assignment->review_phase === 'prep' ? 7 : 14;
            $due = Carbon::parse($latest ? $latest->review_date : $start)->addDays($interval);

            return ['coach_id' => $assignment->trainer_id, 'coach_name' => $assignment->trainer?->name,
                'phase' => $assignment->review_phase, 'interval_days' => $interval,
                'due_on' => $due->toDateString(), 'is_due' => $due->lessThanOrEqualTo(today())];
        });
        $own = $assignments->firstWhere('trainer_id', $request->user()?->id);
        $days = ($own ? $own->review_phase === 'prep' : $assignments->contains('review_phase', 'prep')) ? 7 : 14;
        $from = today()->subDays($days - 1)->toDateString();
        $checks = $client->dailyCheckIns()->whereDate('check_in_date', '>=', $from)->whereDate('check_in_date', '<=', today()->toDateString())->get();
        $workouts = $client->workouts()->whereDate('workout_date', '>=', $from)->whereDate('workout_date', '<=', today()->toDateString())->get();

        return response()->json([
            'schedules' => $schedules,
            'summary' => ['from' => $from, 'to' => today()->toDateString(), 'check_ins' => $checks->count(),
                'weigh_ins' => $checks->whereNotNull('bodyweight')->count(),
                'average_bodyweight' => $checks->whereNotNull('bodyweight')->avg('bodyweight'),
                'average_steps' => $checks->whereNotNull('steps')->avg('steps'),
                'workouts_planned' => $workouts->count(), 'workouts_completed' => $workouts->where('completed', true)->count()],
            'reviews' => CoachingReview::where('client_id', $client->id)->with('coach:id,name')->orderByDesc('review_date')->orderByDesc('id')->paginate(12),
        ]);
    }

    public function schedule(Request $request, User $client): JsonResponse
    {
        Gate::authorize('manageMesocycles', $client);
        $data = $request->validate(['phase' => ['required', Rule::in(['prep', 'offseason'])]]);
        DB::transaction(function () use ($request, $client, $data): void {
            User::whereKey($client->id)->lockForUpdate()->firstOrFail();
            Gate::authorize('manageMesocycles', $client);
            $assignment = $this->assignments($client)->where('trainer_id', $request->user()->id)->firstOrFail();
            if ($assignment->review_phase !== $data['phase'] || $assignment->review_started_on === null) {
                $assignment->forceFill(['review_phase' => $data['phase'], 'review_started_on' => today()])->save();
            }
        });

        return $this->index($request, $client);
    }

    public function store(Request $request, User $client): JsonResponse
    {
        Gate::authorize('manageMesocycles', $client);
        $data = $request->validate([
            'review_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'feedback' => ['required', 'string', 'max:10000'],
            'next_steps' => ['nullable', 'string', 'max:5000'],
            'client_id' => ['prohibited'], 'coach_id' => ['prohibited'], 'phase' => ['prohibited'],
        ]);
        $record = DB::transaction(function () use ($request, $client, $data) {
            User::whereKey($client->id)->lockForUpdate()->firstOrFail();
            Gate::authorize('manageMesocycles', $client);
            $assignment = $this->assignments($client)->where('trainer_id', $request->user()->id)->firstOrFail();
            if ($data['review_date'] < Carbon::parse($assignment->review_started_on ?? $assignment->start_date)->toDateString()) {
                throw ValidationException::withMessages(['review_date' => 'Choose a date in the current coaching phase.']);
            }
            if (CoachingReview::where('client_id', $client->id)->where('coach_id', $request->user()->id)->whereDate('review_date', $data['review_date'])->exists()) {
                throw ValidationException::withMessages(['review_date' => 'You have already published a review for this date.']);
            }

            return CoachingReview::create([...$data, 'client_id' => $client->id, 'coach_id' => $request->user()->id, 'phase' => $assignment->review_phase]);
        });

        return response()->json(['data' => $record->load('coach:id,name')], 201);
    }

    /** @return Builder<TrainerClient> */
    private function assignments(User $client): Builder
    {
        return TrainerClient::where('client_id', $client->id)->where('status', 'active')
            ->whereDate('start_date', '<=', today()->toDateString())
            ->where(fn ($query) => $query->whereNull('end_date')->orWhereDate('end_date', '>=', today()->toDateString()))
            ->whereHas('trainer', fn ($query) => $query->where('role', 'coach')->where('is_active', true));
    }
}
