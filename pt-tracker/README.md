# Stage-Ready

**A training and progress platform for bodybuilding coaches and their clients.**

Stage-Ready brings repeating training plans, workout logging, daily check-ins, and
bodyweight trends into one workspace. Coaches plan a mesocycle once, assign it
for a chosen period, and adjust upcoming sessions as a client's needs change.
Clients follow their schedule and record what they actually did.

The interface uses dark backgrounds, white typography, and borderless bodybuilding
photographs that blend into the page. It adapts to desktop and mobile screens.

## Features

### Coach workspace

- View clients with a current, active coaching assignment.
- Invite new clients by name and email from the Clients section, or invite an
  existing account holder to accept after signing in. Access starts immediately
  when the invitation is accepted; no coaching dates or upcoming-client list.
- Resend or cancel invitations. Resending replaces the previous link; links expire
  after seven days. A copyable link is available if email delivery is unavailable.
- End coaching to remove the coach's access while preserving the client's account,
  workouts, check-ins, and logged history.
- Review each client's check-ins, training sessions, and bodyweight trends.
- Create mesocycles with a start date and duration or a custom end date.
- Define weekly workouts that repeat automatically throughout the training block.
- Edit exercises, sets, reps, weight, RIR, and coaching notes when plans need to change.

### Mesocycle-based programming

A coach can create a block such as **Push on Monday, Pull on Wednesday, and Legs
on Friday for eight weeks**. Stage-Ready generates the dated sessions, so the coach
does not need to plan each individual workout.

- Up to **10 exercises per workout**.
- Default duration of eight weeks, with adjustable dates.
- No overlapping mesocycles for the same client.
- Upcoming unlogged sessions update when the mesocycle changes, including today's
  session if training has not been logged.
- Past sessions, completed workouts, sessions with logged sets, and sessions with
  training clips retain their existing records.
- Logging a session preserves it from later mesocycle changes, even if it is
  subsequently marked incomplete.

Plans currently support up to fourteen weekly workout templates, one hundred
sets per exercise, and a date range of up to one year.

### Client workspace

- View assigned mesocycles and scheduled workouts.
- Log actual reps, weight, reps in reserve (**RIR**), and completion for each set.
- Mark workouts as completed.
- Add an extra workout outside the repeating plan.
- Create, edit, and delete personal daily check-ins.

### Daily check-ins

Each client can record one check-in per date, with optional:

| Training and recovery      | Daily habits |
| -------------------------- | ------------ |
| Bodyweight                 | Steps        |
| Sleep duration and quality | Water intake |
| Energy and stress ratings  | Notes        |

Sleep quality, energy, and stress use a 1–5 rating scale.

### Bodyweight trends

The dashboard displays a **rolling seven-day average**, helping coaches see the
direction of bodyweight over time.

- Each average uses the recorded weights from that day and the six days before it.
- Missing weights are excluded rather than counted as zero.
- Future-dated check-ins are excluded.
- Each point includes the number of weigh-ins contributing to it.
- The current average is compared with the average seven days earlier.
- The chart covers 56 days, with a table of daily averages and reading counts.
- Windows with no weigh-ins appear as gaps.

### Authentication and access

- Session-based login and logout.
- CSRF protection on authenticated write requests.
- Login rate limiting.
- Password reset by email, with single-use links expiring after 60 minutes,
  request throttling, and existing-session invalidation after a password change.
- Clients access their own records.
- Coaches access clients with an active assignment covering the current date.
- Inactive accounts cannot sign in or continue accessing protected pages and APIs.
- Mesocycle creation and editing are restricted to assigned coaches.

### Exercise progress

The Exercise Progress section shows weight and rep charts for a selected exercise
across the latest 60 logged sessions, plus an accessible table of readings.

- Only completed sets with a recorded weight and at least one rep are included.
- Future sessions and planned or incomplete sets are excluded.
- Each session shows its heaviest set, highest rep count, number of included sets,
  and volume (the sum of weight multiplied by reps).
- Weight and rep records may come from different sets in the same session.
- Clients and their current coaches can view the charts.

### Contact enquiries

A public API accepts prospective-client enquiries with validation, required
privacy consent, a honeypot field, and a limit of five requests per minute per IP.

## Technology

| Layer          | Stack                                                                           |
| -------------- | ------------------------------------------------------------------------------- |
| Backend        | Laravel 13, PHP, Eloquent                                                       |
| Frontend       | Vue 3, TypeScript, Inertia.js 3                                                 |
| Styling        | Tailwind CSS 4 and custom CSS                                                   |
| Build tools    | Vite / Vite Plus                                                                |
| Database       | SQLite for local development; MySQL-compatible tracker schema                   |
| Quality checks | Pest, PHPStan / Larastan, Laravel Pint, Vue TypeScript and frontend lint checks |

## Local setup

Requirements: PHP 8.3 or newer with the relevant database driver, Composer, and
Node.js with npm compatible with the project's build tools.

Run the following from the Laravel application directory, `pt-tracker`:

```sh
composer install
npm ci
cp .env.example .env
php artisan key:generate
php -r "file_exists('database/database.sqlite') || copy('database/demo.sqlite', 'database/database.sqlite');"
php artisan migrate
npm run build
php artisan serve
```

