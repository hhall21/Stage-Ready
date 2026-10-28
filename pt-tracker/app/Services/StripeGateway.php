<?php

namespace App\Services;

use App\Models\User;
use App\Support\CoachPricing;
use RuntimeException;
use Stripe\StripeClient;

class StripeGateway
{
    public function configured(?User $coach = null): bool
    {
        $secret = config('services.stripe.secret');
        $live = (bool) config('services.stripe.live_mode');
        $matchesMode = is_string($secret) && ($live
            ? (str_starts_with($secret, 'sk_live_') || str_starts_with($secret, 'rk_live_'))
            : (str_starts_with($secret, 'sk_test_') || str_starts_with($secret, 'rk_test_')));

        return $matchesMode && ($coach === null || CoachPricing::priceId($coach) !== null)
            && filled(config('services.stripe.webhook_secret'))
            && (! app()->isProduction() || config('services.stripe.live_mode'));
    }

    private function client(): StripeClient
    {
        if (! $this->configured()) {
            throw new RuntimeException('Payments are not configured yet. Please try again later.');
        }

        return new StripeClient((string) config('services.stripe.secret'));
    }

    public function createCustomer(User $coach): string
    {
        return $this->client()->customers->create(['email' => $coach->email, 'name' => $coach->name,
            'metadata' => ['coach_id' => (string) $coach->id]], ['idempotency_key' => 'stage-ready-'.hash('sha256', (string) config('app.url')).'-coach-'.$coach->id])->id;
    }

    public function verifiedPriceId(User $coach): string
    {
        $plan = CoachPricing::forCoach($coach);
        $priceId = CoachPricing::priceId($coach);
        if ($priceId === null) {
            throw new RuntimeException('Payments for this coaching discipline are not configured yet.');
        }
        $price = $this->client()->prices->retrieve($priceId);
        if (! $price->active || $price->type !== 'recurring' || $price->unit_amount !== $plan['amount_pence'] || $price->currency !== 'gbp' || $price->recurring->interval !== 'month' || $price->recurring->interval_count !== 1) {
            throw new RuntimeException('The configured Stripe price for '.$plan['label'].' must be '.$plan['price_label'].' GBP every month.');
        }

        return $price->id;
    }

    /** @return array<string, mixed> */
    public function createCheckout(User $coach, string $customer): array
    {
        $priceId = $this->verifiedPriceId($coach);
        $base = rtrim((string) config('app.url'), '/');

        return $this->client()->checkout->sessions->create([
            'mode' => 'subscription', 'customer' => $customer, 'client_reference_id' => (string) $coach->id,
            'line_items' => [['price' => $priceId, 'quantity' => 1]], 'payment_method_types' => ['card'],
            'subscription_data' => ['metadata' => ['coach_id' => (string) $coach->id]],
            'success_url' => $base.'/payment?session_id={CHECKOUT_SESSION_ID}', 'cancel_url' => $base.'/payment',
        ])->toArray();
    }

    /** @return array<string, mixed> */
    public function checkout(string $id): array
    {
        return $this->client()->checkout->sessions->retrieve($id)->toArray();
    }

    /** @return array<string, mixed> */
    public function invoice(string $id): array
    {
        return $this->client()->invoices->retrieve($id)->toArray();
    }

    /** @return array<string, mixed> */
    public function subscription(string $id): array
    {
        return $this->client()->subscriptions->retrieve($id)->toArray();
    }

    /** @return array<string, mixed> */
    public function cancelAtPeriodEnd(string $id): array
    {
        return $this->client()->subscriptions->update($id, ['cancel_at_period_end' => true])->toArray();
    }

    /** @return list<array<string, mixed>> */
    public function paidInvoices(string $customer): array
    {
        return array_values(array_map(fn ($invoice) => $invoice->toArray(), $this->client()->invoices
            ->all(['customer' => $customer, 'status' => 'paid', 'limit' => 10])->data));
    }

    public function portal(string $customer): string
    {
        return $this->client()->billingPortal->sessions->create(['customer' => $customer,
            'return_url' => rtrim((string) config('app.url'), '/').'/payment'])->url;
    }
}
