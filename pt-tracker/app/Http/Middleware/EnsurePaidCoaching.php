<?php

namespace App\Http\Middleware;

use App\Support\AccountNavigation;
use App\Support\CoachPricing;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePaidCoaching
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user?->role === 'coach' && ! $user->hasPaidCoachAccess()) {
            return $request->is('api/*')
                ? response()->json(['message' => 'Your coach access has expired. Renew your '.CoachPricing::forCoach($user)['price_label'].' monthly membership to continue.', 'billing_url' => '/payment'], 402)
                : redirect(AccountNavigation::forUser($user)['href']);
        }
        if ($user?->role === 'client' && ! $request->routeIs('dashboard') && $user->hasSuspendedCoaching()) {
            return response()->json(['message' => 'Your coach’s membership is unpaid. Coaching features are paused. Your account and available handovers remain accessible.'], 402);
        }

        return $next($request);
    }
}
