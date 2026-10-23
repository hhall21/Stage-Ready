# Explaining training analytics

This feature helps a coach compare an exercise’s logged performance with time,
daily calories and bodyweight. It describes associations; it does not establish
that calories or bodyweight caused a change in performance.

## From a logged set to a graph

1. Clients log sets against a coach-prescribed workout. Only completed sets with
   positive reps count. A bodyweight exercise still counts without a load value.
2. `ExerciseProgressController` checks the client’s access policy, validates the
   selected exercise/date range and returns at most 60 sessions chronologically.
   Each session has its heaviest recorded load, highest rep count and working-set
   count. The load and rep maxima can come from different sets; this is stated
   on screen. Volume means working sets, never load multiplied by reps.
3. For coaches, `TrainingPerformanceContext` attaches calories for the session
   date and the average of recorded weigh-ins in that date’s seven-day window.
   The previous seven-day window provides the comparison. Future weigh-ins are
   excluded, missing readings remain null and both windows include reading counts.
4. Calorie context uses either the training-day prescription effective on the
   session date or food logged on that date. Incomplete food entries are subtotals,
   excluded from calorie plots. A complete set of logged items can still represent
   part of a day. Recorded zero calories remain a real zero.
5. `TrainingAnalytics.vue` owns its exercise and history filters, loading state,
   errors and selection. `PerformanceChart.vue` plots SVG points. The time view
   shows weight only, spaces dates by elapsed time and breaks lines at missing
   weights. Calories/bodyweight comparisons show separate weight and rep scatter
   plots. Keyboard focus or selecting a point opens that session’s readings.

## Why these choices matter

The same session can have a valid rep count but no load, or a load but no matching
bodyweight/calorie readings. Treating missing values as zero would invent results
and distort the graph. Keeping each chart’s valid-point count makes its coverage
visible. Historical plan versions and historical weigh-in windows prevent today’s
prescription or a later weigh-in from changing what an earlier session means.

`TrainingAnalyticsTest` covers arithmetic, date boundaries, incomplete calories,
explicit zeros, dated plans, excluded sets and client/coach access. The feature
also receives desktop/mobile browser checks for date spacing, gaps, keyboard
selection, client changes and bodyweight exercises.

## Current limits

Each exercise is limited to the latest 60 sessions within the selected range.
The charts summarize session maxima, not matched sets, estimated one-rep maxes
or causal effects. Extra exercise entries on the same date remain separate
sessions. Nutrition values are recorded or prescribed values, not verified intake.
