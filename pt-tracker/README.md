# Stage-Ready application

Laravel 13 and Vue 3 application for bodybuilding and powerlifting coaching.
See the [project overview](../README.md) for features and the
[API reference](docs/API.md) for endpoint details.

## Requirements

- PHP 8.4.1 or newer, with the database driver for your chosen database.
- Composer.
- Node.js 22.18+ within the 22.x series, or Node.js 24.11+, with npm.
- SQLite for local development, or a MySQL-compatible database.

## Local setup

Run these commands from `pt-tracker/` for a new local installation:

```sh
composer install
npm ci
cp .env.example .env
php artisan key:generate
php -r "file_exists('database/database.sqlite') || touch('database/database.sqlite');"
php artisan migrate
php artisan db:seed
npm run build
php artisan serve
```

Open **http://localhost:8000**. Seeding is optional and requires a new, empty
local database. Keep an existing `.env` and skip seeding when updating an
installation. For databases created from legacy SQL, read the
[migration notes](database/MIGRATIONS.md) first.

| Page                      | URL                    |
| ------------------------- | ---------------------- |
| Public website            | `/`                    |
| Coach registration        | `/register`            |
| Member login              | `/login`               |
| Coach billing             | `/payment`             |
| Cancel subscription       | `/subscription/cancel` |
| Coach or client workspace | `/dashboard`           |
| Administrator portal      | `/admin`               |

For frontend development, run `npm run dev` in a second terminal. Keep
`php artisan queue:listen` running to retry file-cleanup jobs. After setup,
`composer run dev` starts the Laravel server, queue listener and frontend server
together.

## Coach signup and billing

Coaches register publicly, choose a discipline, verify their email and subscribe
for **£6 GBP per month for powerlifting or £8 GBP per month for bodybuilding,
with automatic renewal**. No administrator invitation or
approval is required. Only server-verified payments grant paid access. Coaches
then invite clients by email; coaching starts when an invitation is accepted.
Coach navigation and sign-in lead to email verification or billing until paid
access is verified; workspace links use the server's current access state.

Billing links to a cancellation page showing the paid-through date and retention
deadline. Confirmation cancels renewal through Stripe, keeping the existing
paid period and retention dates. The Stripe customer portal handles invoices,
payment methods and subscription recovery.

When payment expires, coach and affected client coaching features pause. Login,
billing and handovers remain available. After seven extra unpaid days, client
handovers are prepared before eligible coaching records and media are deleted.
Client accounts and records needed by another active coach are preserved.
Billing-expiry handovers are removed after the client deliberately opens and
receives the document. Manual coaching endings keep their existing history.

See [billing setup](docs/BILLING.md) for Stripe credentials, webhooks, scheduled
cleanup, queue workers and retention details. Real billing requires provider
configuration; the local defaults do not charge cards.

## Administrator setup

After migrations, run this command from a trusted terminal on the application host:

```sh
php artisan stage-ready:make-admin owner@example.com --name="Your name"
```

For a new account, it prints a private password setup link valid for 60 minutes.
The command does not send email. Open the link, choose a password and sign in at
`/login` to reach `/admin`. Keep the link private.

For an existing active coach, the command grants administrator access without
changing their password or records. `--name` is only needed for a new account.
Inactive accounts and client accounts cannot be promoted by this command.
Account activity is managed separately from paid membership.

## Email delivery

Set `APP_URL` to the address people use to open Stage-Ready. Verification,
invitation and password reset links use this address.

Local development uses `MAIL_MAILER=log`. Emails appear in
`storage/logs/laravel.log`, not an inbox. For real delivery, configure an SMTP or
supported mail provider with `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`,
`MAIL_USERNAME`, `MAIL_PASSWORD` and `MAIL_FROM_ADDRESS`. Test verification,
invitation and reset emails before using real accounts. Keep credentials out of Git.

Dynamic requests have server-side rate limits, with tighter budgets for email,
billing, uploads, reports and analytics. Use a shared database or Redis cache
across application instances. See [request limits](docs/API.md#request-limits).

## Check-in photo retention

All 13 optional poses are available for temporary review. Coaches can choose any
four photos per check-in to keep for seven days after their first coach view,
and download any available photo to their own device. Unselected review photos
expire seven days after upload. Measurements and feedback remain stored.

Run migrations and `php artisan photos:expire` when updating. Keep the scheduler
and queue worker running for automatic cleanup. See [photo storage](docs/PHOTOS.md).

## Sample accounts

`php artisan db:seed` creates these fictional accounts in a new local database:

| Role   | Email                  | Password   |
| ------ | ---------------------- | ---------- |
| Coach  | `james@pttracker.test` | `password` |
| Client | `adam@example.com`     | `password` |

Optional loaders add more fictional records without clearing existing data:

```sh
php artisan db:seed --class=DevelopmentSampleSeeder
php artisan db:seed --class=DevelopmentGraphHistorySeeder
php artisan db:seed --class=DevelopmentCompetitionSeeder
```

These add ten client scenarios, six weeks of graph history and sample
competitions respectively. They run only in `local` or `testing`. Never deploy
the demo database or use these public passwords for real accounts.

## Verification

```sh
php artisan test
composer validate --strict
composer audit --locked
npm audit
php vendor/bin/pint --test
php vendor/bin/phpstan analyse --no-progress --memory-limit=512M
npm run types:check
npm run check
npm run build
php database/verify-demo.php database/database.sqlite
```

`composer ci:check` runs frontend checks, PHP formatting, static analysis and
application tests. GitHub workflows also build assets, check dependencies and
demo data, test SQLite and MySQL, and verify handover preview pagination.

## Deployment requirements

Serve the application from `public/`, allow writes to `storage/` and
`bootstrap/cache/`, and use a private environment file with a unique app key.
Build assets, install production PHP dependencies and run migrations when updating.
The health endpoint is `/up`. Hosted billing also needs HTTPS, working mail,
Stripe webhooks, Laravel's scheduler and a supervised queue worker; see
[billing setup](docs/BILLING.md).

Use `.env.production.example` for production settings. `deploy/` contains Nginx,
PHP, Supervisor and scheduler examples. Run
`php artisan stage-ready:check-launch --verify-stripe` on the host after setup;
use `--staging` with test credentials.
The check does not create charges or change application records. It does not
replace actual email, webhook, worker, scheduler or backup-restore checks.

Set `SUPPORT_EMAIL`, `PRIVACY_POLICY_URL` and `TERMS_OF_SERVICE_URL` for the public
support and policy links. The `/privacy` and `/terms` pages render reviewed
Markdown from `storage/app/policies/privacy.md` and `terms.md`; keep these files
persistent. Missing or blank documents return 404. They are not supplied legal
policies: publish the business's approved text before opening signup.

## Further documentation

- [API and implementation notes](docs/API.md)
- [Training analytics](docs/TRAINING_ANALYTICS.md)
- [Migration guidance](database/MIGRATIONS.md)
- [Artwork](resources/artwork/README.md)
- [Security](../SECURITY.md)
