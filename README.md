# Stage-Ready

All your client data in one place. Stage-Ready brings training, nutrition,
check-ins, progress and coach feedback into one client workspace for bodybuilding
and powerlifting coaches. Clients follow their plans, log sessions and share
check-ins in the same platform.

## Features

- Separate coach and client workspaces, with private records and role-based access.
- Public coach registration and automatic monthly Stripe subscriptions: £6 GBP
  for powerlifting or £8 GBP for bodybuilding.
  Coaches can cancel renewal from Billing and keep access until their paid
  period ends.
- Client invitations, coaching handovers and an administrator portal.
- Repeating 1–15 day training splits, workout logging and progress charts.
- Training-day and rest-day meals, daily prescriptions and written coach reviews.
- Optional check-ins, private posing photos and seven-day bodyweight averages.
  Coaches can keep any four check-in photos for seven days after first viewing.
- Responsive dark and light themes, with the choice saved in the browser.

When payment expires, affected coaching features pause. Coaching data is retained
for seven extra days. If still unpaid, eligible records are deleted after client
handovers are prepared. Client accounts and records needed by another active
coach are preserved. See [billing and retention](pt-tracker/docs/BILLING.md).

## Development

The Laravel application is in [`pt-tracker/`](pt-tracker/). Follow the
[application README](pt-tracker/README.md) for requirements, local setup,
administrator access, sample accounts and verification commands.

Stack: Laravel 13, PHP 8.4.1+, Vue 3, TypeScript, Inertia.js 3, Tailwind CSS 4
and Vite Plus. Local development uses SQLite.

Real payments and email delivery require configured providers. Individual meal
logging, training clips, supplements, phases and cycle entries have database
models but do not yet have complete user interfaces. The contact API does not
yet have a public form.

## Documentation

- [Application setup and development](pt-tracker/README.md)
- [Stripe billing and data retention](pt-tracker/docs/BILLING.md)
- [API reference](pt-tracker/docs/API.md)
- [Training analytics](pt-tracker/docs/TRAINING_ANALYTICS.md)
- [Database migration guidance](pt-tracker/database/MIGRATIONS.md)
- [Security](SECURITY.md)
