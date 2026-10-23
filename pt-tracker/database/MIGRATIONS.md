# Stage-Ready migrations

The calendar-query migration adds client/date indexes to workouts, meals and
legacy mesocycles. Run `php artisan migrate`; it preserves all records. Calendar
filters now compare bound day boundaries directly, so both MySQL DATE values and
SQLite midnight timestamps work without wrapping indexed columns in `DATE()`.

The end-of-day nutrition migration adds a nullable completion timestamp. Existing
responses are preserved; completion is recorded when clients save the separate
end-of-day form. Apply with `php artisan migrate`.

The nutrition-check-in migration adds five nullable columns to daily check-ins:
hunger, appetite, digestive discomfort, digestion notes, and dietary difficulties.
Run `php artisan migrate`; existing records retain their values and start with
no nutrition responses. No posing or review history is replaced.

The coaching-prescriptions migration adds versioned nutrition plans, daily cardio/
posing/vacuum schedules, coach-written reviews, and review phase/start fields on
coaching assignments. Existing assignments default to fortnightly off-season
reviews; plans and reviews start empty. Apply with `php artisan migrate`.

The weekly-health migration adds an optional monitoring start timestamp to coaching
assignments and a separate health-reading table. Run `php artisan migrate` on an
existing installation. Monitoring starts disabled; existing records are preserved.
Ending coaching clears that assignment's monitoring requirement without deleting
the client's readings.

Migrations are the schema source of truth. New local installations create an
empty ignored `database.sqlite`, run `php artisan migrate`, then load fictional
fixtures with `php artisan db:seed`. No database snapshot is tracked. Never seed
over existing client records or use destructive resets on a working database.

The client-onboarding migration adds the invitation table. Run `php artisan migrate`
on an existing installation to enable invitations; it preserves existing records.
Coaching assignments use an automatic current date internally for compatibility
with existing data. New invitations provide immediate, open-ended access on
acceptance, and coaches do not enter assignment dates.

The posing-check-in migration adds submission/version fields, named pose metadata
on progress photos, and a coach-review table. Run `php artisan migrate` to enable
the workflow on an existing installation. Existing numbers and photo records are
preserved. Legacy photos have no pose key and do not satisfy the named-pose
submission requirement. Existing check-ins start as drafts. Named pose keys are
unique per check-in; legacy records with a null pose key may still coexist.

The current migrations define table types, precision, nullability, foreign keys,
delete actions and unique constraints. The earlier SQL dump and unused migration
drafts have been retired. Sample data lives only in seeders and their JSON fixture.
Clients are users with the client role; an earlier clients-table placeholder
migration remains a no-op for compatibility with existing migration histories.

## Running on a new, empty database

From the `pt-tracker` folder, after configuring the intended database in `.env`:

```powershell
php artisan migrate
```

## Repeating split upgrade

`2026_10_19_230000_create_training_splits` adds repeating 1–15-day programs,
dated client day selections, and nullable split references on workout snapshots.
Run `php artisan migrate` on an existing Laravel installation. Existing weekly
mesocycles and workout performance are preserved. New splits use a start date
and keep repeating until a newer split begins. The application only replaces
unlogged upcoming sessions when a coach edits a split or a client changes a day.

## Existing database created with SQL

Do not run these creation migrations on the existing SQL database yet: its tables
already exist, and Laravel may not have records of them in its `migrations` table.
These files do not automatically adopt or validate existing tables.

To keep existing data, back up the database, compare its actual schema with these
migrations, resolve differences (including `phases_name`), and record a verified
migration baseline. Laravel's authentication support tables and `email_verified_at`
column may also need to be added if they are absent. Do not simply mark every
migration as applied: that could hide missing columns or tables.

For disposable development data only, once all definitions are ready and the
target connection has been checked, `php artisan migrate:fresh` rebuilds the
database. It deletes ALL tables and data on that connection, including tables
without migration files. Load fictional fixtures with the local-only seeders
only after verifying the target.
