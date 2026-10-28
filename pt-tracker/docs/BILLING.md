# Stage-Ready coach billing

Coaches register at `/register`, choose their fixed discipline and verify email
ownership. They subscribe through Stripe Checkout for **£6 GBP every month for
powerlifting or £8 GBP every month for bodybuilding**,
with automatic renewal. No administrator invitation or approval is required.
Clients still join through coach invitations. Administrators retain access
independently of billing.

## Stripe setup

Create two active recurring Prices with `currency=gbp`, `interval=month` and
`interval_count=1`: powerlifting with `unit_amount=600` and bodybuilding with
`unit_amount=800`. The server selects the Price from the coach's saved discipline
and verifies those settings; the browser cannot choose a different price.
Configure these secrets and IDs on the host, never in Git:

```dotenv
STRIPE_SECRET_KEY=sk_test_REPLACE_ME
STRIPE_POWERLIFTING_PRICE_ID=price_REPLACE_WITH_POWERLIFTING_PRICE
STRIPE_BODYBUILDING_PRICE_ID=price_REPLACE_WITH_BODYBUILDING_PRICE
STRIPE_WEBHOOK_SECRET=whsec_REPLACE_ME
STRIPE_LIVE_MODE=false
```

Production requires matching live credentials and Price IDs, with
`STRIPE_LIVE_MODE=true`. Test credentials cannot enable production checkout.
The former `STRIPE_COACH_PRICE_ID` setting is no longer used. Existing Stripe
subscriptions are not repriced by changing these settings: update any old-price
subscriptions in Stripe before their next renewal. Existing verified payment
records and paid-through dates remain unchanged. A missing Price disables new
checkout for that discipline while configured subscription management remains
available for existing customers.
Run `composer install`, `php artisan migrate --force`, `npm ci` and `npm run build`
after updating. Real inbox delivery requires a configured mail provider and a
public HTTPS `APP_URL`; local development still logs emails by default.

Configure a signed webhook at `https://YOUR_DOMAIN/stripe/webhook` for
`invoice.paid`, `customer.subscription.updated` and `customer.subscription.deleted`.
Only this endpoint is exempt from browser CSRF checks. Signatures and test/live
mode are verified. Event and invoice IDs prevent duplicate entitlements. The
checkout return route also verifies the session through Stripe; payments grant
access even if the customer closes their browser before returning.

Enable Stripe's customer portal for invoices, payment methods and recovery.
Set retry/cancellation rules to match the seven-day retention window, avoiding
continued collections after coaching data is deleted. Configure receipts in
Stripe. Entitlements require a verified invoice for the coach's discipline:
£6 for powerlifting or £8 for bodybuilding, with the matching configured Price;
discounts, free trials and additional charges are not supported by these rules.

## Payment dates and access

`coach_payments` stores each verified invoice, exact payment timestamp, amount,
currency and covered period. `coach_memberships` holds the latest payment,
paid-through timestamp, retention deadline and cleanup state. Stripe's invoiced
calendar period defines a month, rather than a fixed 30-day approximation.
Out-of-order invoices cannot shorten paid access.

Expired coaches can sign in to billing but cannot use coaching features.
Affected clients lose coaching feature reads and writes immediately, including
existing sessions, while login and handovers remain available. Clients with
another eligible active coach retain their workspace. Cancellation keeps access
until the paid-through date. Failed renewal does not extend the paid period.
Recovery uses the existing subscription's portal instead of creating duplicates.
Administrator deactivation stays separate and payment cannot override it.

## Coach cancellation

Billing links to `/subscription/cancel`, an authenticated coach page showing the
paid-through date, retention deadline and handover policy. Opening the page does
not change the subscription. Coaches must select **Confirm cancellation** to
stop automatic renewal.

The server loads the account's stored subscription and verifies its Stripe
customer and coach metadata before setting `cancel_at_period_end=true`. The
browser cannot choose another subscription. Repeated confirmations are safe;
already cancelled or ended subscriptions are not updated again. A Stripe failure
does not report success or change local access dates.

Cancellation preserves verified paid access and the existing seven-day retention
deadline. It does not issue an automatic refund. Existing unpaid invoices are
handled separately in billing. Subsequent Stripe events keep subscription status
up to date; the normal expiry and cleanup rules still apply.

## Retention and handovers

Run Laravel's scheduler every minute and a supervised queue worker on the host:

```text
* * * * * cd /ABSOLUTE/PATH/pt-tracker && php artisan schedule:run >> /dev/null 2>&1
```

```sh
php artisan queue:work
```

`coaching:expire-unpaid` runs every five minutes without overlapping copies.
Seven days after paid access ends, it rechecks Stripe before deleting anything.
Unreachable Stripe or unverified payment records defer deletion while access
stays blocked. A later verified payment prevents cleanup if it restores access.

The command creates affected clients' PDFs and ends expired assignments in one
transaction. A PDF failure rolls the transaction back. Without another active
coach needing a client's shared records, it removes their check-ins, workouts,
sets, splits, meals, plans, reviews, health readings, competitions and other
clinical coaching records. Photo and clip files are removed by retriable jobs
after commit. Accounts remain. Another active coach protects shared records.
Financial payment records are retained separately from client coaching data.

Billing-expiry handovers retain the snapshot and PDF until deliberately opened.
Listing reports or opening the dashboard does not consume them. **View handover**
fetches a browser copy; the preview acknowledges that it loaded, and the server
then deletes the report. The browser copy can be downloaded during that visit.
Leaving the page removes it. A failed load or acknowledgement keeps the stored
report. Ordinary handovers from manually ending coaching remain permanent.

Use `php artisan coaching:expire-unpaid --dry-run` for a read-only overdue count.
Test destructive cleanup only in a disposable database. Initial unpaid accounts
and legacy accounts without a recorded paid period do not receive a fabricated
deletion deadline. Ordinary coaches need payment; administrators and explicitly
exempt fictional development fixtures retain access.

Deletion covers active application records and queued media cleanup. Backups,
replicas and database logs need a matching retention policy outside this code.
Restores must not revive deleted data or consumed reports. Paying after deletion
cannot recover removed records. Ended clients need fresh coaching invitations.

## Endpoints

- `POST /api/register`: create an unpaid coach and request email verification.
- `GET /email/verify/{id}/{hash}`: signed verification link.
- `POST /api/email/verification-notification`: request another verification email.
- `GET /api/billing`: own price, access and payment/retention timestamps.
- `POST /api/billing/checkout`: create/reuse checkout or recover a subscription.
- `POST /api/billing/confirm`: verify own checkout session through Stripe.
- `POST /api/billing/portal`: open own subscription management.
- `GET /subscription/cancel`: show the coach cancellation page.
- `POST /api/billing/cancel`: confirm own cancellation with `confirmed=true`.
- `POST /stripe/webhook`: signed Stripe events.
- `POST /api/coaching-handovers/{id}/open`: request an owned temporary report.
- `POST /api/coaching-handovers/{id}/viewed`: acknowledge receipt with its token
  and remove the owned temporary report.
