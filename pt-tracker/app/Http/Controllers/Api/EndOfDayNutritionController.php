<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveDailyCheckInRequest;
use App\Models\User;
use App\Support\DateBounds;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class EndOfDayNutritionController extends Controller
{
    private const FIELDS = ['hunger_level', 'appetite_level', 'digestive_discomfort', 'digestion_notes', 'dietary_difficulties'];

    public function show(Request $request, User $client): JsonResponse
    {
        Gate::authorize('viewTracker', $client);
        $data = $request->validate(['date' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today']]);
        $data['date'] ??= today()->toDateString();
        $record = $client->dailyCheckIns()->where('check_in_date', '>=', DateBounds::start($data['date']))->where('check_in_date', '<', DateBounds::after($data['date']))->first();

        return response()->json(['data' => $record?->only(['check_in_date', 'nutrition_completed_at', ...self::FIELDS])]);
    }

    public function store(Request $request, User $client): JsonResponse
    {
        Gate::authorize('manageCheckIns', $client);
        $rules = Arr::only((new SaveDailyCheckInRequest)->rules(), self::FIELDS);
        $data = $request->validate([
            ...$rules,
            'check_in_date' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'client_id' => ['prohibited'], 'nutrition_completed_at' => ['prohibited'],
            'submitted_at' => ['prohibited'], 'photo_version' => ['prohibited'],
        ]);
        $data['check_in_date'] ??= today()->toDateString();
        $record = DB::transaction(function () use ($client, $data) {
            User::whereKey($client->id)->lockForUpdate()->firstOrFail();
            Gate::authorize('manageCheckIns', $client);
            $record = $client->dailyCheckIns()->where('check_in_date', '>=', DateBounds::start($data['check_in_date']))->where('check_in_date', '<', DateBounds::after($data['check_in_date']))->first();
            $responses = array_merge(array_fill_keys(self::FIELDS, null), $record?->only(self::FIELDS) ?? [], Arr::only($data, self::FIELDS));
            $hasResponses = collect($responses)->contains(fn ($value) => filled($value));
            if (! $record && ! $hasResponses) {
                return null;
            }
            $record ??= $client->dailyCheckIns()->make(['check_in_date' => $data['check_in_date']]);
            $record->fill($responses)->forceFill(['nutrition_completed_at' => $hasResponses ? now() : null])->save();

            return $record;
        });

        return response()->json(['data' => $record?->only(['check_in_date', 'nutrition_completed_at', ...self::FIELDS])]);
    }
}
