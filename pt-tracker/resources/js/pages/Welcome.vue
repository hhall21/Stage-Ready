<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { onMounted, onUnmounted, ref } from 'vue';
import DumbbellVisual from '@/components/DumbbellVisual.vue';
import FeatureShowcase from '@/components/FeatureShowcase.vue';
import HeroAthletes from '@/components/HeroAthletes.vue';
import SiteHeader from '@/components/SiteHeader.vue';
import { vReveal } from '@/lib/motion';

const home = ref<HTMLElement | null>(null);
const scrolled = ref(false);
const activeStep = ref(-1);
const heroPinned = ref(false);
const heroMotion = ref(0);
const prepPinned = ref(false);
const prepReading = ref(false);
let cleanupMotion: (() => void) | undefined;

onMounted(() => {
    const root = home.value;
    if (!root) return;

    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    const header = root.querySelector<HTMLElement>('.site-header');
    const hero = root.querySelector<HTMLElement>('.home-hero');
    const heroScene = root.querySelector<HTMLElement>('.hero-scroll-section');
    const heroArtwork = root.querySelector<HTMLElement>('.hero-athletes');
    const dumbbell = root.querySelector<HTMLElement>('.dumbbell-visual');
    const prep = root.querySelector<HTMLElement>('.prep-scroll-section');
    const prepContent = root.querySelector<HTMLElement>('.prep-section');
    const workflow = root.querySelector<HTMLElement>('.workflow-section');
    let frame = 0;

    const update = () => {
        frame = 0;
        const scrollTop = Math.max(0, window.scrollY);
        const scrollRange = Math.max(1, root.scrollHeight - window.innerHeight);
        const heroHeight = hero?.offsetHeight || 1;
        const headerHeight = header?.offsetHeight || 106;
        const viewportSpace = Math.max(1, window.innerHeight - headerHeight);
        const heroBounds = heroScene?.getBoundingClientRect();
        const heroArtworkBounds = heroArtwork?.getBoundingClientRect();
        const canPinHero =
            !reducedMotion.matches &&
            window.innerWidth > 720 &&
            heroHeight <= viewportSpace - 16;
        const prepHeight = prepContent?.offsetHeight || 0;
        const prepBounds = prep?.getBoundingClientRect();
        const prepContentBounds = prepContent?.getBoundingClientRect();
        const dumbbellBounds = dumbbell?.getBoundingClientRect();
        const canPin =
            !reducedMotion.matches &&
            window.innerWidth > 720 &&
            prepHeight > 0 &&
            prepHeight <= viewportSpace - 32;
        const holdDistance = canPin ? 720 : 0;
        const workflowBounds = workflow?.getBoundingClientRect();
        const workflowProgress = workflowBounds
            ? (window.innerHeight * 0.7 - workflowBounds.top) /
              workflowBounds.height
            : -1;

        scrolled.value = scrollTop > 24;
        heroPinned.value = canPinHero;
        prepPinned.value = canPin;
        prepReading.value = Boolean(
            prepContentBounds &&
            prepContentBounds.top < window.innerHeight * 0.75 &&
            prepContentBounds.bottom > headerHeight + 80,
        );
        activeStep.value =
            workflowProgress < 0
                ? -1
                : Math.min(
                      steps.length - 1,
                      Math.floor(workflowProgress * steps.length),
                  );
        root.style.setProperty('--home-header-height', `${headerHeight}px`);
        root.style.setProperty('--hero-content-height', `${heroHeight}px`);
        root.style.setProperty(
            '--hero-hold-distance',
            canPinHero ? '360px' : '0px',
        );
        root.style.setProperty('--prep-content-height', `${prepHeight}px`);
        root.style.setProperty('--prep-hold-distance', `${holdDistance}px`);
        root.style.setProperty(
            '--prep-read-progress',
            prepBounds && holdDistance
                ? `${Math.min(1, Math.max(0, (headerHeight + 16 - prepBounds.top) / holdDistance))}`
                : '0',
        );
        root.style.setProperty(
            '--home-progress',
            reducedMotion.matches
                ? '0'
                : `${Math.min(1, scrollTop / scrollRange)}`,
        );
        if (reducedMotion.matches) {
            heroMotion.value = 1;
        } else if (
            heroArtworkBounds &&
            heroArtworkBounds.top < window.innerHeight &&
            heroArtworkBounds.bottom > headerHeight
        ) {
            const start = Math.max(
                0,
                heroArtworkBounds.top +
                    scrollTop -
                    headerHeight -
                    viewportSpace * 0.65,
            );
            const range = Math.max(160, Math.min(360, viewportSpace * 0.45));
            heroMotion.value = Math.min(
                1,
                Math.max(
                    0,
                    canPinHero && heroBounds
                        ? (headerHeight - heroBounds.top) / 360
                        : (scrollTop - start) / range,
                ),
            );
        }
        if (reducedMotion.matches) {
            dumbbell?.style.setProperty('--dumbbell-progress', '0');
        } else if (
            dumbbellBounds &&
            dumbbellBounds.top < window.innerHeight &&
            dumbbellBounds.bottom > headerHeight
        ) {
            const animationTop =
                canPin && prepBounds ? prepBounds.top : dumbbellBounds.top;
            const animationHeight = canPin
                ? prepHeight + holdDistance
                : dumbbellBounds.height;
            const progress = Math.min(
                1,
                Math.max(
                    0,
                    (window.innerHeight - animationTop - viewportSpace * 0.1) /
                        (viewportSpace * 0.8 + animationHeight),
                ),
            );
            dumbbell?.style.setProperty('--dumbbell-progress', `${progress}`);
        }
    };
    const scheduleUpdate = () => {
        if (!frame) frame = window.requestAnimationFrame(update);
    };

    update();
    window.addEventListener('scroll', scheduleUpdate, { passive: true });
    window.addEventListener('resize', scheduleUpdate);
    reducedMotion.addEventListener('change', scheduleUpdate);
    const resizeObserver =
        typeof ResizeObserver === 'undefined'
            ? undefined
            : new ResizeObserver(scheduleUpdate);
    resizeObserver?.observe(root);
    if (header) resizeObserver?.observe(header);
    if (hero) resizeObserver?.observe(hero);
    if (heroArtwork) resizeObserver?.observe(heroArtwork);
    if (prepContent) resizeObserver?.observe(prepContent);

    const faqObserver =
        typeof IntersectionObserver === 'undefined'
            ? undefined
            : new IntersectionObserver(
                  (entries) => {
                      entries.forEach((entry) => {
                          entry.target.classList.toggle(
                              'is-glinting',
                              entry.isIntersecting &&
                                  entry.intersectionRatio >= 0.75,
                          );
                      });
                  },
                  { threshold: 0.75 },
              );
    root.querySelectorAll('.faq-indicator').forEach((arrow) => {
        faqObserver?.observe(arrow);
    });

    cleanupMotion = () => {
        window.cancelAnimationFrame(frame);
        window.removeEventListener('scroll', scheduleUpdate);
        window.removeEventListener('resize', scheduleUpdate);
        reducedMotion.removeEventListener('change', scheduleUpdate);
        resizeObserver?.disconnect();
        faqObserver?.disconnect();
    };
});

