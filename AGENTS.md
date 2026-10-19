# Project preferences

- The website's name is **Stage-Ready**. Always use this name in user-facing
  copy, navigation, page titles, and branding; never call the website PT Tracker.
- Keep the interface dark with white writing throughout.
- Bodybuilding photographs should blend into the dark page and form backgrounds
  with no visible image borders.
- Each workout can contain at most 10 exercise entries.
- Training volume is measured in working sets, not weight multiplied by reps.
  Programmed sets are working sets. Completed volume counts completed sets with
  positive reps, including bodyweight sets without a recorded load.
- Coaches build repeating training splits of 1–15 numbered days, including rest
  days, with a separate prescribed workout for each training day and a start date.
  Splits repeat until a newer split begins. Clients cannot create or rewrite
  workouts; they log performance against coach-prescribed sessions.
- Clients can choose a different split day for special circumstances. Extra rest
  pauses the split; selecting a training or planned rest day makes tomorrow
  continue from the following split day. Changes must preserve logged history.
- Coaches can edit workouts for injuries or other changes; updates apply to
  unlogged sessions from today onward and preserve past, completed and logged
  sessions. Changing the start date or length after recorded history requires a
  new split. Legacy weekly programs and their workout history remain available.
- Bodyweight trends use a rolling seven-day average from recorded weigh-ins
  only, with reading counts and comparison to seven days earlier.
- Client onboarding starts coaching immediately when an invitation is accepted.
  Do not add assignment start/end date inputs or an upcoming-clients section.
  Ending coaching must preserve the client's account and logged history.
- All client check-in measurements, notes and pose photos are optional. Clients
  can save or submit partial or blank check-ins; coaches are responsible for
  following up. Missing readings must stay missing, never become zero.
- Coaches provide a 1-10 posing rating and feedback;
  competition-prep reviews can include separate 1-10 flatness and fullness ratings.
  Photos are private. Changed photos require a fresh submission and review.
- Posing check-ins offer the original eight poses plus front relaxed, rear
  relaxed, left side relaxed and right side relaxed. Favourite classic pose is
  a thirteenth option. None of the photos block submission or coach review.
- Diet prescriptions have separate training-day and rest-day meals with independent
  times. Rest-day copying defaults to a 60-minute delay and is coach-adjustable.
- Coaches prescribe daily cardio, mandatory posing rounds and vacuum sets/holds.
  Future-dated versions allow planned posing increases approaching a show.
- Written coach check-in reviews are weekly in prep and fortnightly off-season.
  Plans and reviews remain available to clients when coaching ends.
- Nutrition check-ins record optional hunger, appetite and digestive discomfort
  ratings (1-5), digestion notes and dietary difficulties. Coach review summaries
  exclude missing responses from averages and show reading counts.
- Clients complete nutrition at the end of the day, independently of posing.
  Saving evening responses preserves existing daily numbers and posing submissions.

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
