<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveCoachingForWrites
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        abort_if(
            ! $request->isMethodSafe() && $user?->role === 'client' && ! $user->hasActiveCoach(),
            403,
            'You need an active coach to update coaching records. Your handover is still available.',
        );

        return $next($request);
    }
}
