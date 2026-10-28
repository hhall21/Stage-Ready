# Stage-Ready API and implementation notes

Laravel backend and website for Stage-Ready.

## Brand name

The website is called **Stage-Ready**. Always use Stage-Ready in website copy,
navigation, page titles, and branding. Do not call the website PT Tracker.

## Request limits

All requests reaching Laravel have a baseline limit of **600 per minute per IP**,
before sessions, authentication or controller work. Normal pages and API routes
also share a limit of **180 requests per minute per signed-in account**, or per IP
for guests. Within that budget, writes share **60 requests per minute**. Changing
URLs or opening another tab does not reset these counters. Different signed-in
accounts have separate application budgets, while sharing the outer IP cap.

Additional limits apply before the relevant controller executes:

| Action                                                     | Limit                                         | Scope                                                    |
| ---------------------------------------------------------- | --------------------------------------------- | -------------------------------------------------------- |
| Login                                                      | 5/minute per email/IP and 20/minute per IP    | All login attempts                                       |
| Request password reset email                               | 5/minute and 20/hour per IP; 5/hour per email | Email is normalised and hashed; shared across IPs        |
| Submit new password                                        | 5/minute per IP                               | Separate from reset email requests                       |
| Coach registration                                         | 5/minute and 20/hour per IP                   | Public signup                                            |
| Verify email or resend verification                        | 6/minute and 20/hour per account              | Both verification routes                                 |
| Create or resend invitations and administrator setup links | 10/minute and 60/hour per account             | Shared email-delivery budget                             |
| Open or accept an invitation                               | 20/minute per IP                              | Shared invitation budget                                 |
| Contact enquiry                                            | 5/minute per IP                               | Public contact API                                       |
| Billing changes                                            | 10/minute per account                         | Checkout, confirmation, portal and cancellation          |
| Photo uploads                                              | 20/minute per account                         | Shared across clients and poses; invalid uploads count   |
| Photo views, downloads and retention choices               | 60/minute per account                         | Shared across all private photos                         |
| Handover creation and document requests                    | 20/minute per account                         | End coaching, PDF retrieval and temporary-report opening |
| Progress analytics                                         | 30/minute per account                         | Exercise progress and bodyweight trends                  |

Limits return HTTP `429` with `Retry-After` and rate-limit headers. The API client
shows the waiting time; the forgot-password form also disables submission until
that time expires. These controls are enforced on the server even after a reload.
Blocked requests do not send mail, call Stripe, store uploads or mutate coaching
records. A throttled handover-open request does not consume the report.

Stripe webhooks have a separate **600 requests per minute per IP** transport
budget, rather than sharing browser/account counters. Every delivery still needs
a valid Stripe signature and matching environment before billing work runs.

Limits use Laravel's configured cache. Use a persistent shared database or Redis
store across workers and application instances; `array` is for tests only.
Configure trusted proxy addresses on the host when using a reverse proxy, and do
not trust arbitrary forwarded IP headers. Web-server or proxy limits complement
these controls by rejecting floods before PHP runs. Static assets served directly
by the web server do not pass through Laravel.

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
- `GET /api/user`: signed-in user's ID, name, email, role, fixed
  `is_bodybuilding_coach` flag, `is_admin`, `has_active_coaching` and `has_coaching_handovers`.
- `GET /api/clients`: coaches' current active assigned clients, paginated.
- `POST /api/logout`: ends the session.
- `GET /api/exercises`: paginated exercise catalogue.
- `GET /api/clients/{client}/daily-check-ins` and `/workouts`: paginated lists.
- `POST` to those collection URLs creates a record.
- `GET`, `PATCH` or `DELETE` the collection URL plus `/{recordId}` reads, updates or deletes a record.

Clients can manage their own check-ins and workouts. Coaches can view check-ins
and manage workouts for clients with an active assignment whose start/end dates
include today. Inactive accounts and clients cannot access tracker records.
Coaches can register publicly, verify their email and subscribe for £6 GBP per
month for powerlifting or £8 GBP per month for bodybuilding, with automatic renewal.
User roles and administrator privileges cannot be
changed through account requests. A coach's discipline is fixed during signup or
trusted provisioning. See [billing, access expiry and retention](BILLING.md) for
endpoints, required Stripe settings and the seven-day deletion policy.

### Administrator portal

`/admin` and every `/api/admin/*` endpoint require an authenticated, active
account with `is_admin = true`, in addition to the normal session and CSRF checks.
This flag does not grant access to private tracker records or photos; existing
coach/client authorization still applies. Bootstrap access using
`php artisan stage-ready:make-admin owner@example.com --name="Your name"` on a
trusted application host. New accounts receive a password setup link in terminal
output, not an email. Existing active coach passwords are preserved.

