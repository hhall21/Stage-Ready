<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\CoachBilling;
use App\Services\StripeGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BillingController extends Controller
{
    public function cancellationPage(Request $request): Response
    {
        $this->coach($request);

        return Inertia::render('CancelSubscription');
    }

    public function index(Request $request, CoachBilling $billing): JsonResponse
    {
        return response()->json(['data' => $billing->summary($this->coach($request))]);
    }

    public function checkout(Request $request, CoachBilling $billing, StripeGateway $stripe): JsonResponse
    {
        $coach = $this->coach($request);
        abort_unless($coach->hasVerifiedEmail(), 403, 'Verify your email before starting checkout.');
        abort_if($coach->is_admin || $coach->billing_exempt, 409, 'This account already has access without a subscription.');
        abort_unless($stripe->configured($coach), 503, 'Payments are not configured yet. Please try again later.');

        return response()->json(['url' => $billing->checkout($coach)]);
    }

    public function confirm(Request $request, CoachBilling $billing, StripeGateway $stripe): JsonResponse
    {
        $coach = $this->coach($request);
        $data = $request->validate(['session_id' => ['required', 'string', 'max:255', 'regex:/^cs_[A-Za-z0-9_]+$/']]);
        abort_unless($stripe->configured($coach), 503, 'Payments are not configured yet.');
        $billing->confirm($coach, $data['session_id']);

        return response()->json(['data' => $billing->summary($coach)]);
    }

    public function portal(Request $request, StripeGateway $stripe): JsonResponse
    {
        $customer = $this->coach($request)->coachMembership()->first()?->stripe_customer_id;
        abort_unless(is_string($customer) && $customer !== '', 409, 'This account has no Stripe subscription to manage.');
        abort_unless($stripe->configured(), 503, 'Payments are not configured yet.');

        return response()->json(['url' => $stripe->portal($customer)]);
    }

    public function cancel(Request $request, CoachBilling $billing, StripeGateway $stripe): JsonResponse
    {
        $coach = $this->coach($request);
        $request->validate(['confirmed' => ['required', 'accepted']]);
        abort_unless($stripe->configured(), 503, 'Payments are not configured yet. Please try again later.');
        $billing->cancel($coach);
        $summary = $billing->summary($coach);

        return response()->json(['message' => $summary['cancel_at_period_end']
            ? 'Automatic renewal is cancelled. Your paid access continues until its expiry date.'
            : 'This subscription has already ended.', 'data' => $summary]);
    }

    private function coach(Request $request): User
    {
        $user = $request->user();
        abort_unless($user?->role === 'coach', 403);

        return $user;
    }
}
