# Stage-Ready

**A training and progress platform for bodybuilding coaches and their clients.**

Stage-Ready brings repeating training plans, workout logging, daily check-ins, and
bodyweight trends into one workspace. Coaches create a repeating training split
with a start date and adjust upcoming sessions as a client's needs change.
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
- Build a split of 1–15 numbered training/rest days and choose its start date.
- Write a separate workout for every training day; the split repeats automatically.
- Edit exercises, sets, reps, weight, RIR, and coaching notes when plans need to change.

### Repeating training splits

A coach can create **Day 1 Push, Day 2 Pull, Day 3 Rest, Day 4 Legs**. Day 1 starts
on the selected date. Calendar days advance through the sequence, including
rest days, and wrap to Day 1 after the last day. A newer split takes over on its
own start date. Missed logins do not stop the calendar count.

- Up to **10 exercises per workout**.
- Splits can contain 1–15 days and repeat until the next split starts.
- Each training day has its own workout; rest days have no exercise entries.
- Today's scheduled workout or rest day appears automatically on login.
- The client can select a different split day for special circumstances. An extra
  rest day pauses the count; choosing a training or planned rest day makes the
  following date continue with the next split day. Skipping the last rest day
  and selecting Day 1 therefore makes tomorrow Day 2.
- Choices can be corrected before performance is logged. Earlier choices cannot
  shift later logged sessions, and client accounts cannot create or rewrite workouts.
- Upcoming unlogged sessions update when the split changes, including today's
  session if training has not been logged.
- Past sessions, completed workouts, sessions with logged sets, and sessions with
  training clips retain their existing records.
- Logging a session preserves it from later split changes, even if it is
  subsequently marked incomplete.

Plans support up to one hundred sets per exercise. Changing a split's start date
or length after recorded history requires a new split. Previous weekly mesocycles
and their logged sessions remain available; they are not rewritten into new splits.
Run `php artisan migrate` when upgrading an existing installation.

### Client workspace

- View assigned training splits and the correct scheduled workout or rest day.
- Log actual reps, weight, reps in reserve (**RIR**), and completion for each set.
- Mark workouts as completed.
- Choose a coach-prescribed split day or extra rest when the schedule changes.
- Create, edit, and delete personal daily check-ins.

### Meal plans, daily prescriptions and written reviews

- Coaches publish separate training-day and rest-day meal plans with independent
  meal times, foods/portions, and optional calories/macros. Copying training meals
  to rest days applies an adjustable delay (60 minutes by default).
- The client's scheduled workout suggests the day type, while both meal plans
  remain available to view. Plans can start today or on a future date.
- Daily prescriptions repeat Monday to Sunday: cardio type, minutes and intensity;
  rounds of mandatory poses and hold time per pose; vacuum sets and hold time.
- Coaches can schedule later plan versions with more posing rounds closer to the
  optional show date. Increases are explicitly prescribed, never automatic.
- Published plans retain their history. Future versions can be cancelled by their
  author. Today's changes create another version rather than rewriting old plans.
- Coaches publish written check-in feedback and next steps every seven days in
  prep, or every fourteen days in the off-season. Each coach sets the current phase;
  changing phase starts a new interval today. Review status appears on the overview.
- Reviews include a recent summary of recorded weights, steps, and completed
  workouts. Missing readings are excluded from averages. This summary is separate
  from the existing rolling seven-day bodyweight chart.
- Clients can read their plans and review history after coaching ends. Former
  coaches lose access; clients cannot write prescriptions or coach reviews.

Existing installations require `php artisan migrate` for these features.

### Weekly health monitoring

Coaches enable weekly monitoring per client in **Weekly health**, for clients
who need blood pressure and resting heart rate recorded during their cycle.
Clients may record systolic/diastolic pressure in mmHg, resting heart rate in
bpm, and notes. Every measurement is optional, including when correcting or
clearing an earlier reading. Missing measurements stay null and appear as not
recorded. The overview shows when a full reading is due: immediately when
monitoring starts, then every seven days after a current full reading. Blank or
partial entries stay visible to coaches without postponing that reminder.