onUnmounted(() => cleanupMotion?.());

function unveilFaqAnswer(event: Event) {
    const details = event.currentTarget;
    if (!(details instanceof HTMLDetailsElement)) return;

    const answer = details.querySelector<HTMLElement>('.faq-answer-reveal');
    answer?.getAnimations().forEach((animation) => animation.cancel());
    if (
        !answer ||
        !details.open ||
        window.matchMedia('(prefers-reduced-motion: reduce)').matches
    )
        return;

    answer.animate(
        [
            {
                height: '0px',
                opacity: 0,
                overflow: 'hidden',
                transform: 'translateY(-8px)',
            },
            {
                height: `${answer.offsetHeight}px`,
                opacity: 1,
                overflow: 'hidden',
                transform: 'translateY(0)',
            },
        ],
        {
            duration: 450,
            easing: 'cubic-bezier(0.22, 1, 0.36, 1)',
        },
    );
}

const description =
    'Training plans, meal plans and client check-ins for bodybuilding and powerlifting coaches. Review what you prescribed alongside what your clients recorded.';
const features = [
    {
        category: 'Training',
        title: 'Training splits and session logs',
        text: 'Build repeating splits with training and rest days. Set the exercises, working sets, reps, load and RIR. Clients log against your plan; changes to upcoming sessions preserve their recorded history.',
    },
    {
        category: 'Nutrition',
        title: 'Meal plans for training and rest days',
        text: 'Prescribe separate meals and times for each day type, with future-dated changes when needed. Review hunger, appetite and digestion in the client’s end-of-day check-in.',
    },
    {
        category: 'Check-ins',
        title: 'Daily records with room for context',
        text: 'Review bodyweight, sleep, energy, stress, steps and notes. Enable weekly blood pressure and resting heart rate readings for clients who need them. Missing readings stay missing.',
    },
    {
        category: 'Progress',
        title: 'Trends you can trace back to the log',
        text: 'Follow seven-day bodyweight averages and exercise progress. Compare weight and reps with calories and bodyweight, with reading counts alongside the trends.',
    },
];
const steps = [
    {
        title: 'Invite a client',
        text: 'Send an invitation from your dashboard. Coaching starts when they accept.',
    },
    {
        title: 'Prescribe their plan',
        text: 'Set their training split, meals, cardio and practice in their workspace.',
    },
    {
        title: 'Review their records',
        text: 'Clients log sessions and check-ins against the work you prescribed.',
    },
    {
        title: 'Publish your feedback',
        text: 'Write the review and adjust upcoming plans while keeping logged history.',
    },
];
const questions = [
    {
        title: 'Do powerlifting clients need posing photos?',
        text: 'No. Each coach has a fixed Bodybuilding or Powerlifting discipline. Posing photos and posing reviews are available to bodybuilding coaches and clients with an active bodybuilding coach.',
    },
    {
        title: 'Can clients change the workouts I prescribe?',
        text: 'Clients log their performance against your plan. They can choose another prescribed split day or take extra rest, but cannot rewrite the workout.',
    },
    {
        title: 'Does every check-in field have to be completed?',
        text: 'No. Measurements, notes and pose photos are optional. Clients can save or submit partial check-ins; you decide what to follow up on.',
    },
    {
        title: 'What happens to records when coaching ends?',
        text: 'Clients keep their account, logged history, published plans and written reviews. Ending coaching removes the coach’s access to their records.',
    },
];
</script>

