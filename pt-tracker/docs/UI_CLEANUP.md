# Stage-Ready interface cleanup

This pass applies the layout, copywriting, navigation, dashboard and accessibility
guidance in [anti-ai-slop](https://github.com/muris11/anti-ai-slop) to the existing
homepage, sign-in page and dashboard shell. It does not install an agent plugin.

## Direction and reasons

The project’s existing direction is a dark interface, white writing and original
bodybuilding artwork blended into the background. The audience is bodybuilding
and powerlifting coaches and their clients.

- **Energy 2:** retain prominent athletic artwork and a strong headline, with
  readable sentence-case copy explaining the actual product.
- **Rhythm 2:** use feature rows for the coaching tools, an illustrated section
  for prep, an ordered workflow and native disclosure controls for questions.
- **Motion 1:** controls provide hover/focus feedback; reading and changing
  dashboard sections do not trigger entrance animations.
- **Colour:** neutral chrome follows the existing brand. Sand and teal remain
  available for distinguishing real series in training analytics.
- **Typography:** retain Instrument Sans for continuity. Public body copy is
  larger, sign-in uses a functional heading and dashboard titles name the task.
- **Imagery:** keep the existing athlete and equipment illustrations on public
  pages. Remove the large motivational banner from the working dashboard so
  scheduled training and check-ins receive the attention.
- **Navigation:** remove ornamental dashboard numbers and repeated outbound
  arrows. All public links remain visible on phones, without a hidden menu.
- **Spacing:** public sections use 8px-based spacing and responsive gutters;
  dashboard records use a compact definition list instead of equal metric cards.
- **Honesty:** describe existing capabilities, retain optional check-ins and
  discipline-specific posing access, and add no adoption figures or testimonials.

## Findings addressed

1. **R-05, R-14:** the homepage repeated six equally weighted feature entries,
   numbered steps and a closing sales section. Replace these with a shorter
   hierarchy tied to real coaching tasks.
2. **R-15, R-16:** repeated slogans obscured what the platform does. Use specific
   headings and action labels, including “View coaching tools”.
3. **R-03, R-24:** public section navigation disappeared on phones. Keep both
   section links available and provide 44px navigation and button targets.
4. **R-08, R-19:** ordinary local actions used outbound arrows and content entered
   with template animations. Remove that decoration from the changed screens.
5. **R-25, R-32:** strengthen shared control boundaries, include disclosure
   controls in focus styling and add skip links on the changed pages.
6. **R-27:** overview totals and an empty workout state appeared while records
   were loading or had failed. Show those records only after a successful load;
   retain loading feedback and an error message with Retry.

## Review scope

This is an interface pass. Coaching permissions, billing, invitations, training
prescriptions and logged history keep their existing implementation. The named
rules are applied to the changed screens; this is not a claim that every existing
form and chart throughout the application has received a full accessibility audit.

## Validation

- **PASS, production build:** `npm run build` compiled 640 modules.
- **PASS, types:** `npm run types:check` completed without errors.
- **PASS, lint:** `vp lint --threads=1` passed for the five changed Vue files.
- **PASS, formatting:** `vp fmt --check --threads=1` passed for the seven changed
  application and documentation files.
- **PASS, R-03:** the homepage and sign-in page had no horizontal overflow at
  320px and 375px phone widths. Coach navigation was checked at 320px, client
  navigation at 375px, and desktop layouts at the default viewport and 1440px.
- **PASS, R-24/R-26:** both public section links and the primary feature link
  reach their sections. All four questions open. The local fictional coach and
  client accounts sign in; sign-out returns to sign-in. All ten coach tabs and
  eight client tabs switch to the corresponding headings. Overview shortcuts
  open training for a coach and the daily check-in for a client.
- **PASS, R-25/R-32, changed shared controls:** text contrast is 20.38:1 on the
  page background, 18.26:1 in inputs and 11.73:1 on hovered buttons. The #888
  boundary is 4.38:1 on buttons and 5.15:1 on inputs. The white focus ring is
  15.52:1 on a button. Mobile navigation targets are at least 44px tall. Activating
  the keyboard skip link focuses `main-content`.
- **PASS, R-27, overview:** browser review confirmed the explicit loading state
  and populated records. Source review confirms records, totals and empty
  messages are withheld during loading or error, with Retry available on error.
- **PASS, R-17/R-18/R-36/R-38:** public copy describes existing features; no
  fabricated metrics, endorsements, pricing, signup or payment flow was added.
- **PASS, purpose and liveliness:** the reasons and dials above explain the
  retained imagery, neutral palette, different section layouts and calm motion.
- **PASS, browser console:** no warnings or errors were reported during the
  homepage, sign-in, coach and client checks.

The repository-wide `npm run check` could not complete: its formatter workers
ran out of memory, including on the unchanged baseline. A full formatting scan
also exhausted memory. Targeted formatting/lint, full Vue type checks and the
production build completed instead. No backend code was changed, and no training,
nutrition, photo or coaching records were edited during browser checks.