Clients can correct their readings, and current coaches can view the dated
history. Disabling monitoring or ending coaching preserves readings. Monitoring
starts disabled and requires the weekly-health database migration on existing
installations (`php artisan migrate`).

### Daily check-ins

Optional nutrition responses include hunger and appetite (separate 1-5 ratings),
digestive discomfort (1 = none, 5 = severe), digestion notes, and dietary difficulties
such as trouble finishing meals, cravings, or food access. Clients can save these
at the end of the day using **Save end-of-day nutrition**, independently of posing
photos. Existing posing submissions and morning measurements are preserved.
The form shows whether the day has been completed and supports previous days.
Current coaches can read the responses in daily
history and the seven/fourteen-day review summary. Missing ratings are excluded
from averages, with response counts shown. Existing check-ins remain unchanged.

Clients can save or submit a partial or entirely blank check-in. Coaches are
responsible for agreeing expectations and following up on missed information.
A blank date uses today when creating a record, or preserves its original date
when editing. Each client can record one check-in per date, with optional:

| Training and recovery      | Daily habits |
| -------------------------- | ------------ |
| Bodyweight                 | Steps        |
| Sleep duration and quality | Water intake |
| Energy and stress ratings  | Notes        |

Sleep quality, energy, and stress use a 1–5 rating scale.

### Posing photos and daily coach reviews

