# Stage-Ready

**A training and progress platform for bodybuilding coaches and their clients.**

Stage-Ready connects repeating training plans, workout logging, daily check-ins,
and bodyweight trends in one responsive workspace. Coaches create a repeating split
of up to 15 training/rest days and adjust upcoming training when a client's needs change. Clients follow
their schedule and record their progress.

## Features

- **Meal plans:** separate training/rest-day foods and timings, with adjustable
  later rest-day meals and dated plan versions.
- **Daily prescriptions:** coach-set cardio, mandatory posing rounds and vacuum
  practice, including scheduled increases nearer a show.
- **Written coach reviews:** weekly in prep and fortnightly in the off-season,
  with due dates, recent check-in summaries, feedback and next steps.

- **Coach and client dashboards:** separate workflows with access restricted to
  the client's own records or the coach's current assigned clients.
- **Client onboarding:** coaches invite clients by email for immediate access on
  acceptance, manage invitations, and end coaching while preserving client history.
- **Password reset:** emailed, expiring reset links with request throttling and
  existing-session invalidation.
- **Training analytics:** coach weight-over-time charts and weight/rep comparisons
  against daily calories and rolling seven-day bodyweight averages. Clients retain
  exercise progress charts from completed logged sets, with
  session readings and training volume measured in working sets.
- **Repeating training splits:** coaches define 1–15 training/rest days and a
  start date. The correct workout appears automatically and the count wraps.
- **Flexible scheduling:** clients can select a different prescribed split day;
  extra rest pauses the split and other choices recalibrate following days.
- **Editable programming:** change exercises, sets, reps, weights, RIR, and notes
  for injuries or other adjustments while preserving past and logged sessions.
- **Workout logging:** clients record actual reps, weight, RIR, set completion,
  and workout completion. Clients cannot add workouts or rewrite their coach's
  plan. Each workout supports up to ten exercises.
- **Daily check-ins:** optionally track bodyweight, sleep, energy, stress, steps,
  water, and notes. Clients can leave anything blank; coaches handle follow-up.
- **Posing check-ins:** submit eight bodybuilding poses plus four relaxed views
  privately, plus a favourite classic pose. Every photo is optional; clients can
  submit with some or no photos and
  receive coach ratings and feedback, with flatness and fullness scores during prep.
- **Rolling bodyweight averages:** a seven-day average based on recorded
  weigh-ins, reading counts, comparison with seven days earlier, and a 56-day chart.
- **Protected accounts:** session authentication, CSRF protection, login rate
  limiting, role-based access, and inactive-account blocking.
- **Contact enquiries:** a validated public API with privacy consent and spam controls.
- **Dark visual design:** white typography and original dark
  bodybuilding illustrations, with desktop and mobile layouts.

## Technology

Laravel 13 · PHP · Eloquent · Vue 3 · TypeScript · Inertia.js 3 · Tailwind CSS 4 ·
Vite / Vite Plus · SQLite / MySQL

Quality checks use Pest, PHPStan / Larastan, Laravel Pint, frontend linting, and
Vue TypeScript checks.

## Getting started

The Laravel application lives in [`pt-tracker/`](pt-tracker/).

See the [application README](pt-tracker/README.md) for requirements, installation,
sample accounts, development commands, testing, and staging deployment.

After local setup, the website is available at `http://localhost:8000`, with
member login at `/login` and the authenticated workspace at `/dashboard`.

### Try the local demo

After following the setup guide, use these fictional sample accounts at `/login`:

| Role   | Email                  | Password   |
| ------ | ---------------------- | ---------- |
| Coach  | `james@pttracker.test` | `password` |
| Client | `adam@example.com`     | `password` |

These credentials are public and only for the local demo. The sample seeder is
blocked outside `local` and `testing`. Start staging and production with an empty
database and create real accounts with unique passwords; do not deploy the demo
database.

## Current scope

Authentication, dashboards, check-ins, private posing reviews, workouts, set
logging, mesocycles, bodyweight trends, meal plans, daily practice prescriptions,
weekly health monitoring and written coach reviews are implemented.

Database models also cover individual meal logging, training clips and feedback,
supplements, phases, cycle entries, and competitions. Their full interfaces and
management endpoints are still to be built. Coach account
provisioning currently requires trusted application code; client onboarding and
coaching management are available in the coach dashboard.

## Documentation

- [Setup, features, and project structure](pt-tracker/README.md)
- [API and implementation notes](pt-tracker/docs/API.md)
- [Training analytics walkthrough](pt-tracker/docs/TRAINING_ANALYTICS.md)
- [Handover PDF design and notes pagination](pt-tracker/docs/handover/README.md)
- [Database migration guidance](pt-tracker/database/MIGRATIONS.md)
- [Project preferences](AGENTS.md)
- [Security and public repository preparation](SECURITY.md)
