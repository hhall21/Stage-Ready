# Stage-Ready

**A training and progress platform for bodybuilding coaches and their clients.**

Stage-Ready connects repeating training plans, workout logging, daily check-ins,
and bodyweight trends in one responsive workspace. Coaches create a mesocycle
once and adjust upcoming training when a client's needs change. Clients follow
their schedule and record their progress.

## Features

- **Coach and client dashboards:** separate workflows with access restricted to
  the client's own records or the coach's current assigned clients.
- **Client onboarding:** coaches invite clients by email for immediate access on
  acceptance, manage invitations, and end coaching while preserving client history.
- **Password reset:** emailed, expiring reset links with request throttling and
  existing-session invalidation.
- **Exercise progress:** weight and rep charts from completed logged sets, with
  session readings and training volume.
- **Repeating mesocycles:** enter a training period and weekly workouts; dated
  sessions are generated automatically throughout the block.
- **Editable programming:** change exercises, sets, reps, weights, RIR, and notes
  for injuries or other adjustments while preserving past and logged sessions.
- **Workout logging:** clients record actual reps, weight, RIR, set completion,
  and workout completion. Each workout supports up to ten exercises.
- **Daily check-ins:** track bodyweight, sleep, energy, stress, steps, water, and notes.
- **Posing check-ins:** submit all eight men's bodybuilding poses privately, then
  receive coach ratings and feedback, with flatness and fullness scores during prep.
- **Rolling bodyweight averages:** a seven-day average based on recorded
  weigh-ins, reading counts, comparison with seven days earlier, and a 56-day chart.
- **Protected accounts:** session authentication, CSRF protection, login rate
  limiting, role-based access, and inactive-account blocking.
- **Contact enquiries:** a validated public API with privacy consent and spam controls.
- **Dark visual design:** white typography and borderless bodybuilding
  photography that blends into the page, with desktop and mobile layouts.

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

## Current scope

Authentication, dashboards, check-ins, workouts, set logging, mesocycles, and
bodyweight trends are implemented.

Database models also cover nutrition, progress photos, training clips and
feedback, supplements, phases, cycle entries, and competitions. Their full
interfaces and management endpoints are still to be built. Coach account
provisioning currently requires trusted application code; client onboarding and
coaching management are available in the coach dashboard.

## Documentation

- [Setup, features, and project structure](pt-tracker/README.md)
- [API and implementation notes](pt-tracker/docs/API.md)
- [Handover PDF design and notes pagination](pt-tracker/docs/handover/README.md)
- [Database migration guidance](pt-tracker/database/MIGRATIONS.md)
- [Project preferences](AGENTS.md)