<template>
    <Head title="Bodybuilding and powerlifting coaching software">
        <meta name="description" :content="description" />
        <meta
            property="og:title"
            content="Stage-Ready | Training plans and client check-ins"
        />
        <meta property="og:description" :content="description" />
        <meta property="og:type" content="website" />
    </Head>
    <div
        ref="home"
        class="public-page coach-home"
        :class="{ 'has-scrolled': scrolled }"
    >
        <SiteHeader skip-target="#main-content" />
        <main id="main-content" tabindex="-1">
            <section
                class="hero-scroll-section"
                :class="{ 'hero-is-pinned': heroPinned }"
                aria-labelledby="home-title"
            >
                <div class="home-hero">
                    <div class="home-hero-copy">
                        <p v-reveal class="home-label">
                            For bodybuilding and powerlifting coaches
                        </p>
                        <h1 id="home-title" v-reveal>
                            See how your clients are following the plan.
                        </h1>
                        <p v-reveal class="home-intro">
                            Prescribe training and meals, review check-ins and
                            adjust upcoming sessions. Stage-Ready keeps your
                            clients’ logs beside the work you set.
                        </p>
                        <a v-reveal class="button" href="#features"
                            >View coaching tools</a
                        >
                    </div>
                    <HeroAthletes :progress="heroMotion" />
                </div>
            </section>

            <FeatureShowcase :features="features" />

            <section
                id="competition-prep"
                class="prep-scroll-section"
                :class="{ 'is-pinned': prepPinned, 'is-reading': prepReading }"
                aria-labelledby="prep-title"
            >
                <div class="prep-section">
                    <DumbbellVisual />
                    <div class="prep-copy">
                        <p class="home-label">
                            Bodybuilding and competition prep
                        </p>
                        <h2 id="prep-title">
                            The work between training sessions.
                        </h2>
                        <p class="section-intro">
                            Keep posing practice, check-in feedback and
                            competition dates with the rest of your client’s
                            plan.
                        </p>
                        <dl class="prep-details">
                            <div v-reveal>
                                <dt>Cardio and posing prescriptions</dt>
                                <dd>
                                    Set cardio, mandatory posing rounds and
                                    vacuum sets and holds. Schedule increases in
                                    advance.
                                </dd>
                            </div>
                            <div v-reveal>
                                <dt>Private posing check-ins</dt>
                                <dd>
                                    Review up to thirteen optional pose photos.
                                    Give a posing rating and feedback, with
                                    separate flatness and fullness ratings
                                    during prep.
                                </dd>
                            </div>
                            <div v-reveal>
                                <dt>Reviews and competition dates</dt>
                                <dd>
                                    Write weekly reviews in prep and fortnightly
                                    reviews off-season. Track shows by client,
                                    then record placings and notes after the
                                    show.
                                </dd>
                            </div>
                        </dl>
                    </div>
                </div>
            </section>

            <section
                id="how-it-works"
                class="workflow-section home-section"
                aria-labelledby="workflow-title"
            >
                <div v-reveal class="home-section-heading">
                    <p class="home-label">How it works</p>
                    <h2 id="workflow-title">From invitation to review.</h2>
                </div>
                <ol class="coaching-steps">
                    <li
                        v-for="(step, index) in steps"
                        :key="step.title"
                        v-reveal
                        :class="{
                            'is-current': activeStep === index,
                            'is-passed': activeStep > index,
                        }"
                        :style="{ '--reveal-delay': `${index * 0.07}s` }"
                    >
                        <h3>{{ step.title }}</h3>
                        <p>{{ step.text }}</p>
                    </li>
                </ol>
            </section>

            <section
                class="home-questions home-section"
                aria-labelledby="questions-title"
            >
                <div v-reveal class="home-section-heading">
                    <h2 id="questions-title">Before you invite a client</h2>
                </div>
                <div class="question-list">
                    <details
                        v-for="question in questions"
                        :key="question.title"
                        v-reveal
                        @toggle="unveilFaqAnswer"
                    >
                        <summary>
                            <span class="faq-indicator" aria-hidden="true">
                                <svg viewBox="0 0 20 20" fill="none">
                                    <path
                                        d="m7 5 5 5-5 5"
                                        stroke="currentColor"
                                        stroke-width="1.8"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                    />
                                </svg>
                            </span>
                            <span>{{ question.title }}</span>
                        </summary>
                        <div class="faq-answer-reveal">
                            <p class="faq-answer">{{ question.text }}</p>
                        </div>
                    </details>
                </div>
            </section>
        </main>
        <footer class="site-footer home-footer">
            <div>
                <span class="footer-name">Stage-Ready</span>
                <p>Coaching software for bodybuilding and powerlifting.</p>
            </div>
            <Link href="/login">Member sign in</Link>
        </footer>
    </div>