- `GET /api/admin/users?search=&role=&status=&page=1` returns `users` (20 per
  page) and `summary` counts. `role` accepts `coach`, `client` or `admin`;
  `status` accepts `active` or `inactive`. Account rows contain identity, role,
  access flags, discipline, creation date and current active client count. They
  exclude passwords, private tracker records and setup tokens. Pending invitation
  counts exclude expired, cancelled, accepted and inactive-coach invitations.
- `POST /api/admin/coaches` accepts `name`, unique `email` and required boolean
  `is_bodybuilding_coach`. It always creates an active, non-admin coach with a
  random initial password. The response contains limited account `data`,
  `setup_url`, `expires_in_minutes` and `delivery` (`sent`, `local` or `failed`).
  The welcome email lets the coach choose a password through the existing reset
  flow. Default link lifetime is 60 minutes. Mail failure leaves the account
  intact and returns a copyable setup link.
- `POST /api/admin/coaches/{coach}/setup-link` issues a fresh password link for
  an active, non-admin coach. Earlier password links become invalid.
- `PATCH /api/admin/users/{user}/access` accepts only `is_active` for account
  changes. It preserves role, discipline, administrator flags, assignments and
  history. Administrator accounts cannot be deactivated through this endpoint.
  Deactivation blocks sign-in and coaching access; it does not end coaching.

Coach creation and password-link delivery share the invitation limit of ten
requests per minute per signed-in account. No public admin registration or
administrator privilege-management endpoint exists. Public paid signup grants
coach access, while administrator deactivation remains a separate control.

### Ending coaching and handover PDFs

`DELETE /api/clients/{client}/coaching` is restricted to a currently assigned
coach. It creates a personal handover PDF and marks only that assignment inactive
in one transaction. It does not set the client's account `is_active` to false.
Clients can still sign in. Without an active coach, Coaching handovers is their
only dashboard tab. Their plans, reviews and logged history remain stored; the
existing read endpoints retain access to the client's own saved records. All
client writes under `/api/clients/{client}` return 403 without an active coaching
assignment, including requests from a dashboard opened before coaching ended.
Signing out and accepting a new coaching invitation remain available. Clients
with another active coach keep their full dashboard. The former coach loses
access immediately. A PDF generation failure rolls back both the report and
the assignment change.

The optional JSON body accepts `feedback` (10,000 characters), `next_steps`
(5,000 characters), and `notes` (a list of at most ten objects). Each supplied
note requires `title` (160 characters), `period` (120 characters) and `body`
(10,000 characters). Omit these fields, send null or use an empty notes list to
end coaching without final notes. Long notes continue on extra pages.

`GET /api/coaching-handovers?page=1` returns only the signed-in client's reports,
12 per page, with `id`, `coach_name`, `discipline`, `started_on`, `ended_at` and
`pdf_url`. It does not include the underlying snapshot or PDF bytes.
`GET /api/coaching-handovers/{handover}/pdf` previews a saved PDF inline;
`?download=1` uses attachment disposition. Both require the owning client's
active account and session, use private no-store responses, and forbid access
by coaches or other clients. Inline framing is restricted to the same origin.
Repeated coaching periods create separate immutable reports. For older inactive
assignments with known end dates, operators can run
`php artisan coaching:backfill-handovers` once after upgrading. The command
preserves existing PDFs and excludes records dated after the assignment ended.

### Check-ins and workouts

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

End-of-day nutrition is completed by the client independently of posing:
`GET /api/clients/{client}/end-of-day-nutrition?date=YYYY-MM-DD` reads that day's
responses and `nutrition_completed_at`, or null when no check-in exists.
`PUT` to the same URL accepts `check_in_date` and the five nutrition fields.
Today/past dates are allowed. Missing fields preserve existing responses; explicit
null clears them. Saving responses sets the server-controlled completion timestamp;
clearing every response removes completion. Blank saves do not create empty days.
The client alone can save; currently assigned coaches can read. A save reuses the
existing daily record and preserves bodyweight, photos, posing submission/version
and coach feedback. No fixed evening hour is imposed.

Daily check-in POST/PATCH payloads also accept nullable integer `hunger_level`,
`appetite_level`, and `digestive_discomfort` (1-5), plus nullable `digestion_notes`
and `dietary_difficulties` (strings up to 5,000 characters). Hunger/appetite increase
from low to high; digestive discomfort increases from none to severe. Omitted
PATCH fields are preserved; explicit null clears a response. These fields use
the existing client-only check-in write permissions and do not submit pose photos.