The repository includes [`database/demo.sqlite`](database/demo.sqlite), a verified
SQLite database containing all migrations and development sample data. Setup
copies it to your private working database without overwriting an existing one.
You can inspect the demo file with any SQLite database viewer. Keep the tracked
demo file unchanged while running the website; local databases remain ignored.

Keep an existing
`.env` when updating an installed project. For a database previously created from
the supplied SQL, read [the migration notes](database/MIGRATIONS.md) before running
migrations.

Open **http://localhost:8000**.

| Page                      | URL          |
| ------------------------- | ------------ |
| Public website            | `/`          |
| Member login              | `/login`     |
| Client or coach dashboard | `/dashboard` |

For live frontend development, run `npm run dev` in a second terminal while the
Laravel server is running.

### Invitation and password reset emails

Set `APP_URL` to the address clients use to open Stage-Ready. Invitation and reset
links use that configured address. Invitations create client access immediately
on acceptance, without scheduling a coaching start or end date. Existing account
holders must sign in to their own account to accept; their password is unchanged.

Local development defaults to `MAIL_MAILER=log`, so emails appear in
`storage/logs/laravel.log` rather than an inbox. The invitation form also provides a
copyable link and reports whether mail delivery succeeded. For real delivery,
configure an SMTP or supported mail provider using `MAIL_MAILER`, `MAIL_HOST`,
`MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, and `MAIL_FROM_ADDRESS`. Test delivery
with your own account before inviting clients. Keep credentials in the local
environment file.

### Sample accounts

The included demo database already contains the accounts below. If you chose to
create an empty database instead, run migrations and load the sample data:

```sh
php artisan db:seed
```

The sample seeder requires its target tables to be empty. It includes coaches,
clients, assignments, check-ins, workouts, and related tracker records.

| Role   | Email                  | Password   |
| ------ | ---------------------- | ---------- |
| Coach  | `james@pttracker.test` | `password` |
| Client | `adam@example.com`     | `password` |

These are development-only accounts. Do not seed them into production.

## Project structure

```text
pt-tracker/
├── app/
│   ├── Http/Controllers/Api/    # Authentication and tracker endpoints
│   ├── Http/Requests/           # Request validation
│   ├── Models/                 # Eloquent models and relationships
│   ├── Policies/               # Coach/client access rules
│   └── Services/               # Mesocycle scheduling
├── database/
│   ├── migrations/             # Database schema and incremental changes
│   └── seeders/                # Development sample data
├── resources/
│   ├── js/components/          # Shared UI, mesocycle editor, trend chart
│   ├── js/pages/               # Public page, login, dashboard
│   ├── js/lib/                 # API client and image configuration
│   └── css/                    # Dark theme and responsive layouts
├── routes/                     # Website and API routes
├── tests/                      # Model, API and access-control tests
└── docs/API.md                 # Endpoint and implementation notes
```

### Photography and branding

The product name is **Stage-Ready** throughout the website. Project preferences
are recorded in the workspace's `AGENTS.md`.

Image imports are configured in
[`athlete-images.ts`](resources/js/lib/athlete-images.ts). The current photographs
are stored in `resources` and bundled by Vite. The shared image component uses
soft edge fades and background blending to avoid visible rectangular borders.

## Verification

```sh
php artisan test
php vendor/bin/pint --test
php vendor/bin/phpstan analyse --no-progress --memory-limit=512M
npm run types:check
npm run check
npm run build
```

Tests cover authentication, inactive accounts, record ownership, coach assignments,
model relationships, repeating schedules, preservation of logged history, exercise
limits, and rolling bodyweight calculations.

PHPStan checks the application, configuration, database scripts and seeders, and
routes. Run `composer ci:check` for the frontend checks, PHP formatting and static
analysis, and application tests. GitHub runs these checks and a production asset
build using the workflow in the repository root's `.github/workflows/` directory.

## Current scope

The working website includes authentication, coach/client dashboards, check-ins,
workout and set logging, mesocycles, and bodyweight trends.

Models and database tables also exist for meals, progress photos, training clips,
clip feedback, supplements, phases, cycle entries, and competitions. Their full
user interfaces and upload/management endpoints are not yet implemented.

Coach account provisioning still requires trusted application code. Coaches can
onboard clients through invitations and end coaching from the Clients section.
Public self-registration and an administration interface are not included. The
contact-enquiry API is available, but a public contact form is not yet connected.

## API documentation

See [API and implementation notes](docs/API.md) for endpoint details, CSRF handling,
request examples, validation rules, and record-replacement behaviour.

## Staging deployment

1. Configure `.env` using `.env.staging.example`, with a unique application key,
   the staging URL, and database credentials.
2. Run `composer install --no-dev --optimize-autoloader`, `npm ci`, and
   `npm run build` with development build dependencies available.
3. Run `php artisan migrate --force` and `php artisan optimize`.
4. Point the web server at `public/` and allow writes to `storage/` and
   `bootstrap/cache/`.
5. Configure the health check to request `/up` with `Accept: application/json`.

Keep populated environment files, credentials, and real client data out of the
repository.