</template>

<style scoped>
.coach-home {
    --section-accent: #fff;
    --home-gutter: clamp(24px, 5.5vw, 96px);
    --home-header-height: 106px;
    --home-progress: 0;
    position: relative;
    isolation: isolate;
}
.coach-home::before,
.coach-home::after {
    content: '';
    position: absolute;
    inset: 0;
    z-index: -1;
    pointer-events: none;
}
.coach-home::before {
    background: linear-gradient(
        165deg,
        rgb(106 251 240 / 7%),
        transparent 28%,
        rgb(251 195 116 / 5%) 60%,
        transparent 88%
    );
}
.coach-home::after {
    background: linear-gradient(
        195deg,
        transparent 15%,
        rgb(106 251 240 / 6%) 48%,
        rgb(251 195 116 / 6%)
    );
    opacity: var(--home-progress);
}
.coach-home :deep(.site-header) {
    position: sticky;
    top: 0;
    z-index: 30;
    border-bottom-color: transparent;
    transition:
        background-color 0.3s,
        border-color 0.3s,
        box-shadow 0.3s;
}
.has-scrolled :deep(.site-header) {
    background: rgb(5 5 5 / 96%);
    border-bottom-color: var(--line);
    box-shadow: 0 8px 24px rgb(0 0 0 / 16%);
}
.coach-home :deep(.athlete-visual) {
    background: transparent;
    isolation: auto;
}
.coach-home :deep(.athlete-fade) {
    background: none;
}
.hero-scroll-section.hero-is-pinned {
    min-height: calc(var(--hero-content-height) + var(--hero-hold-distance));
}
.hero-is-pinned .home-hero {
    position: sticky;
    top: var(--home-header-height);
}
.home-hero {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(0, 1.15fr);
    align-items: center;
    min-height: 640px;
    padding-left: var(--home-gutter);
}
.home-hero-copy {
    min-width: 0;
    padding-block: 64px;
}
.home-hero-copy > :nth-child(2) {
    --reveal-delay: 0.08s;
}
.home-hero-copy > :nth-child(3) {
    --reveal-delay: 0.16s;
}
.home-hero-copy > :nth-child(4) {
    --reveal-delay: 0.24s;
}
.home-label {
    margin: 0 0 16px;
    font-size: 13px;
    font-weight: 500;
    line-height: 1.6;
}
.home-hero h1 {
    max-width: 15ch;
    margin: 24px 0;
    font-size: clamp(40px, 4.7vw, 76px);
    font-weight: 600;
    line-height: 1.06;
    letter-spacing: -0.045em;
    text-wrap: balance;
}
.home-intro {
    max-width: 44ch;
    margin: 0 0 32px;
    font-size: 17px;
    line-height: 1.75;
}
.home-hero :deep(.hero-athletes) {
    align-self: stretch;
    min-height: 560px;
}
.coach-home :deep(.athlete-caption) {
    display: none;
}
.home-section {
    padding: 80px var(--home-gutter);
    border-top: 1px solid var(--line);
}
#how-it-works,
#main-content {
    scroll-margin-top: calc(var(--home-header-height) + 24px);
}
.home-questions {
    display: grid;
    grid-template-columns: minmax(0, 0.8fr) minmax(0, 1.4fr);
    gap: clamp(32px, 6vw, 96px);
}
.home-section-heading h2,
.prep-copy h2 {
    max-width: 20ch;
    margin: 0;
    font-size: clamp(28px, 3vw, 44px);
    font-weight: 600;
    line-height: 1.15;
    letter-spacing: -0.035em;
    text-wrap: balance;
}
.home-section-heading > p:last-child:not(.home-label) {
    max-width: 36ch;
    margin: 24px 0 0;
    font-size: 16px;
    line-height: 1.75;
}
.coaching-steps h3 {
    margin: 0;
    font-size: 21px;
    font-weight: 500;
    line-height: 1.4;
    letter-spacing: -0.02em;
}
.coaching-steps p,
.section-intro,
.prep-details dd,
.question-list p {
    margin: 0;
    font-size: 15px;
    line-height: 1.8;
}
.prep-scroll-section {
    position: relative;
    border-top: 1px solid var(--line);
    scroll-margin-top: calc(var(--home-header-height) + 16px);
}
.prep-scroll-section.is-pinned {
    min-height: calc(var(--prep-content-height) + var(--prep-hold-distance));
}
.is-pinned .prep-section {
    position: sticky;
    top: calc(var(--home-header-height) + 16px);
}
.prep-section {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(0, 1.1fr);
    align-items: center;
}
.prep-section :deep(.athlete-visual) {
    min-height: 560px;
}
.prep-copy {
    position: relative;
    isolation: isolate;
    min-width: 0;
    padding: 64px var(--home-gutter) 64px 32px;
}
.prep-copy::before {
    content: '';
    position: absolute;
    inset: 32px 32px 32px 0;
    z-index: -1;
    border: 1px solid rgb(106 251 240 / 32%);
    border-radius: 18px;
    background: linear-gradient(
        135deg,
        rgb(106 251 240 / 5%),
        rgb(251 195 116 / 2%)
    );
    box-shadow: 0 0 36px rgb(106 251 240 / 9%);
    opacity: 0.45;
    pointer-events: none;
    transition: opacity 0.4s;
}
.is-reading .prep-copy::before {
    opacity: calc(0.7 + var(--prep-read-progress, 0) * 0.3);
    animation: prep-read-glow 4s ease-in-out infinite;
}
@keyframes prep-read-glow {
    0%,
    100% {
        border-color: rgb(106 251 240 / 34%);
        box-shadow: 0 0 30px rgb(106 251 240 / 11%);
    }
    50% {
        border-color: rgb(106 251 240 / 65%);
        box-shadow:
            0 0 48px rgb(106 251 240 / 20%),
            inset 0 0 20px rgb(106 251 240 / 4%);
    }
}
.section-intro {
    margin-top: 24px;
    max-width: 48ch;
}
.prep-details {
    margin: 32px 0 0;
}
.prep-details > div {
    padding: 24px 0;
    border-top: 1px solid var(--line);
}
.prep-details > div:last-child {
    padding-bottom: 0;
}
.prep-details dt {
    margin: 0 0 8px;
    font-size: 17px;
    font-weight: 500;
    line-height: 1.5;
}
.workflow-section {
    padding-block: 64px;
}
.coaching-steps {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 40px;
    margin: 40px 0 0;
    padding-left: 20px;
}
.coaching-steps li {
    padding-left: 8px;
    padding-top: 24px;
    border-top: 2px solid var(--line);
    transition:
        opacity 0.5s var(--motion-ease),
        transform 0.5s var(--motion-ease),
        border-color 0.4s;
}
.coaching-steps .is-current {
    border-top-color: var(--accent-sand);
}
.coaching-steps .is-passed {
    border-top-color: var(--accent-teal);
}
.coaching-steps li::marker {
    font-size: 14px;
    font-weight: 600;
}
.coaching-steps .is-current::marker {
    color: var(--accent-sand);
}
.coaching-steps .is-passed::marker {
    color: var(--accent-teal);
}
.coaching-steps h3 {
    margin-bottom: 12px;
    font-size: 18px;
}
.home-questions {
    padding-block: 64px;
}
.question-list {
    --faq-blue: 79 161 255;
    --faq-flash: 177 220 255;
}
:global(html[data-theme='light'] .coach-home .question-list) {
    /* Keep the FAQ accents blue after the page's light-mode inversion. */
    --faq-blue: 208 122 16;
    --faq-flash: 239 171 67;
}
.question-list details {
    border-top: 1px solid var(--line);
}
.question-list details:last-child {
    border-bottom: 1px solid var(--line);
}
.question-list summary {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    min-height: 56px;
    padding: 20px 8px;
    cursor: pointer;
    font-size: 16px;
    line-height: 1.6;
    list-style: none;
}
.question-list summary::-webkit-details-marker {
    display: none;
}
.faq-indicator {
    position: relative;
    display: grid;
    place-items: center;
    flex: 0 0 20px;
    height: 1.6em;
}
.faq-indicator::before {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 40px;
    height: 40px;
    border: 1px solid rgb(var(--faq-blue) / 75%);
    border-radius: 50%;
    background: radial-gradient(
        circle,
        rgb(var(--faq-blue) / 45%),
        rgb(var(--faq-blue) / 8%) 65%,
        transparent 75%
    );
    opacity: 0;
    transform: translate(-50%, -50%) scale(0.55);
    pointer-events: none;
}
.faq-indicator svg {
    position: relative;
    display: block;
    width: 20px;
    height: 20px;
    transition: transform 0.2s var(--motion-ease);
}
.question-list details[open] .faq-indicator {
    color: rgb(var(--faq-blue));
}
.question-list details[open] .faq-indicator svg {
    transform: rotate(90deg);
}
.faq-answer-reveal {
    display: flow-root;
}
.question-list .faq-answer {
    max-width: 64ch;
    margin: 0 8px 24px;
    padding: 20px;
    border: 1px solid rgb(var(--faq-blue) / 55%);
    border-radius: 12px;
    background: linear-gradient(
        135deg,
        rgb(var(--faq-blue) / 12%),
        rgb(var(--faq-blue) / 3%)
    );
    box-shadow:
        0 0 28px rgb(var(--faq-blue) / 22%),
        inset 0 0 24px rgb(var(--faq-blue) / 6%);
}
@media (prefers-reduced-motion: no-preference) {
    .faq-indicator.is-glinting {
        animation: faq-arrow-glint 1.8s ease-out 0.15s;
    }
    .faq-indicator.is-glinting svg {
        animation: faq-arrow-emphasis 1.8s ease-out 0.15s;
    }
    .faq-indicator.is-glinting::before {
        animation: faq-arrow-halo 1.8s ease-out 0.15s;
    }
}
@keyframes faq-arrow-glint {
    0%,
    100% {
        filter: drop-shadow(0 0 0 rgb(var(--faq-blue) / 0%));
    }
    18%,
    42% {
        color: rgb(var(--faq-flash));
        filter: drop-shadow(0 0 5px rgb(var(--faq-blue) / 100%))
            drop-shadow(0 0 16px rgb(var(--faq-blue) / 100%));
    }
    65% {
        color: rgb(var(--faq-blue));
        filter: drop-shadow(0 0 8px rgb(var(--faq-blue) / 65%));
    }
}
@keyframes faq-arrow-emphasis {
    0%,
    100% {
        scale: 1;
    }
    18%,
    42% {
        scale: 1.3;
    }
    65% {
        scale: 1.12;
    }
}
@keyframes faq-arrow-halo {
    0% {
        opacity: 0;
        transform: translate(-50%, -50%) scale(0.55);
    }
    24% {
        opacity: 0.9;
        transform: translate(-50%, -50%) scale(1);
    }
    55% {
        opacity: 0.45;
        transform: translate(-50%, -50%) scale(1.25);
    }
    100% {
        opacity: 0;
        transform: translate(-50%, -50%) scale(1.5);
    }
}
.home-footer {
    align-items: center;
    padding: 32px var(--home-gutter);
    border-top: 1px solid var(--line);
    font-size: 13px;
    line-height: 1.7;
    letter-spacing: 0;
}
.footer-name {
    font-size: 16px;
    font-weight: 600;
}
.home-footer p {
    margin: 8px 0 0;
}
.home-footer > a {
    display: inline-flex;
    align-items: center;
    min-height: 44px;
    text-decoration: underline;
    text-underline-offset: 5px;
}
@media (max-width: 1000px) {
    .home-questions {
        grid-template-columns: minmax(0, 0.8fr) minmax(0, 1.2fr);
        gap: 32px;
    }
    .coaching-steps {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        row-gap: 32px;
    }
}
@media (max-width: 720px) {
    .home-hero,
    .prep-section,
    .home-questions {
        grid-template-columns: minmax(0, 1fr);
    }
    .home-hero {
        min-height: 0;
        padding-left: 0;
    }
    .home-hero-copy {
        padding: 48px var(--home-gutter) 0;
    }
    .home-hero h1 {
        max-width: 16ch;
        font-size: clamp(36px, 9vw, 56px);
    }
    .home-intro {
        font-size: 16px;
    }
    .home-hero :deep(.hero-athletes) {
        min-height: 320px;
    }
    .home-section {
        padding-block: 48px;
    }
    .prep-section {
        row-gap: 48px;
    }
    .prep-section :deep(.athlete-visual) {
        min-height: 240px;
    }
    .prep-copy {
        padding: 0 var(--home-gutter) 48px;
    }
    .prep-copy::before {
        inset: -20px 12px 24px;
    }
    .coaching-steps {
        grid-template-columns: 1fr;
        gap: 24px;
        margin-top: 32px;
    }
    .home-footer {
        align-items: flex-start;
        gap: 24px;
    }
}
@media (prefers-reduced-motion: reduce) {
    .coach-home::after {
        opacity: 0;
    }
    .prep-scroll-section.is-pinned {
        min-height: 0;
    }
    .hero-scroll-section.hero-is-pinned {
        min-height: 0;
    }
    .hero-is-pinned .home-hero {
        position: static;
    }
    .is-pinned .prep-section {
        position: static;
    }
    .prep-copy::before {
        animation: none;
        opacity: 0.5;
    }
}
</style>
