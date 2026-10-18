# Stage-Ready migrations

`demo.sqlite` is the shared development database, generated from the migrations
and `PtTrackerSeeder` sample records, with no local sessions or enquiries.
Copy it to `database.sqlite` for local use (see the application README), then run
`php artisan migrate` to apply any newer migrations. Never copy over an existing
working database that contains records you want to keep. Do not run `db:seed`
on the already-seeded copy.

The client-onboarding migration adds the invitation table. Run `php artisan migrate`
on an existing installation to enable invitations; it preserves existing records.
Coaching assignments use an automatic current date internally for compatibility
with existing data. New invitations provide immediate, open-ended access on
acceptance, and coaches do not enter assignment dates.

The migrations translate the table definitions in `../../CREATE AND INSERTS.sql`.
They include column lengths, decimal precision, nullability, defaults, named foreign
keys, delete actions, and composite unique constraints. Laravel's existing users,
password reset, session, cache, jobs, and contact enquiry migrations are retained.
The tracker user fields are added in a separate migration. The user factory now
supplies the required client role.

Clients are stored in `users` with role `client`. The earlier `create_clients_table`
placeholder now does nothing. If it was already run, any existing `clients` table
is left untouched.

The SQL defines `phases_name` but its INSERT uses `phase_name`. These migrations
use `phase_name`. The `cycle_entries.cycle_id` name is preserved and references
`phases.id`, as in the SQL. Sample INSERT data is not imported by migrations.

## Running on a new, empty database

From the `pt-tracker` folder, after configuring the intended database in `.env`:

```powershell
php artisan migrate
```

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
without migration files. The SQL INSERT data would need to be imported separately
or converted into seeders.
