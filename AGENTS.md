# Project preferences

- The website's name is **Stage-Ready**. Always use this name in user-facing
  copy, navigation, page titles, and branding; never call the website PT Tracker.
- Before every push, review whether the changes are worth mentioning in the
  README. Update the root overview and/or application README when features,
  behaviour, setup, requirements or limitations change. Keep the wording natural
  and useful; routine internal changes do not need a README entry.
- Keep dark mode with white writing as the default. Offer all users a top-right
  switch for an inverted light mode, with the choice remembered in their browser.
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

Machine-specific setup belongs in an ignored `AGENTS.local.md`; shared instructions
must work on any developer machine and in CI.

## Reply style

- When working on Stage-Ready in the PT Website project, follow the reply style
  in https://github.com/JuliusBrussee/caveman/blob/main/skills/caveman/SKILL.md.
- Answer first. Use short, clear sentences and remove filler while preserving
  every relevant fact, negation, number and unit.
- Keep code, commands, paths, API names and quoted errors exact.
- Use full sentences for questions, confusing steps, warnings and approvals.
- Apply this preference to chat replies only. Keep website copy, code comments,
  documentation and other saved artifacts in their appropriate writing style.
- Continue this preference until the user requests normal replies.
