# Project preferences

- The website's name is **Stage-Ready**. Always use this name in user-facing
  copy, navigation, page titles, and branding; never call the website PT Tracker.
- Keep the interface dark with white writing throughout.
- Bodybuilding photographs should blend into the dark page and form backgrounds
  with no visible image borders.
- Each workout can contain at most 10 exercise entries.
- Coaches plan repeating weekly workouts in mesocycles with entered start/end
  dates. They can edit the mesocycle for injuries or other changes; updates apply
  to future unlogged sessions and preserve past, completed, and logged history.
- Bodyweight trends use a rolling seven-day average from recorded weigh-ins
  only, with reading counts and comparison to seven days earlier.
- Client onboarding starts coaching immediately when an invitation is accepted.
  Do not add assignment start/end date inputs or an upcoming-clients section.
  Ending coaching must preserve the client's account and logged history.

## Local storage on Haydy's computer

- These storage paths apply only to Haydy's Windows computer with the Seagate
  Portable Drive connected as D:. Collaborators and CI should use their own
  available storage and normal temporary/cache locations; the app does not
  require a D: drive.
- On Haydy's computer, C: has very little free space. Prefer the Seagate (D:)
  for temporary files, dependency caches, large downloads, and clean-install checks.
- Use `D:\Stage-Ready-Work\Temp` for `TEMP` and `TMP`,
  `D:\Stage-Ready-Work\npm-cache` for `npm_config_cache`, and
  `D:\Stage-Ready-Work\composer-cache` for `COMPOSER_CACHE_DIR` during local work.
  Put disposable verification copies under `D:\Stage-Ready-Work\Checks`.
