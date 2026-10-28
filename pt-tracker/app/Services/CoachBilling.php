<?php

namespace App\Services;

use App\Models\CoachMembership;
use App\Models\User;
use App\Support\CoachPricing;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CoachBilling
{
    public function __construct(private StripeGateway $stripe) {}

    /** @return array<string, mixed> */
    public function summary(User $coach): array
    {
        $membership = $coach->coachMembership()->first();

        return [...CoachPricing::forCoach($coach),
            'payments_configured' => $this->stripe->configured($coach),
            'subscription_management_configured' => $this->stripe->configured(), 'has_access' => $coach->hasPaidCoachAccess(),
            'exempt' => $coach->is_admin || $coach->billing_exempt, 'status' => $membership === null ? 'pending' : $membership->status,
            'last_paid_at' => $membership?->last_paid_at?->toISOString(), 'paid_through' => $membership?->paid_through?->toISOString(),
            'retention_expires_at' => $membership?->retention_expires_at?->toISOString(),
            'data_purged_at' => $membership?->data_purged_at?->toISOString(), 'cancel_at_period_end' => $membership === null ? false : $membership->cancel_at_period_end,
            'has_subscription' => $membership?->stripe_subscription_id !== null,
            'can_manage_subscription' => $membership?->stripe_customer_id !== null];
    }

    public function cancel(User $coach): void
    {
        $membership = $coach->coachMembership()->first();
        abort_unless($membership !== null && $membership->stripe_customer_id !== null && $membership->stripe_subscription_id !== null,
            409, 'This account has no subscription to cancel.');
        $subscriptionId = $membership->stripe_subscription_id;
        $subscription = $this->stripe->subscription($subscriptionId);
        abort_unless(($subscription['id'] ?? '') === $subscriptionId
            && ($subscription['customer'] ?? '') === $membership->stripe_customer_id
            && ($subscription['metadata']['coach_id'] ?? '') === (string) $coach->id, 403, 'This subscription does not belong to your account.');
        $ended = in_array($subscription['status'] ?? '', ['canceled', 'incomplete_expired'], true);
        if (! $ended && ! ($subscription['cancel_at_period_end'] ?? false)) {
            $subscription = $this->stripe->cancelAtPeriodEnd($subscriptionId);
            abort_unless(($subscription['id'] ?? '') === $subscriptionId && ($subscription['cancel_at_period_end'] ?? false) === true,
                502, 'Cancellation could not be confirmed. Please refresh billing and try again.');
        }
        DB::transaction(function () use ($coach, $membership, $subscriptionId, $subscription, $ended): void {
            User::whereKey($coach->id)->lockForUpdate()->firstOrFail();
            $current = CoachMembership::whereKey($membership->id)->lockForUpdate()->firstOrFail();
            abort_unless($current->stripe_subscription_id === $subscriptionId, 409, 'Your subscription changed. Refresh billing before cancelling.');
            $current->update(['status' => $subscription['status'], 'cancel_at_period_end' => ! $ended,
                'last_status_event_at' => max($current->last_status_event_at, now()->getTimestamp())]);
        });
    }

    public function checkout(User $coach): string
    {
        return DB::transaction(function () use ($coach): string {
            User::whereKey($coach->id)->lockForUpdate()->firstOrFail();
            $membership = CoachMembership::firstOrCreate(['user_id' => $coach->id]);
            if ($membership->stripe_customer_id === null) {
                $membership->update(['stripe_customer_id' => $this->stripe->createCustomer($coach)]);
            }
            if ($membership->stripe_subscription_id !== null) {
                $subscription = $this->stripe->subscription($membership->stripe_subscription_id);
                if (! in_array($subscription['status'] ?? '', ['canceled', 'incomplete_expired'], true)) {
                    return $this->stripe->portal($membership->stripe_customer_id);
                }
            }
            if ($membership->checkout_session_id !== null && $membership->checkout_expires_at?->isFuture()) {
                $session = $this->stripe->checkout($membership->checkout_session_id);
                if (($session['status'] ?? '') === 'open' && is_string($session['url'] ?? null)) {
                    return $session['url'];
                }
                if (($session['status'] ?? '') === 'complete' && is_string($session['subscription'] ?? null)) {
                    $membership->update(['stripe_subscription_id' => $session['subscription']]);

                    return $this->stripe->portal($membership->stripe_customer_id);
                }
            }
            $session = $this->stripe->createCheckout($coach, $membership->stripe_customer_id);
            $membership->update(['checkout_session_id' => $session['id'],
                'checkout_expires_at' => CarbonImmutable::createFromTimestampUTC($session['expires_at'])]);

            return $session['url'];
        });
    }

    public function confirm(User $coach, string $sessionId): void
    {
        $session = $this->stripe->checkout($sessionId);
        $membership = $coach->coachMembership()->firstOrFail();
        abort_unless(($session['client_reference_id'] ?? '') === (string) $coach->id
            && ($session['customer'] ?? '') === $membership->stripe_customer_id
            && ($session['mode'] ?? '') === 'subscription', 403);
        if (($session['payment_status'] ?? '') !== 'paid' || ! is_string($session['subscription'] ?? null)) {
            return;
        }
        $subscription = $this->stripe->subscription($session['subscription']);
        $invoice = $subscription['latest_invoice'] ?? null;
        if (is_string($invoice)) {
            $this->recordInvoice($this->stripe->invoice($invoice));
        }
    }

    /** @param array<string, mixed> $invoice */
    public function recordInvoice(array $invoice): bool
    {
        if (($invoice['status'] ?? '') !== 'paid'
            || (bool) ($invoice['livemode'] ?? false) !== (bool) config('services.stripe.live_mode')
            || ($invoice['currency'] ?? '') !== 'gbp' || ! is_string($invoice['id'] ?? null)) {
            return false;
        }
        $subscriptionId = $invoice['parent']['subscription_details']['subscription'] ?? $invoice['subscription'] ?? null;
        if (! is_string($subscriptionId) || ! is_string($invoice['customer'] ?? null)) {
            return false;
        }
        $subscription = $this->stripe->subscription($subscriptionId);
        $coachId = (int) ($subscription['metadata']['coach_id'] ?? 0);
        $coach = User::find($coachId);
        if (! $coach || $coach->role !== 'coach' || ($subscription['customer'] ?? '') !== $invoice['customer']) {
            return false;
        }
        $amount = CoachPricing::forCoach($coach)['amount_pence'];
        $priceId = CoachPricing::priceId($coach);
        if ($priceId === null || ($invoice['amount_paid'] ?? 0) !== $amount) {
            return false;
        }
        $lines = $invoice['lines']['data'] ?? [];
        if (! is_array($lines)) {
            return false;
        }
        $line = null;
        foreach ($lines as $candidate) {
            if (is_array($candidate)
                && ($candidate['pricing']['price_details']['price'] ?? $candidate['price']['id'] ?? null) === $priceId
                && ($candidate['amount'] ?? 0) === $amount && ($candidate['quantity'] ?? 1) === 1) {
                $line = $candidate;
                break;
            }
        }
        $start = $line['period']['start'] ?? null;
        $end = $line['period']['end'] ?? null;
        $paidAt = $invoice['status_transitions']['paid_at'] ?? null;
        if (! is_int($start) || ! is_int($end) || ! is_int($paidAt) || $start >= $end || $paidAt <= 0 || $paidAt > now()->getTimestamp() + 300) {
            return false;
        }

        return DB::transaction(function () use ($coach, $invoice, $subscriptionId, $subscription, $start, $end, $paidAt, $amount): bool {
            User::whereKey($coach->id)->lockForUpdate()->firstOrFail();
            $membership = $coach->coachMembership()->lockForUpdate()->first();
            if (! $membership || $membership->stripe_customer_id !== $invoice['customer']) {
                return false;
            }
            $periodEnd = CarbonImmutable::createFromTimestampUTC($end);
            DB::table('coach_payments')->insertOrIgnore(['membership_id' => $membership->id, 'stripe_invoice_id' => $invoice['id'],
                'amount_pence' => $amount, 'currency' => 'gbp', 'paid_at' => CarbonImmutable::createFromTimestampUTC($paidAt),
                'period_started_at' => CarbonImmutable::createFromTimestampUTC($start), 'period_ended_at' => $periodEnd,
                'created_at' => now(), 'updated_at' => now()]);
            $paymentTime = CarbonImmutable::createFromTimestampUTC($paidAt);
            if ($membership->last_paid_at === null || $paymentTime->gt($membership->last_paid_at)) {
                $membership->update(['last_paid_at' => $paymentTime]);
            }
            if ($membership->paid_through === null || $periodEnd->gt($membership->paid_through)) {
                $membership->update(['stripe_subscription_id' => $subscriptionId, 'status' => $subscription['status'],
                    'paid_through' => $periodEnd,
                    'retention_expires_at' => $periodEnd->addDays(7), 'data_purged_at' => null,
                    'cancel_at_period_end' => (bool) ($subscription['cancel_at_period_end'] ?? false)]);
            }

            return true;
        });
    }

    public function reconcile(CoachMembership $membership): void
    {
        if ($membership->stripe_customer_id === null) {
            throw new RuntimeException('Cannot verify payments before retention cleanup.');
        }
        $verified = false;
        foreach ($this->stripe->paidInvoices($membership->stripe_customer_id) as $invoice) {
            $verified = $this->recordInvoice($invoice) || $verified;
        }
        if (! $verified) {
            throw new RuntimeException('No matching paid invoice could be verified. Cleanup has been deferred.');
        }
    }

    /** @param array<string, mixed> $event */
    public function webhook(array $event): void
    {
        if (DB::table('stripe_webhook_events')->where('id', $event['id'])->exists()) {
            return;
        }
        $object = $event['data']['object'] ?? [];
        if ($event['type'] === 'invoice.paid' && is_string($object['id'] ?? null)) {
            $this->recordInvoice($this->stripe->invoice($object['id']));
        } elseif (in_array($event['type'], ['customer.subscription.updated', 'customer.subscription.deleted'], true)) {
            DB::transaction(function () use ($event, $object): void {
                $membership = CoachMembership::where('stripe_subscription_id', $object['id'] ?? '')->lockForUpdate()->first();
                if ($membership && (int) $event['created'] >= $membership->last_status_event_at) {
                    $membership->update(['status' => $object['status'], 'cancel_at_period_end' => (bool) ($object['cancel_at_period_end'] ?? false),
                        'last_status_event_at' => $event['created']]);
                }
            });
        }
        DB::table('stripe_webhook_events')->insertOrIgnore(['id' => $event['id'], 'type' => $event['type'], 'handled_at' => now()]);
    }
}
