<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveDailyCheckInRequest;
use App\Models\DailyCheckIn;
use App\Models\ProgressPhoto;
use App\Models\User;
use App\Services\TemporaryPosePhotos;
use App\Support\CheckInPhotoCleanup;
use App\Support\DateBounds;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class DailyCheckInController extends Controller
{
    public function __construct(private TemporaryPosePhotos $temporary) {}

    public function index(User $client): JsonResponse
    {
        Gate::authorize('viewTracker', $client);

        return response()->json([
            ...$client->dailyCheckIns()->with('retainedPosePhotos')->orderByDesc('check_in_date')->paginate(30)
                ->through(fn (DailyCheckIn $record) => $this->withPhotoCount($record))->toArray(),
            'posing_enabled' => Gate::allows('useCheckInPhotos', $client),
        ]);
    }

    public function show(User $client, int $checkIn): JsonResponse
    {
        Gate::authorize('viewTracker', $client);

        return response()->json(['data' => $this->withPhotoCount($client->dailyCheckIns()->with('retainedPosePhotos')->findOrFail($checkIn))]);
    }

    public function store(SaveDailyCheckInRequest $request, User $client): JsonResponse
    {
        Gate::authorize('manageCheckIns', $client);
        $data = $request->validated();
        $data['check_in_date'] ??= today()->toDateString();
        $record = DB::transaction(function () use ($client, $data) {
            $lockedClient = User::whereKey($client->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($lockedClient)->authorize('manageCheckIns', $lockedClient);
            if ($client->dailyCheckIns()->where('check_in_date', '>=', DateBounds::start($data['check_in_date']))->where('check_in_date', '<', DateBounds::after($data['check_in_date']))->exists()) {
                throw ValidationException::withMessages(['check_in_date' => 'A check-in already exists for this date.']);
            }

            return $client->dailyCheckIns()->create($data);
        });

        return response()->json(['data' => $this->withPhotoCount($record->refresh()->load('retainedPosePhotos'))], 201);
    }

    public function update(SaveDailyCheckInRequest $request, User $client, int $checkIn): JsonResponse
    {
        Gate::authorize('manageCheckIns', $client);
        $data = $request->validated();
        if (! isset($data['check_in_date'])) {
            unset($data['check_in_date']);
        }
        $record = DB::transaction(function () use ($client, $data, $checkIn) {
            $lockedClient = User::whereKey($client->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($lockedClient)->authorize('manageCheckIns', $lockedClient);
            $record = $client->dailyCheckIns()->lockForUpdate()->findOrFail($checkIn);
            if (isset($data['check_in_date']) && $client->dailyCheckIns()->whereKeyNot($checkIn)->where('check_in_date', '>=', DateBounds::start($data['check_in_date']))->where('check_in_date', '<', DateBounds::after($data['check_in_date']))->exists()) {
                throw ValidationException::withMessages(['check_in_date' => 'A check-in already exists for this date.']);
            }
            if (isset($data['check_in_date']) && $record->check_in_date->toDateString() !== $data['check_in_date']) {
                $record->forceFill(['photo_version' => $record->photo_version + 1, 'submitted_at' => null]);
            }
            $record->fill($data)->save();
            if (array_intersect(array_keys($data), ['hunger_level', 'appetite_level', 'digestive_discomfort', 'digestion_notes', 'dietary_difficulties']) !== []) {
                $record->forceFill(['nutrition_completed_at' => null])->save();
            }

            return $record;
        });

        return response()->json(['data' => $this->withPhotoCount($record->load('retainedPosePhotos'))]);
    }

    public function destroy(User $client, int $checkIn): JsonResponse
    {
        Gate::authorize('manageCheckIns', $client);
        DB::transaction(function () use ($client, $checkIn): void {
            $lockedClient = User::whereKey($client->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($lockedClient)->authorize('manageCheckIns', $lockedClient);
            $record = $client->dailyCheckIns()->lockForUpdate()->findOrFail($checkIn);
            $paths = array_values($record->posePhotos()->get()->map(fn (ProgressPhoto $photo): string => $photo->file_path)->all());
            $record->delete();
            CheckInPhotoCleanup::afterCommit($paths);
            $this->temporary->afterCheckInDeletion($client->id, [$checkIn]);
        });

        return response()->json(null, 204);
    }

    private function withPhotoCount(DailyCheckIn $record): DailyCheckIn
    {
        $retained = $record->retainedPosePhotos->pluck('pose_key')->all();
        $temporary = array_filter($this->temporary->photos($record), fn (array $photo) => ! in_array($photo['pose_key'], $retained, true));
        $record->setAttribute('pose_photos_count', $record->retainedPosePhotos->count() + count($temporary));
        $record->unsetRelation('retainedPosePhotos');

        return $record;
    }
}
