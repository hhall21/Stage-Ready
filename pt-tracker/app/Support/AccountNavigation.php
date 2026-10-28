<?php

namespace App\Support;

use App\Models\User;

class AccountNavigation
{
    /** @return array{href: string, label: string, workspace_url: string|null} */
    public static function forUser(?User $user): array
    {
        if ($user === null || ! $user->is_active) {
            return ['href' => '/login', 'label' => 'Member sign in', 'workspace_url' => null];
        }
        if ($user->is_admin) {
            return ['href' => '/admin', 'label' => 'Open admin portal', 'workspace_url' => '/admin'];
        }
        if ($user->role === 'coach' && ! $user->hasPaidCoachAccess()) {
            return $user->hasVerifiedEmail()
                ? ['href' => '/payment', 'label' => 'Continue to billing', 'workspace_url' => null]
                : ['href' => '/email/verify', 'label' => 'Verify your email', 'workspace_url' => null];
        }

        return ['href' => '/dashboard', 'label' => 'Open workspace', 'workspace_url' => '/dashboard'];
    }
}
