# Stage-Ready API and implementation notes

Laravel backend and website for Stage-Ready.

## Brand name

The website is called **Stage-Ready**. Always use Stage-Ready in website copy,
navigation, page titles, and branding. Do not call the website PT Tracker.

## Public API

`POST /api/contact-enquiries` accepts prospective-client enquiries. It is limited to five requests per minute per IP address.

```json
{
    "name": "Jamie Taylor",
    "email": "jamie@example.com",
    "phone": "+44 7700 900123",
    "service": "Personal training",
    "message": "I would like to arrange an introductory consultation.",
    "privacy_consent": true
}
```

Use a visually hidden `website` input as a honeypot; legitimate submissions must leave it empty.

## Tracker API

Tracker endpoints use the website's session cookie and CSRF protection. From the
same origin, call `GET /api/csrf-token` first, retain the session cookie, and send
the returned token as `X-CSRF-TOKEN` on POST, PUT, PATCH and DELETE requests. Send
`Accept: application/json`. Refresh the token after login, logout, accepting an
invitation, or completing a password reset.

- `POST /api/login`: email and password; limited to five attempts per minute per email/IP.
- `GET /api/user`: signed-in user's ID, name, email and role.
- `GET /api/clients`: coaches' current active assigned clients, paginated.
- `POST /api/logout`: ends the session.
- `GET /api/exercises`: paginated exercise catalogue.
- `GET /api/clients/{client}/daily-check-ins` and `/workouts`: paginated lists.
- `POST` to those collection URLs creates a record.
- `GET`, `PATCH` or `DELETE` the collection URL plus `/{recordId}` reads, updates or deletes a record.

Clients can manage their own check-ins and workouts. Coaches can view check-ins
and manage workouts for clients with an active assignment whose start/end dates
include today. Inactive accounts and clients cannot access tracker records.
Coach accounts and user roles require trusted application code. Client onboarding
and ending coaching are available through the endpoints below; public
self-registration is not provided.

Check-ins require `check_in_date` (YYYY-MM-DD), unique per client. Optional fields:
`bodyweight`, `sleep_hours`, `sleep_quality`, `energy_level`, `stress_level`,
`steps`, `water_litres` and `notes`. Quality/energy/stress ratings use 1–5.

Workouts require `workout_name` and `workout_date` (YYYY-MM-DD). Optional fields:
`notes`, `completed`, and `exercises`, an array of at most ten entries:

```json
{
    "workout_name": "Push day",
    "workout_date": "2026-10-08",
    "exercises": [
        {
            "exercise_id": 1,
            "notes": "Controlled reps",
            "sets": [{ "reps": 8, "weight": 60, "rir": 2, "completed": false }]
        }
    ]
}
```

Exercise and set positions are numbered in submitted order. PATCH preserves
omitted fields. Providing `exercises` replaces the whole exercise/set list in a
transaction; an empty array clears it. Replacement deletes old set records and
unlinks any training clips attached to those sets. Client/trainer IDs cannot be
overridden in request bodies. A coach creating a workout is recorded as its trainer.
Deleting check-ins/workouts cascades to their dependent database records.
Deleting a check-in also cleans up its named-pose photo files after commit;
cleanup of unrelated legacy progress-photo paths or training-clip files is outside
these endpoints.

## Nutrition and practice prescriptions

Under `/api/clients/{client}`:

- `GET /nutrition-plans?date=YYYY-MM-DD&page=1` and `GET /practice-plans?...`
  return `date`, workout-derived `day_type` (`training`/`rest`), ISO `weekday`,
  `current` (latest uncancelled plan effective on that date, or null), and
  `versions` (12 per page). Same-day ties use the latest plan ID.
- `POST /nutrition-plans`: assigned coach only. Requires `effective_from`
  (today or later), `training_meals` and `rest_meals`, each with 1-12 meals.
  Each meal requires `name`, `time` (HH:mm), and `foods` (food amounts and
  preparation instructions). Optional numeric `calories`, `protein`,
  `carbohydrates`, and `fat` are nonnegative; macros use grams. Optional plan
  `notes` supports 5,000 characters. Food logging tables are not changed.
