<?php

namespace App\Policies;

use App\Models\User;

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
            ->whereDate('trainer_clients.start_date', '<=', today()->toDateString())
            ->where(fn ($query) => $query->whereNull('trainer_clients.end_date')
                ->orWhereDate('trainer_clients.end_date', '>=', today()->toDateString()))
            ->exists();
    }

    public function manageCheckIns(User $user, User $client): bool
    {
        return $user->role === 'client' && $this->viewTracker($user, $client);
    }

    public function manageWorkouts(User $user, User $client): bool
    {
        return $this->viewTracker($user, $client);
    }

    public function manageMesocycles(User $user, User $client): bool
    {
        return $user->role === 'coach' && $this->viewTracker($user, $client);
    }
}
