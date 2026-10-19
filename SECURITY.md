# Stage-Ready security

## Report a vulnerability

Please use GitHub's **Security → Report a vulnerability** option when it is
enabled. Do not post passwords, invitation links, reset tokens, private photos,
client records, or working exploits containing personal data in public issues.
Maintainers should enable private vulnerability reporting before publication.

## Before making the repository public

- Review the files and the history of every branch and tag that will be public.
  Ignoring a file does not remove earlier commits. Keep local backup branches
  containing old credentials private; push only reviewed branches and tags.
- Rotate any real database, email, API or other credentials that have ever been
  committed, even if the files have since been removed from the public history.
- Keep populated `.env` files, local databases, uploaded photos, logs, generated
  client reports, private keys and backups out of Git. Only the blank environment
  examples, fictional seed data and verified `database/demo.sqlite` belong here.
- Run `php database/verify-demo.php` from `pt-tracker` before publishing any
  change to the demo database. It must contain only fictional fixtures, with no
  sessions, invitations, reset tokens, client uploads or other runtime records.
- Confirm permission to redistribute the photographs bundled in `resources/`
  and choose a repository license covering the code you own. Dependency licenses
  and the Composer package's license field do not grant rights to photographs.
- Enable GitHub secret scanning, push protection, dependency alerts and private
  vulnerability reporting where available. Required CI checks should pass before
  merging changes into the public branch.

To scan reviewed Git history locally with [Gitleaks](https://github.com/gitleaks/gitleaks):

```sh
gitleaks git . --log-opts="main" --redact=100
```

Run the scan for every branch/tag being published, or use `--log-opts="--all"`
when all local refs are intended for publication. Keep scan reports private.

## Application protections

Database reads and writes use Eloquent/query-builder value bindings. The few raw
SQL expressions in HTTP handlers are fixed expressions with bound values; never
interpolate request text into SQL or let requests choose table/column names.
Validation supplements parameter binding and does not replace it. Security tests
exercise hostile login, identifier, date and nested exercise values, and verify
that SQL-looking notes are stored literally without changing query scope.
See [Laravel's query binding guidance](https://laravel.com/framework/docs/13.x/queries).

Session writes require CSRF protection. Login attempts are limited per email/IP
pair and per IP, including attempts that rotate email addresses. Record policies
restrict clients to their own records and coaches to active assignments. Role
and ownership fields cannot be chosen through the tracker forms. Reset tokens
are expiring and single use; password changes invalidate existing sessions.

New passwords require at least 12 characters with uppercase/lowercase letters
and a number. Login, invitation acceptance and password resets reject null bytes
and values exceeding bcrypt's 72-byte limit, including multibyte characters.
Demo accounts deliberately use the documented weak password for local use only;
the sample seeder refuses non-local/non-testing environments.

Posing photos stay on a private disk and are streamed only after authorization.
Uploads are validated by content type, size and dimensions. Dynamic responses
disable storage in browser/shared caches, block framing and MIME sniffing, and
omit referrers so invitation/reset links do not leak through the Referer header.
These response headers do not replace upload validation, authorization or CSRF.

## Deploying a hosted application

Making the source public and deploying the app are separate steps. Use a new
private database for a hosted installation, not `demo.sqlite` or the sample SQL.
Set `APP_ENV=production`, `APP_DEBUG=false`, a fresh `APP_KEY`, the correct HTTPS
`APP_URL` and `SESSION_SECURE_COOKIE=true`. Use unique secrets, a database account
with only the required privileges, private upload storage outside the web root,
and a configured mail service. Serve only `pt-tracker/public/`, and configure
trusted proxies for the actual infrastructure without trusting arbitrary clients.
See [Laravel's deployment guide](https://laravel.com/framework/docs/deployment).

Run migrations and the queue worker, back up the database and private uploads,
test invitation/reset delivery, and verify the deployed server denies requests
for `.env`, `.git`, database files and private storage. Never run demo seeding or
destructive database resets on a live coaching database.

From `pt-tracker`, CI checks include:

```sh
composer validate --strict
composer audit --locked
npm audit
composer ci:check
npm run build
php database/verify-demo.php
```

Automated tests and dependency/secret scans reduce risk but are not a guarantee
that every vulnerability has been found. Keep dependencies and checks current.