- `POST /practice-plans`: assigned coach only. Requires `effective_from`,
  optional `show_date` on/after that date, optional `notes`, and exactly seven
  `days`. Each ISO weekday 1-7 appears exactly once. Each day has `cardio_mode`,
  integer `cardio_minutes` (0-300), optional `cardio_intensity`, integer
  `posing_rounds` (0-100), `pose_hold_seconds` (1-300), `vacuum_sets` (0-100),
  `vacuum_hold_seconds` (1-300), and optional `notes`. Zero minutes/rounds/sets
  means no prescription for that activity. One posing round covers all mandatory
  poses. Dated versions support planned increases as the show approaches.
- `DELETE /nutrition-plans/{plan}` or `/practice-plans/{plan}` cancels a future
  version authored by the current assigned coach. Active/historical versions
  cannot be cancelled or edited. Publish a new version for changes. Cancelled
  versions remain in history and are excluded when choosing the effective plan.

Client and coach IDs cannot be set in request bodies. Current assigned coaches
may read their client's plan history; the client retains read access after coaching
ends. Dates and totals are descriptive; the app does not generate diet prescriptions.

## Written coaching reviews

- `GET /api/clients/{client}/coaching-reviews?page=1`: returns active-coach
  `schedules`, recent `summary`, and paginated `reviews` with author names.
- `PATCH .../coaching-reviews/schedule`: assigned coach only; `{ "phase": "prep" }`
  or `{ "phase": "offseason" }`. Each coach controls their own schedule. Prep
  has a seven-day interval; off-season has fourteen. Changing phase starts a new
  interval today; saving an already-initialised unchanged phase preserves its anchor.
- `POST .../coaching-reviews`: assigned coach only; requires `review_date`
  (not future or before the current phase began), written `feedback` (max 10,000),
  and optional `next_steps` (max 5,000). One review per coach/client/date. Author
  and phase are server-assigned. Reviews are published records, not editable drafts.

The next due date is the latest review date in the current phase plus the interval,
or the phase/assignment start plus that interval when no review exists. Backdated
reviews cannot move a later due date backwards. Summary averages exclude missing
values and use the seven/fourteen calendar days ending today. Clients with any
active prep coach see a seven-day summary. Historical reviews remain accessible
to the client after coaching ends, while former coaches lose access.

## Weekly health monitoring

Routes under `/api/clients/{client}/weekly-health`:

- `GET /`: monitoring status, due date, `is_due`, current coach's `coach_enabled`
  (null for clients), and `readings` paginated by 12, newest measurement date first.
- `PATCH /settings`: current assigned coach only; body `{ "enabled": true }` or
  `{ "enabled": false }`. Each coach controls their own assignment's requirement.
- `POST /readings`: client only, while an active coach has enabled monitoring.
- `PUT /readings/{reading}`: owning client only; corrects an existing reading,
  including after monitoring or coaching ends.

Readings require `reading_date` (YYYY-MM-DD, not in the future), integer `systolic`
(1-400 mmHg), `diastolic` (1-300 mmHg), and `resting_heart_rate` (1-300 bpm).
Systolic must exceed diastolic. Optional `notes` allows 5,000 characters. These
are storage validation bounds, not clinical thresholds. One reading per client
per date; duplicates return 422. Client IDs cannot be supplied in the body.

Monitoring is due immediately when enabled, then seven days after the latest
reading on or after the monitoring start date. Older backdated entries do not
postpone the due date. All active monitoring assignments contribute to the client's
requirement. Disabling one assignment does not disable another coach's requirement.
Disabled monitoring has no due date. Previous readings are preserved, and former
coaches lose access when coaching ends. There are no drug/dose recommendations or
automated clinical classifications in this feature.

## Daily posing check-ins

