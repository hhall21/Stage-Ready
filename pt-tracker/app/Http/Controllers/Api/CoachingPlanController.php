<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveNutritionPlanRequest;
use App\Http\Requests\SavePracticePlanRequest;
use App\Models\NutritionPlan;
use App\Models\PracticePlan;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class CoachingPlanController extends Controller
{
    public function nutrition(Request $request, User $client): JsonResponse
    {
        return $this->plans($request, $client, NutritionPlan::class);
    }

    public function practice(Request $request, User $client): JsonResponse
    {
        return $this->plans($request, $client, PracticePlan::class);
    }

    public function storeNutrition(SaveNutritionPlanRequest $request, User $client): JsonResponse
    {
        return $this->store($request, $client, NutritionPlan::class);
    }

    public function storePractice(SavePracticePlanRequest $request, User $client): JsonResponse
    {
        return $this->store($request, $client, PracticePlan::class);
    }

    public function cancelNutrition(Request $request, User $client, int $plan): JsonResponse
    {
        return $this->cancel($request, $client, $plan, NutritionPlan::class);
    }

    public function cancelPractice(Request $request, User $client, int $plan): JsonResponse
    {
        return $this->cancel($request, $client, $plan, PracticePlan::class);
    }

    /** @param class-string<NutritionPlan>|class-string<PracticePlan> $model */
    private function plans(Request $request, User $client, string $model): JsonResponse
    {
        Gate::authorize('viewTracker', $client);
        $data = $request->validate(['date' => ['sometimes', 'date_format:Y-m-d']]);
        $date = $data['date'] ?? today()->toDateString();
        $query = $model::where('client_id', $client->id);

        return response()->json([
            'date' => $date,
            'day_type' => $client->workouts()->whereDate('workout_date', $date)->exists() ? 'training' : 'rest',
            'weekday' => Carbon::parse($date)->dayOfWeekIso,
            'current' => (clone $query)->whereNull('cancelled_at')->whereDate('effective_from', '<=', $date)->orderByDesc('effective_from')->orderByDesc('id')->first(),
            'versions' => $query->orderByDesc('effective_from')->orderByDesc('id')->paginate(12),
        ]);
    }

    /** @param class-string<NutritionPlan>|class-string<PracticePlan> $model */
    private function store(SaveNutritionPlanRequest|SavePracticePlanRequest $request, User $client, string $model): JsonResponse
    {
        Gate::authorize('manageMesocycles', $client);
        $record = DB::transaction(function () use ($request, $client, $model) {
            User::whereKey($client->id)->lockForUpdate()->firstOrFail();
            Gate::authorize('manageMesocycles', $client);
            $data = $request->validated();
            if ($request instanceof SavePracticePlanRequest) {
                foreach ($data['days'] as &$day) {
                    foreach (['weekday', 'cardio_minutes', 'posing_rounds', 'pose_hold_seconds', 'vacuum_sets', 'vacuum_hold_seconds'] as $field) {
                        $day[$field] = (int) $day[$field];
                    }
                }
                unset($day);
            }

            return $model::create([...$data, 'client_id' => $client->id, 'coach_id' => $request->user()->id]);
        });

        return response()->json(['data' => $record], 201);
    }

    /** @param class-string<NutritionPlan>|class-string<PracticePlan> $model */
    private function cancel(Request $request, User $client, int $plan, string $model): JsonResponse
    {
        Gate::authorize('manageMesocycles', $client);
        DB::transaction(function () use ($client, $plan, $model, $request): void {
            User::whereKey($client->id)->lockForUpdate()->firstOrFail();
            Gate::authorize('manageMesocycles', $client);
            $record = $model::where('client_id', $client->id)->findOrFail($plan);
            abort_unless($record->coach_id === $request->user()->id, 403);
            abort_unless(Carbon::parse($record->effective_from)->isAfter(today()), 422, 'Only future plans can be cancelled. Publish a new version to change an active plan.');
            $record->forceFill(['cancelled_at' => now()])->save();
        });

        return response()->json(null, 204);
    }
}