Clients can save their daily numbers as a draft, then choose from the eight poses in the
[NPC men's bodybuilding pose set](https://npcnewsonline.com/official-bodybuilding-rules/).
The check-in also offers front relaxed, rear relaxed, left side relaxed and
right side relaxed, plus a **Favourite classic pose**: thirteen photo options.
Every photo is optional. Clients can submit with any number of photos, including
none, and coaches can review those submissions. Missing poses stay visible to
the coach, and the photo count includes every uploaded pose.

- Photos upload individually and stay private to the client and currently assigned
  coaches. They are not placed on the public storage disk.
- Clients can replace a pose photo or remove it before submitting again.
- Coaches record an overall posing score from 1 to 10 and written feedback.
- A coach can mark the review as a competition-prep check-in and optionally add
  separate 1-10 flatness and fullness scores. Flatness increases from not flat to
  very flat; fullness increases from low fullness to very full.
- Each coach has a separate review. Clients can read their feedback after coaching
  ends, while the former coach loses access to the client's photos.
- Changing a photo marks earlier reviews out of date and requires resubmission.
  A coach cannot save a review against a photo set that changed while being viewed.

Existing check-ins and their recorded numbers remain available. A saved draft
only becomes submitted when the client chooses to submit it; missing photos do
not block that action. Previously submitted photos and review history remain
available. Run `php artisan migrate` to allow partial weekly health readings on
existing installations; rolling back that migration leaves the columns nullable
so omitted measurements are preserved.

The browser prepares JPEG uploads at a maximum 2,000-pixel long edge to keep daily
uploads smaller. The server accepts JPEG, PNG, and WebP images up to 5 MB and
6,000 pixels in either dimension. For API uploads above PHP's default limit,
configure `upload_max_filesize` to at least `6M` and `post_max_size` to at least `8M`.

Photos use the private `checkin_photos` disk, under
`storage/app/private/check-in-photos` by default. Set `CHECKIN_PHOTOS_ROOT` to use
another private writable directory. Collaborators can use their own storage; a
Seagate drive is not required. Replaced and deleted pose files are cleaned up
after their database transaction commits; filesystem failures are retried through
the queue, so keep the queue worker running.

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
- Split creation, program changes and workout creation are restricted to assigned coaches.

### Exercise progress

The Exercise Progress section shows weight, rep and working-set charts for a selected exercise
across the latest 60 logged sessions, plus an accessible table of readings.

- Completed working sets with at least one rep are included, even without a recorded weight.
- Future sessions and planned or incomplete sets are excluded.
- Each session shows its heaviest set, highest rep count, and volume in working sets.
- Workout cards show planned and completed working-set counts across all exercises.
- All programmed sets are working sets. Missing weights remain unknown and do
  not appear as zero on the weight chart; their sets still count towards volume.
- Weight and rep records may come from different sets in the same session.
- Clients and their current coaches can view the charts.

Coaches use **Training analytics** to view weight over calendar time and compare
both weight and reps against daily calories or rolling seven-day average
bodyweight. Select an exercise and history range, then hover, tap or focus a chart
point to inspect the session. A readings table includes missing values and counts.

Calorie comparisons can use the training-day prescription effective on each
session date or food calories logged for that date. Logged food may cover only
part of a day; incomplete calorie entries are shown as subtotals and excluded
from the calorie plots. Bodyweight averages use that session date and the six
preceding days, with weigh-in counts and a comparison to seven days earlier.
Missing readings remain missing. Clients retain their existing Exercise Progress
view. These comparisons use existing records and do not require a migration.

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

Requirements: PHP 8.4.1 or newer with the relevant database driver, Composer, and
Node.js 22.18+ within the 22.x series, or Node.js 24.11+ with npm. The committed Composer
lock file includes packages that require PHP 8.4.1+, even though the framework's
base requirement is lower.

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

The `vite` dependency is an alias for the matching Vite+ core package. Keep its
version aligned with `vite-plus` when upgrading the build tools; the npm
override ensures plugins use that same copy.

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

To add richer samples to an existing local database without clearing its records, run:

```sh
php artisan db:seed --class=DevelopmentSampleSeeder
```

This adds ten fictional client scenarios across all 27 application tables, including
training splits, legacy programs, logged sets, meals, prescriptions, reviews,
invitations and enquiries. Related detail tables can contain more than ten rows.
The first sample client has ten days of check-ins for a useful bodyweight trend.
Framework tables (sessions, password resets, cache, queues and migrations) are
left alone. Repeating the command preserves records and edits without duplicating
the samples. It only runs in `local` or `testing` and never sends invitations or email.

Sample client emails are `sample.client01@stage-ready.test` through
`sample.client10@stage-ready.test`, with password `password`. The loader uses the
existing James demo coach if its public demo password still matches; otherwise it
creates `sample.coach@stage-ready.test` with the same demo password. Sample photos
and clips are labelled placeholders copied to private storage. Keep this data local;
the shared `demo.sqlite` and its original fixtures are unchanged.

The included demo database already contains the accounts below. If you chose to
create an empty database instead, run migrations and load the sample data:

```sh
php artisan db:seed
```

The sample seeder only runs in `local` and `testing`, and requires its target
tables to be empty. It includes coaches,
clients, assignments, check-ins, workouts, and related tracker records.

| Role   | Email                  | Password   |
| ------ | ---------------------- | ---------- |
| Coach  | `james@pttracker.test` | `password` |
| Client | `adam@example.com`     | `password` |

These credentials are public and only for local development. Do not deploy the
demo database or seed these accounts into staging or production. Start hosted
installations with a new, empty database. See the repository's
[security notes](../SECURITY.md) for public-repository and deployment checks.

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
composer validate --strict
composer audit --locked
npm audit
php vendor/bin/pint --test
php vendor/bin/phpstan analyse --no-progress --memory-limit=512M
npm run types:check
npm run check
npm run build
php database/verify-demo.php
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
private posing reviews, workout and set logging, mesocycles, bodyweight trends,
meal plans, daily practice prescriptions, weekly health monitoring and written
coach reviews.

Models and database tables also exist for individual meal logging, training clips, clip feedback,
supplements, phases, cycle entries, and competitions. Their full user interfaces
and upload/management endpoints are not yet implemented. Progress photos are
available through the daily posing check-in workflow.

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