Saving daily numbers creates or edits a draft. Submission requires all eight
named poses in the [NPC men's bodybuilding rules](https://npcnewsonline.com/official-bodybuilding-rules/).
The pose catalogue is returned in `required_poses`; use its stable keys for uploads.

The following routes extend
`/api/clients/{client}/daily-check-ins/{checkIn}`:

| Method | Suffix                  | Purpose                                                                 |
| ------ | ----------------------- | ----------------------------------------------------------------------- |
| GET    | `/posing`               | Photo set, required poses, submission state and coach reviews           |
| POST   | `/posing/photos/{pose}` | Upload or replace one pose, using multipart `photo` and `photo_version` |
| DELETE | `/posing/photos/{pose}` | Remove one pose, with JSON `photo_version`                              |
| POST   | `/posing/submit`        | Submit all eight photos, with JSON `photo_version`                      |
| PUT    | `/posing/review`        | Save the signed-in coach's review of the submitted photo version        |

All responses wrap the posing snapshot in `data`. It contains `check_in_id`,
`photo_version`, `submitted_at`, `required_poses`, `photos`, and `reviews`. Each
photo includes its ID, `pose_key`, authenticated `url`, and `updated_at`; private
filesystem paths are never returned. The main check-in list also includes
`photo_version`, `submitted_at`, and `pose_photos_count` for submission status.

`GET /api/daily-check-in-photos/{photo}` serves a private image only to its owner
or a currently assigned coach. It requires the same authenticated session and
active-account checks as the other tracker endpoints, and is served with private,
non-storing cache headers. Do not copy these files into public storage.

Only the client can upload, remove, or submit photos. Uploads accept JPEG, PNG, or
WebP images, at most 5 MB and 6,000 pixels in either dimension. One image is sent
per request. Submit the current integer `photo_version` with every mutation;
stale versions receive HTTP 409. Photo changes and check-in date changes increment
the version and clear submission. Existing reviews remain visible but are marked
out of date; unchanged metrics edits do not invalidate a posing review.

Only a currently assigned coach can review a complete, submitted set. The review
body requires `photo_version`, integer `posing_rating` (1-10), written `feedback`
(up to 10,000 characters), and boolean `is_competition_prep`. Optional
`flatness_rating` and `fullness_rating` accept integers 1-10 during prep; send
`null` or omit them outside prep. The scale directions are explicit in the UI:
10 means very flat for flatness, and very full for fullness. These are separate
coach assessments, not automated scores.

Each coach can update only their own review. Reviews include `coach_id`,
`coach_name`, scores, feedback, `photo_version`, `reviewed_at`, and `is_current`.
They remain readable by the client after coaching ends. A client cannot submit
coach feedback or score fields through the metrics endpoint.

The migration preserves legacy front/side/back/other progress-photo records.
Only new, named-pose photos count towards the eight-photo submission requirement.
Failed filesystem cleanup is retried through the application's queue.

## Client onboarding and coaching management

Coaches invite clients using only their name and email. Coaching starts immediately
on acceptance; no assignment date inputs or upcoming-client workflow is required.
Existing dated assignments remain compatible. Ending coaching removes the coach's
access and preserves the client's account and all training/check-in records.

| Method and path                            | Behaviour                                                                                              |
| ------------------------------------------ | ------------------------------------------------------------------------------------------------------ |
| `GET /api/client-invitations`              | Coach's unaccepted, uncancelled invitations, paginated.                                                |
| `POST /api/client-invitations`             | Invite by `name` and `email`. Returns `accept_url` and mail `delivery` (`sent`, `local`, or `failed`). |
| `POST /api/client-invitations/{id}/resend` | Rotate the token and send a new invitation.                                                            |
| `DELETE /api/client-invitations/{id}`      | Revoke an invitation belonging to the coach.                                                           |
| `POST /api/invitations/{token}/accept`     | Accept a seven-day, single-use invitation.                                                             |
| `DELETE /api/clients/{client}/coaching`    | End the signed-in coach's current assignment without deleting records.                                 |

Invitation tokens are stored as hashes and never included in invitation lists.
The generated link opens `/join/{token}`. New clients submit `password` and
`password_confirmation`; their email, name, and client role come from the
invitation. Existing clients must sign in to the invited account before accepting;
acceptance does not change their password. Invitations are limited to ten
creations/resends per minute per coach; joining is limited to twenty requests per
minute per IP. SMTP or another supported mail provider must be configured for real
email delivery; local development logs emails and offers copyable invitation links.

## Password reset

- `POST /api/forgot-password` takes `email` and returns the same message for
  active, inactive, or unknown accounts. Active users receive a single-use reset
  link; repeat mail requests are throttled by Laravel's password broker.
- `POST /api/reset-password` takes `email`, `token`, `password`, and
  `password_confirmation`. Tokens expire after 60 minutes. Passwords require at
  least twelve characters, uppercase and lowercase letters, and a number.
- Both endpoints are limited to five requests per minute per IP. Password changes
  rotate remember tokens and invalidate old authenticated sessions on their next
  protected request. Inactive accounts cannot reset their passwords.
- `/forgot-password` requests a link, and `/reset-password/{token}?email=...`
  chooses the new password. Links use `APP_URL`, not the incoming request host.

## Exercise progress

`GET /api/clients/{client}/exercise-progress?exercise_id={id}` returns an exercise
selector (`exercises`), the selected `exercise_id`, and `data` containing up to 60
recent sessions in chronological order. The exercise parameter is optional.

Each point includes `workout_id`, `date`, `workout_name`, `max_weight_kg`,
`max_reps`, `logged_sets`, and `volume_kg`. Only completed sets with non-null weight
and positive reps from sessions dated today or earlier are included. Maximum
weight and reps are independent records; volume sums weight multiplied by reps
over the included sets. The client's own access and current-coach access use the
same policy as other tracker records.

## Mesocycles and bodyweight trends

Coaches create/edit mesocycles in the dashboard's Workouts tab. Enter a name,
start date and duration (default eight weeks), or a custom end date, then add
workouts with recurring weekdays, up to ten exercises each, and prescribed sets.
The backend creates dated sessions for every matching weekday in the period.
Dates cannot overlap another mesocycle for that client. Plans are limited to
one year, fourteen weekly workout templates, and one hundred sets per exercise.

Editing a mesocycle updates future unlogged sessions only. Past sessions,
completed workouts, logged sets, and sessions with training
clips remain intact. Today's unlogged sessions can also be updated for an injury
before training begins. Clients log their actual reps, weight, RIR and set
completion in their scheduled workouts. Logging makes the session a preserved snapshot;
marking a workout incomplete later does not erase that protection. Coach-only
mesocycle changes do not grant clients permission to edit the repeating plan.

- `GET /api/clients/{client}/mesocycles`: permitted clients/coaches can read plans.
- `POST /api/clients/{client}/mesocycles`: assigned coaches create a plan.
- `PUT /api/clients/{client}/mesocycles/{id}`: assigned coaches submit the full revised plan.
- `PATCH /api/clients/{client}/workouts/{workout}/sets/{set}`: clients log their own sets.
- `GET /api/clients/{client}/bodyweight-trend`: returns 56 daily rolling averages.

Each bodyweight average uses recorded weights from that date and the six days
before it. Null/missing weigh-ins and future-dated check-ins are excluded.
Responses include reading counts and the difference from the rolling average
seven days earlier. Empty windows have null averages and appear as chart gaps.
A lower average is shown as a decrease; the graph lets coaches assess whether
that decrease is consistent instead of treating a single reading as a trend.

Run `php artisan migrate` on an already-baselined database to add the mesocycle
tables and workout linkage. This incremental migration preserves existing data.

## Website pages and photographs

The public page is at `/`, member login at `/login`, and the authenticated
client/coach workspace at `/dashboard`. The interface uses dark backgrounds and
white text on desktop and mobile. Check-ins and workouts connect to the tracker
API; coaches select their assigned client in the workspace.

Five bodybuilding photographs from `resources` are imported through
`resources/js/lib/athlete-images.ts` and published by Vite. Portraits appear on
the home and login pages, the wide back photograph appears on the dashboard,
and two additional poses appear in the home-page gallery. The page and form
backgrounds share a near-black shade; image blending and soft edge fades remove
visible rectangular boundaries while preserving the full poses.

## Staging deployment

1. Copy `.env.staging.example` to the deployment environment as `.env`, set a unique `APP_KEY`, database credentials, and the staging URL.
2. Run `composer install --no-dev --optimize-autoloader`, `npm ci`, and `npm run build`.
3. Run `php artisan migrate --force` and `php artisan optimize`.
4. Configure the platform health check to request `/up` with `Accept: application/json`.

Never commit the populated `.env` file or production/staging credentials.
