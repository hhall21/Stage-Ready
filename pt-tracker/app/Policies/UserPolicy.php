<?php

namespace App\Policies;

use App\Models\User;
use App\Support\DateBounds;

class UserPolicy
{
    public function viewTracker(User $user, User $client): bool
    {
        if (! $user->is_active || ! $client->is_active || $client->role !== 'client') {
            return false;
        }
        if ($user->role === 'client') {
            return $user->is($client);
        }

        return $user->role === 'coach' && $user->clients()
            ->where('users.id', $client->id)
            ->wherePivot('status', 'active')
            ->where('trainer_clients.start_date', '<', DateBounds::after(today()->toDateString()))
            ->where(fn ($query) => $query->whereNull('trainer_clients.end_date')
                ->orWhereDate('trainer_clients.end_date', '>=', today()->toDateString()))
            ->exists();
    }

    public function manageCheckIns(User $user, User $client): bool
    {
        return $user->role === 'client' && $this->viewTracker($user, $client);
    }

    public function useCheckInPhotos(User $user, User $client): bool
    {
        if (! $this->viewTracker($user, $client)) {
            return false;
        }

        return $user->role === 'coach' ? $user->is_bodybuilding_coach : $client->hasBodybuildingCoach();
    }

    public function manageWorkouts(User $user, User $client): bool
    {
        return $user->role === 'coach' && $this->viewTracker($user, $client);
    }

    public function manageMesocycles(User $user, User $client): bool
    {
        return $user->role === 'coach' && $this->viewTracker($user, $client);
    }

    public function manageCompetitions(User $user, User $client): bool
    {
        return $user->role === 'coach' && $this->viewTracker($user, $client);
    }
}
