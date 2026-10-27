# Stage-Ready

**A training and progress platform for bodybuilding and powerlifting coaches and their clients.**

Stage-Ready gives coaches and clients one place to plan training, log sessions,
and keep track of progress. Coaches set the training split, meals, cardio and
posing practice. Clients can see what's planned for the day, record how it went,
and check in with their coach.

It works on desktop and mobile, with a dark layout, white text and bodybuilding
artwork that blends into the page. A top-right switch offers an inverted light
mode with softer grey backgrounds. Your browser remembers the choice.

## Recent updates

- **Payment page preview:** an initial layout at `/payment` reviews the plan and
  previews the handoff to Stripe Checkout. Prices and billing periods remain
  unset, and the preview does not take payments.
- **Light mode:** everyone can switch between dark and inverted light mode from
  the top-right corner, including before signing in. The choice survives page
  changes and reloads, and applies to images, charts and forms too. Its muted grey
  backgrounds reduce glare across the website. Deep blue outlines and stronger
  glow cores give cards, panels and controls clear contrast in light mode.
  The homepage's powerlifter plates stay red and its bodybuilder lighting stays
  golden in light mode.
- **Clearer pages and navigation:** the homepage describes the coaching tools
  directly, public navigation stays available on phones, and the dashboard puts
  scheduled training before supporting records. Sign-in and dashboard headings
  use task names, with fewer decorative elements and larger controls.
  Coach and client dashboards use larger text and more space between fields,
  supporting notes and buttons, including workout logging on phones.
  Dropdowns share blue arrow highlights and opening effects, and homepage artwork
  now holds in view on phones when it fits below the header.
  Navigation, buttons, links, form fields and chart points share blue hover and
  keyboard-focus highlights in both themes.
  Homepage feature cards, preparation panels and workflow accents also stay blue
  in both themes.
- **Coaching handovers:** ending coaching creates a private, dated PDF with
  recorded bodyweight, training bests and optional final coach notes. Former
  clients without an active coach see only Coaching handovers when signed in.
  Their account and history stay saved, and further coaching entries are blocked.
  Run `composer install` and `php artisan migrate` in `pt-tracker/` when updating.
- **Coaching disciplines:** each coach has a fixed Bodybuilding or Powerlifting
  discipline. Check-in photos and posing reviews are available to bodybuilding
  coaches and clients with an active bodybuilding coach. Existing photos stay
  stored when discipline changes. Run `php artisan migrate` when updating.
- **A homepage for coaches:** the public website introduces the platform's
  coaching features, competition-prep tools and client workflow, with practical
  FAQs and links to member sign-in.
- **More context for training progress:** coaches can compare exercise weight and
  reps with prescribed or logged calories and rolling bodyweight averages.
  Charts include reading counts and keep missing values visible.
- **More focused dashboard sections:** each tab loads its own records, with
  headings and actions tailored to coaches or clients.
- **Fuller local demos:** optional sample loaders add ten fictional client
  scenarios and six weeks of graph history, including workouts, weigh-ins and
  food logs. See [sample accounts](pt-tracker/README.md#sample-accounts).
- **Artwork and setup updates:** original dark illustrations replace the earlier
  photographs. The build tools have been updated, and PHP 8.4.1 is now the
  documented minimum. Existing installations should run `php artisan migrate`
  from `pt-tracker/` to pick up the latest database changes.

## Features

- **Coach and client dashboards:** each role has its own workspace. Clients see
  their own records; coaches see their currently assigned clients.
- **Client onboarding:** coaches invite clients by email for immediate access on
  acceptance, manage invitations, and end coaching with a downloadable client
  handover while preserving their account and history.
- **Competition dates:** coaches add and manage shows in the Competitions tab,
  filter by client and by All, Upcoming or Past, and view dates grouped by client.
  Only clients with a matching competition appear. Placing and show notes become
  available after the show date.
- **Repeating training splits:** coaches define 1–15 training/rest days and a
  start date. The day's workout appears automatically, and the split repeats.
- **Flexible scheduling:** clients can select a different prescribed split day;
  extra rest pauses the split, while choosing a split day makes tomorrow continue
  from the following day in the plan.
- **Editable programming:** change exercises, sets, reps, weights, RIR, and notes
  for injuries or other adjustments while preserving past and logged sessions.
- **Workout logging:** clients record actual reps, weight, RIR, set completion,
  and workout completion. Clients cannot add workouts or rewrite their coach's
  plan. Each workout supports up to ten exercises.
- **Training analytics:** coaches can explore exercise weight over time and
  compare weight or reps with calories and bodyweight trends. Clients have their
  own exercise progress charts. Training volume is measured in working sets,
  including completed bodyweight sets with positive reps and no recorded load.
- **Daily check-ins:** optionally track bodyweight, sleep, energy, stress, steps,
  water, and notes. Clients can leave anything blank; coaches handle follow-up.
- **End-of-day nutrition:** optional hunger, appetite and digestive discomfort
  ratings, digestion notes and dietary difficulties. Saving these responses
  preserves the day's measurements and posing submission.
- **Bodybuilding posing check-ins:** thirteen private photo options: eight bodybuilding poses,
  four relaxed views and a favourite classic pose. All photos are optional.
  Coaches give a 1–10 rating and feedback, with optional flatness and fullness
  scores during prep.
- **Weekly health:** coaches can enable blood pressure and resting heart rate
  reminders. Clients can save partial readings, and missing measurements stay missing.
- **Rolling bodyweight averages:** a seven-day average based on recorded
  weigh-ins, reading counts, comparison with seven days earlier, and a 56-day chart.
- **Meal plans:** separate training-day and rest-day foods and meal times, with
  dated plan versions. Copying meals to rest days adds an adjustable delay of
  60 minutes by default.
- **Daily prescriptions:** coach-set cardio, mandatory posing rounds and vacuum
  practice, including planned increases nearer a show.
- **Written coach reviews:** weekly in prep and fortnightly in the off-season,
  with due dates, check-in summaries, feedback and next steps. Published plans
  and reviews remain stored after coaching ends.
- **Protected accounts:** session authentication, CSRF protection, login rate
  limiting, role-based access, inactive-account blocking and emailed password resets.
- **Contact enquiries:** a validated public API with privacy consent and spam controls.

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

For more chart points, use the optional
[sample loaders](pt-tracker/README.md#sample-accounts) to add fictional clients and
past training data without clearing existing local records.

These credentials are public and only for the local demo. The sample seeder is
blocked outside `local` and `testing`. Start staging and production with an empty
database and create real accounts with unique passwords; do not deploy the demo
database.

## Current scope

The training, check-in and coaching features above are available in the website,
alongside legacy weekly programs and their logged history.

Database models also cover individual meal logging, training clips and feedback,
supplements, phases, and cycle entries. Their full interfaces and
management endpoints are still to be built. There is also a standalone
[handover PDF design preview](pt-tracker/docs/handover/README.md), which uses
fictional data. The application generates personal handovers when coaching ends.

Creating coach accounts still requires trusted application code. Coaches can
invite clients and manage coaching from their dashboard. Public self-registration
and an admin interface are not included, and the contact API does not yet have a
connected public form.

## Documentation

- [Setup, features, and project structure](pt-tracker/README.md)
- [API and implementation notes](pt-tracker/docs/API.md)
- [Training analytics walkthrough](pt-tracker/docs/TRAINING_ANALYTICS.md)
- [Handover PDF design and notes pagination](pt-tracker/docs/handover/README.md)
- [Database migration guidance](pt-tracker/database/MIGRATIONS.md)
- [Project preferences](AGENTS.md)
- [Security and public repository preparation](SECURITY.md)
