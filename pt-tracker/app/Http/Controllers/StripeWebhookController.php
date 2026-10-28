<?php

namespace App\Http\Controllers;

use App\Services\CoachBilling;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use UnexpectedValueException;

class StripeWebhookController extends Controller
{
    public function __invoke(Request $request, CoachBilling $billing): JsonResponse
    {
        $secret = config('services.stripe.webhook_secret');
        abort_unless(is_string($secret) && $secret !== '', 503);
        try {
            $event = Webhook::constructEvent($request->getContent(), (string) $request->header('Stripe-Signature'), $secret);
        } catch (SignatureVerificationException|UnexpectedValueException) {
            return response()->json(['message' => 'Invalid Stripe signature.'], 400);
        }
        abort_unless($event->livemode === (bool) config('services.stripe.live_mode'), 400, 'Stripe environment mismatch.');
        $billing->webhook($event->toArray());

        return response()->json(['received' => true]);
    }
}
