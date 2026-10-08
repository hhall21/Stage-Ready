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
`Accept: application/json`. Refresh the token after login/logout.

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
User roles, account provisioning and coach assignments must be managed by trusted
application code; public registration and assignment-management endpoints are not included.

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
Deleting check-ins/workouts cascades to their dependent database records;
uploaded file cleanup is outside these endpoints.

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
2. Run `composer install --no-dev --optimize-autoloader`, `npm install`, and `npm run build`.
3. Run `php artisan migrate --force` and `php artisan optimize`.
4. Configure the platform health check to request `/up` with `Accept: application/json`.

Never commit the populated `.env` file or production/staging credentials.