Review `summary.nutrition` provides `{ average, readings }` for each numeric field
and dated `reports` containing digestion notes/dietary difficulties, newest first.
It uses the same seven/fourteen-day window as the review summary, excludes missing
ratings from averages, and returns null averages when nothing was recorded.

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

## Competition dates

Competition management is restricted to coaches with a current active assignment.
Clients cannot use these endpoints. Ending coaching preserves competition records
and removes the former coach's access.

- `GET /api/competitions?page=1&period=all`: clients with at least one matching
  competition, paginated by 12 clients and ordered by name. `period` accepts `all`
  (the default), `upcoming` (today or later) or `past` (before today). Both client
  groups and competitions are filtered before pagination. Each show has a derived
  `period` of `past` or `upcoming`, and the response includes `today` in the
  application's timezone. Unassigned, inactive and non-matching clients are excluded.
  Optional `client_id` (a positive integer) limits shows to one assigned client
  and combines with `period` before pagination. The response also includes
  `client_options` (IDs and names only) for all currently assigned active clients
  with any competition, independent of the selected client or period. Unknown or
  unassigned IDs return an empty show list without exposing other clients.
- `POST /api/clients/{client}/competitions`: requires `competition_name` (max 200)
  and `competition_date` (`YYYY-MM-DD`). Optional fields are `location` (max 200),
  `category` (max 150). Past dates are accepted. Only shows before today can include
  optional `placing` (max 50) and `notes` (max 5,000). These fields must be omitted
  for today's or future shows, and are omitted from those shows' API responses.
- `PUT /api/clients/{client}/competitions/{competition}`: corrects a competition
  using the same fields and required name/date. The client cannot be changed.
  Moving a show to today or later hides existing results without clearing them.
  Omitted result fields retain their values; past results can be cleared explicitly.
- `DELETE /api/clients/{client}/competitions/{competition}`: removes only that
  competition. Removing a client's last competition hides them from the grouped
  list without deleting their account or other records.

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

Photo and posing routes require a current bodybuilding coaching assignment.
The assigned coach must have `is_bodybuilding_coach=true`; clients are eligible
when at least one current active coach has that flag. A powerlifting coach cannot
access photos through a client who also has a bodybuilding coach. Without this
access, posing read/upload/remove/submit/review routes and private image URLs
return 403. Access changes do not extend photo deadlines. Written reviews remain stored.

`GET /api/clients/{client}/daily-check-ins` includes `posing_enabled` for the current
viewer. Normal measurements and nutrition remain available for both disciplines.

`GET /api/user` includes boolean `is_bodybuilding_coach`. True means bodybuilding,
false means powerlifting. This is a fixed discipline assigned by trusted
provisioning code. Neither coaches nor clients can change it through the API.

Saving daily numbers creates or edits a draft. All 13 photos are optional: the
eight bodybuilding poses, front/rear/left/right relaxed views, and favourite
classic pose. Catalogues use `standard_poses` and `additional_poses`; use their
stable keys for uploads. Blank and partial sets can be submitted and reviewed.

The following routes extend
`/api/clients/{client}/daily-check-ins/{checkIn}`:

| Method | Suffix                            | Purpose                                                                 |
| ------ | --------------------------------- | ----------------------------------------------------------------------- |
| GET    | `/posing`                         | Photo set, pose catalogue, submission state and coach reviews           |
| POST   | `/posing/photos/{pose}`           | Upload or replace one pose, using multipart `photo` and `photo_version` |
| GET    | `/posing/photos/{pose}`           | View an available private image; `?download=1` downloads an attachment  |
| PUT    | `/posing/photos/{pose}/retention` | Coach choice: `retained` boolean and current `photo_version`            |
| DELETE | `/posing/photos/{pose}`           | Remove one pose, with JSON `photo_version`                              |
| POST   | `/posing/submit`                  | Submit the optional photo set, with JSON `photo_version`                |
| PUT    | `/posing/review`                  | Save the signed-in coach's review of the submitted photo version        |

All responses wrap the posing snapshot in `data`. It contains `check_in_id`,
`photo_version`, `submitted_at`, `standard_poses`, `additional_poses`, `photos`,
`retained_photo_limit` (4), and `reviews`. Each photo includes its numeric or
temporary UUID ID, `pose_key`, authenticated `url`, `updated_at`, `expires_at`,
`coach_viewed_at`, and `retained` flag. Private filesystem paths are never returned.
The main check-in list also includes
`photo_version`, `submitted_at`, and `pose_photos_count` for submission status.

`GET /api/daily-check-in-photos/{photo}` also serves a selected private image by
its database ID. Both image routes require an eligible owner or assigned coach,
use private non-storing cache headers, and reject expired images. Adding
`?download=1` sends a downloadable image attachment. Do not copy files into public
storage. Only coach image requests start the first-view clock; client views do not.

