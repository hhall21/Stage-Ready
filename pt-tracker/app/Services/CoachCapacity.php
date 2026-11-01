<?php

namespace App\Services;

use App\Models\ClientInvitation;
use App\Models\TrainerClient;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class CoachCapacity
{
    public const LIMIT = 20;

    /** @return Builder<TrainerClient> */
    private function assignments(User $coach): Builder
    {
        // Account deactivation must not free a place that reactivation could reuse.
        return TrainerClient::where('trainer_id', $coach->id)->where('status', 'active')
            ->where(fn ($query) => $query->whereNull('end_date')->orWhereDate('end_date', '>=', today()));
    }

    /** @return array{limit: int, active_clients: int, pending_invitations: int, remaining: int} */
    public function summary(User $coach, ?int $excludingInvitation = null): array
    {
        $active = $this->assignments($coach)->count();
        $pending = ClientInvitation::where('trainer_id', $coach->id)->whereNull('accepted_at')->whereNull('revoked_at')
            ->where('expires_at', '>', now())->when($excludingInvitation !== null, fn ($query) => $query->where('id', '!=', $excludingInvitation))->count();

        return ['limit' => self::LIMIT, 'active_clients' => $active, 'pending_invitations' => $pending,
            'remaining' => max(0, self::LIMIT - $active - $pending)];
    }

    // Call inside the transaction holding the coach row lock, also used by coaching endings.
    public function ensureInvitationPlace(User $coach, ?int $excludingInvitation = null): void
    {
        if ($this->summary($coach, $excludingInvitation)['remaining'] === 0) {
            throw ValidationException::withMessages(['email' => 'Your plan has 20 coaching places. End coaching or cancel an outstanding invitation before inviting another client.']);
        }
    }

    public function ensureClientPlace(User $coach, ?User $client): void
    {
        if ($client !== null && $this->assignments($coach)->where('client_id', $client->id)->exists()) {
            return;
        }
        if ($this->assignments($coach)->count() >= self::LIMIT) {
            throw ValidationException::withMessages(['invitation' => 'This coach has reached the 20-client limit. Ask your coach to free a place, then try this invitation again.']);
        }
    }
}
