# Stage-Ready

**A training and progress platform for bodybuilding coaches and their clients.**

Stage-Ready connects repeating training plans, workout logging, daily check-ins,
and bodyweight trends in one responsive workspace. Coaches create a mesocycle
once and adjust upcoming training when a client's needs change. Clients follow
their schedule and record their progress.

## Features

- **Coach and client dashboards:** separate workflows with access restricted to
  the client's own records or the coach's current assigned clients.
- **Repeating mesocycles:** enter a training period and weekly workouts; dated
  sessions are generated automatically throughout the block.
- **Editable programming:** change exercises, sets, reps, weights, RIR, and notes
  for injuries or other adjustments while preserving past and logged sessions.
- **Workout logging:** clients record actual reps, weight, RIR, set completion,
  and workout completion. Each workout supports up to ten exercises.
- **Daily check-ins:** track bodyweight, sleep, energy, stress, steps, water, and notes.
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
interfaces and management endpoints are still to be built. Account provisioning
and coach/client assignments currently require trusted application code.

## Documentation

- [Setup, features, and project structure](pt-tracker/README.md)
- [API and implementation notes](pt-tracker/docs/API.md)
- [Database migration guidance](pt-tracker/database/MIGRATIONS.md)
- [Project preferences](AGENTS.md)