All uploads initially use private temporary files and expire seven days after
upload. Coaches can choose up to four photos from any of the 13 poses to keep in
database photo records. That shared per-check-in limit is enforced with locks.
Selected copies expire seven days after the first coach view; repeated views or
saves cannot extend that date. Selection does not invalidate an existing review.
Removing a selected copy frees its slot and leaves the original review copy
available until its upload deadline. See [photo storage and cleanup](PHOTOS.md).

Only the client can upload, remove, or submit photos. Uploads accept JPEG, PNG, or
WebP images, at most 5 MB and 6,000 pixels in either dimension. One image is sent
per request. Submit the current integer `photo_version` with every mutation;
stale versions receive HTTP 409. Photo changes and check-in date changes increment
the version and clear submission. Existing reviews remain visible but are marked
out of date; unchanged metrics edits do not invalidate a posing review.

Only a currently assigned bodybuilding coach can review a submitted check-in,
including blank or partial photo sets. The review
body requires `photo_version`, integer `posing_rating` (1-10), written `feedback`
(up to 10,000 characters), and boolean `is_competition_prep`. Optional
`flatness_rating` and `fullness_rating` accept integers 1-10 during prep; send
`null` or omit them outside prep. The scale directions are explicit in the UI:
10 means very flat for flatness, and very full for fullness. These are separate
coach assessments, not automated scores.

Each coach can update only their own review. Reviews include `coach_id`,
`coach_name`, scores, feedback, `photo_version`, `reviewed_at`, and `is_current`.
They remain stored after coaching ends; reading posing history requires active
bodybuilding photo access. A client cannot submit
coach feedback or score fields through the metrics endpoint.

`photos:expire` runs every five minutes. It moves unexpired named legacy photos
into temporary review storage and removes expired database records, manifests
and files. API access ends at the deadline even if scheduled cleanup is delayed.
Measurements, submissions and coach feedback survive photo expiry. Failed file
cleanup is retried through the queue.

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
- Each endpoint has its own limit of five requests per minute per IP. Email
  requests also have the hourly limits listed above. Password changes
  rotate remember tokens and invalidate old authenticated sessions on their next
  protected request. Inactive accounts cannot reset their passwords.
- `/forgot-password` requests a link, and `/reset-password/{token}?email=...`
  chooses the new password. Links use `APP_URL`, not the incoming request host.

## Exercise progress

The endpoint also accepts `from` and `to` (`YYYY-MM-DD`, no later than today).
The start must not follow the end. The session limit remains 60; exercise choices
include all the client's logged exercises, even outside the selected date range.

Current assigned coaches receive additional per-session analytics fields:

- `prescribed_calories_kcal`, `prescribed_meal_readings`, `prescribed_meal_count`:
  training-day totals from the latest non-cancelled meal plan effective on that
  training date. A total is null if any prescribed meal has no calorie reading.
- `logged_calories_kcal`, `logged_calorie_subtotal_kcal`, `logged_item_readings`,
  `logged_item_count`, `logged_meal_count`: totals for the client's food logs on
  that date. A complete total needs calories on all recorded food items and no
  empty meal logs. Partial data retains its subtotal but has a null total. Logged
  meals do not prove a full day of intake; recorded zero calories remain zero.
- `average_bodyweight_kg`, `bodyweight_readings`: the rolling seven-day average
  ending on the training date, using recorded weigh-ins only. No later readings
  are used. An empty window has a null average and zero readings.
- `bodyweight_change_from_7_days_ago_kg`, `prior_bodyweight_readings`: comparison
  with the seven-day window ending seven days before the session. The change is
  null if either window has no weigh-ins.

Client responses retain the existing performance fields. Former or unrelated
coaches cannot access the endpoint. All responses include `session_limit: 60`.

`GET /api/clients/{client}/exercise-progress?exercise_id={id}` returns an exercise
selector (`exercises`), the selected `exercise_id`, and `data` containing up to 60
recent sessions in chronological order. The exercise parameter is optional.

Each point includes `workout_id`, `date`, `workout_name`, `max_weight_kg`,
`max_reps`, and `working_sets` (session volume for that exercise). Completed sets
with positive reps from sessions dated today or earlier are included. Recorded
weight is optional, so bodyweight sets count towards volume. `max_weight_kg` is
null when no included set has a recorded load; a recorded zero remains zero.
Maximum weight and reps are independent records. Programmed sets are working
sets. The previous `logged_sets` and `volume_kg` fields are replaced by `working_sets`.
The client's own access and current-coach access use the
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
