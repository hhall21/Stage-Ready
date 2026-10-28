<script setup lang="ts">
import DropdownDetails from '@/components/DropdownDetails.vue';
import { Head, Link } from '@inertiajs/vue3';
import { onMounted, onUnmounted, ref } from 'vue';
import DumbbellVisual from '@/components/DumbbellVisual.vue';
import FeatureShowcase from '@/components/FeatureShowcase.vue';
import HeroAthletes from '@/components/HeroAthletes.vue';
import PublicPolicyLinks from '@/components/PublicPolicyLinks.vue';
import SiteHeader from '@/components/SiteHeader.vue';
import { useCoachPricing } from '@/composables/useCoachPricing';
import { vReveal } from '@/lib/motion';

const home = ref<HTMLElement | null>(null);
const { plans } = useCoachPricing();
const scrolled = ref(false);
const activeStep = ref(-1);
const heroPinned = ref(false);
const heroArtworkPinned = ref(false);
const heroMotion = ref(0);
const prepPinned = ref(false);
const dumbbellPinned = ref(false);
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
    const heroArtworkScene = root.querySelector<HTMLElement>(
        '.hero-artwork-scroll',
    );
    const dumbbell = root.querySelector<HTMLElement>('.dumbbell-visual');
    const prep = root.querySelector<HTMLElement>('.prep-scroll-section');
    const prepContent = root.querySelector<HTMLElement>('.prep-section');
    const dumbbellScene = root.querySelector<HTMLElement>(
        '.dumbbell-scroll-section',
    );
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
        const heroArtworkSceneBounds =
            heroArtworkScene?.getBoundingClientRect();
        const heroArtworkHeight = heroArtwork?.offsetHeight || 0;
        const mobile = window.innerWidth <= 720;
        const canPinHeroArtwork =
            !reducedMotion.matches &&
            mobile &&
            heroArtworkHeight > 0 &&
            heroArtworkHeight <= viewportSpace - 16;
        const canPinHero =
            !reducedMotion.matches &&
            window.innerWidth > 720 &&
            heroHeight <= viewportSpace - 16;
        const prepHeight = prepContent?.offsetHeight || 0;
        const prepBounds = prep?.getBoundingClientRect();
        const prepContentBounds = prepContent?.getBoundingClientRect();
        const dumbbellBounds = dumbbell?.getBoundingClientRect();
        const dumbbellSceneBounds = dumbbellScene?.getBoundingClientRect();
        const dumbbellHeight = dumbbell?.offsetHeight || 0;
        const canPinDumbbell =
            !reducedMotion.matches &&
            mobile &&
            dumbbellHeight > 0 &&
            dumbbellHeight <= viewportSpace - 32;
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
        heroArtworkPinned.value = canPinHeroArtwork;
        prepPinned.value = canPin;
        dumbbellPinned.value = canPinDumbbell;
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
            '--hero-artwork-height',
            `${heroArtworkHeight}px`,
        );
        root.style.setProperty(
            '--dumbbell-artwork-height',
            `${dumbbellHeight}px`,
        );
        root.style.setProperty(
            '--hero-hold-distance',
            canPinHero ? '480px' : '0px',
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
                        : canPinHeroArtwork && heroArtworkSceneBounds
                          ? (headerHeight - heroArtworkSceneBounds.top) / 360
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
                    canPinDumbbell && dumbbellSceneBounds
                        ? (headerHeight + 16 - dumbbellSceneBounds.top) / 480
                        : (window.innerHeight -
                              animationTop -
                              viewportSpace * 0.1) /
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

    cleanupMotion = () => {
        window.cancelAnimationFrame(frame);
        window.removeEventListener('scroll', scheduleUpdate);
        window.removeEventListener('resize', scheduleUpdate);
        reducedMotion.removeEventListener('change', scheduleUpdate);
        resizeObserver?.disconnect();
    };
});

onUnmounted(() => cleanupMotion?.());

const description =
    'All your client data in one place. Training, nutrition, check-ins, progress and coach feedback together for bodybuilding and powerlifting coaches.';
const features = [
    {
        category: 'Training',
        title: 'Their training plan and session logs, together',
        text: 'Build training splits and review completed sessions in the same client workspace. Prescribed exercises, working sets, reps, load and RIR stay connected to the performance your client logs.',
    },
    {
        category: 'Nutrition',
        title: 'Meal plans connected to daily feedback',
        text: 'Keep training-day and rest-day meals with the client’s nutrition check-ins. Review hunger, appetite and digestion alongside the food and meal times you prescribed.',
    },
    {
        category: 'Check-ins',
        title: 'Check-ins with the client’s plan close at hand',
        text: 'Bodyweight, sleep, energy, stress, steps and notes belong to the same client record as their plans. Include optional weekly health readings and keep the details you need for your next review together.',
    },
    {
        category: 'Progress',
        title: 'Progress with the records behind it',
        text: 'Follow exercise progress and seven-day bodyweight averages with training and calorie records in the same workspace. Make your next adjustment with the client’s progress and prescribed plan together.',
    },
];
const steps = [
    {
        title: 'Invite a client',
        text: 'Bring your client into Stage-Ready from your dashboard. Their plans, check-ins and feedback share one workspace from the start.',
    },
    {
        title: 'Prescribe their plan',
        text: 'Set their training split, meals, cardio and practice in the same place you’ll review their progress.',
    },
    {
        title: 'Review their records',
        text: 'Open their session logs and check-ins from the same workspace as their training and nutrition plans.',
    },
    {
        title: 'Publish your feedback',
        text: 'Keep your review with the client’s check-in, then adjust upcoming plans with their recorded history close at hand.',
    },
];
const questions = [
    {
        title: 'What can I manage in one client workspace?',
        text: 'Training splits, session logs, meal plans, daily check-ins, bodyweight trends, posing reviews and coach feedback. Competition dates, cardio and posing prescriptions are there too, so the records you use to coach each client stay together.',
    },
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
    <Head title="All your client data in one place">
        <meta name="description" :content="description" />
        <meta
            property="og:title"
            content="Stage-Ready | All your client data in one place"
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
                            All your client data in one place.
                        </h1>
                        <p v-reveal class="home-intro">
                            Everything you need to coach each client, together.
                            Plan their training and meals, review check-ins,
                            track progress and give feedback from one workspace.
                        </p>
                        <a v-reveal class="button" href="#features"
                            >See everything together</a
                        >
                    </div>
                    <div
                        class="hero-artwork-scroll"
                        :class="{ 'hero-art-is-pinned': heroArtworkPinned }"
                    >
                        <HeroAthletes :progress="heroMotion" />
                    </div>
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
                    <div
                        class="dumbbell-scroll-section"
                        :class="{ 'dumbbell-is-pinned': dumbbellPinned }"
                    >
                        <DumbbellVisual />
                    </div>
                    <div class="prep-copy">
                        <p class="home-label">
                            Bodybuilding and competition prep
                        </p>
                        <h2 id="prep-title">
                            Your client’s whole prep, together.
                        </h2>
                        <p class="section-intro">
                            Training, nutrition, posing practice, photos and
                            competition dates share one client workspace. Review
                            the details, leave feedback and plan their next step
                            with their records close at hand.
                        </p>
                        <dl class="prep-details">
                            <div v-reveal>
                                <dt>Cardio and posing prescriptions</dt>
                                <dd>
                                    Keep daily cardio, posing rounds and vacuum
                                    practice with their training and meals. Plan
                                    increases from the same client workspace.
                                </dd>
                            </div>
                            <div v-reveal>
                                <dt>Private posing check-ins</dt>
                                <dd>
                                    Review optional pose photos and keep your
                                    rating and feedback with the check-in. Prep
                                    reviews can include separate flatness and
                                    fullness ratings.
                                </dd>
                            </div>
                            <div v-reveal>
                                <dt>Reviews and competition dates</dt>
                                <dd>
                                    Keep show dates, placings and review notes
                                    in the client’s workspace. Publish weekly
                                    prep reviews or fortnightly off-season
                                    reviews alongside their plans and check-ins.
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
                    <h2 id="workflow-title">
                        One workspace, from plan to review.
                    </h2>
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
                    <h2 id="questions-title">
                        Your coaching workspace, explained.
                    </h2>
                </div>
                <div class="question-list">
                    <DropdownDetails
                        v-for="question in questions"
                        :key="question.title"
                        v-reveal
                        content-class="faq-answer"
                        glint
                    >
                        <template #summary>{{ question.title }}</template>
                        <p>{{ question.text }}</p>
                    </DropdownDetails>
                </div>
            </section>
        </main>
        <footer class="site-footer home-footer">
            <div>
                <span class="footer-name">Stage-Ready</span>
                <p>
                    Your client plans, progress and feedback. All in one place.
                </p>
                <PublicPolicyLinks />
            </div>
            <Link href="/register"
                >Powerlifting {{ plans.powerlifting.price_label }}/month ·
                Bodybuilding {{ plans.bodybuilding.price_label }}/month</Link
            >
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
        rgb(var(--dropdown-blue) / calc(7% * var(--glow-strength))),
        transparent 28%,
        rgb(var(--dropdown-flash) / calc(5% * var(--glow-strength))) 60%,
        transparent 88%
    );
}
.coach-home::after {
    background: linear-gradient(
        195deg,
        transparent 15%,
        rgb(var(--dropdown-blue) / calc(6% * var(--glow-strength))) 48%,
        rgb(var(--dropdown-flash) / calc(6% * var(--glow-strength)))
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
.hero-artwork-scroll,
.dumbbell-scroll-section {
    display: contents;
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
    border: 1px solid
        rgb(var(--dropdown-blue) / calc(32% * var(--glow-strength)));
    border-radius: 18px;
    background: linear-gradient(
        135deg,
        rgb(var(--dropdown-blue) / calc(5% * var(--glow-strength))),
        rgb(var(--dropdown-blue) / calc(2% * var(--glow-strength)))
    );
    box-shadow:
        var(--glow-core),
        0 0 36px rgb(var(--dropdown-blue) / calc(9% * var(--glow-strength)));
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
        border-color: rgb(
            var(--dropdown-blue) / calc(34% * var(--glow-strength))
        );
        box-shadow:
            var(--glow-core),
            0 0 30px
                rgb(var(--dropdown-blue) / calc(11% * var(--glow-strength)));
    }
    50% {
        border-color: rgb(
            var(--dropdown-blue) / calc(65% * var(--glow-strength))
        );
        box-shadow:
            var(--glow-core),
            0 0 48px
                rgb(var(--dropdown-blue) / calc(20% * var(--glow-strength))),
            inset 0 0 20px
                rgb(var(--dropdown-blue) / calc(4% * var(--glow-strength)));
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
    border-top-color: rgb(var(--dropdown-flash));
}
.coaching-steps .is-passed {
    border-top-color: rgb(var(--dropdown-blue));
}
.coaching-steps li::marker {
    font-size: 14px;
    font-weight: 600;
}
.coaching-steps .is-current::marker {
    color: rgb(var(--dropdown-flash));
}
.coaching-steps .is-passed::marker {
    color: rgb(var(--dropdown-blue));
}
.coaching-steps h3 {
    margin-bottom: 12px;
    font-size: 18px;
}
.home-questions {
    padding-block: 64px;
}
.question-list details {
    border-top: 1px solid var(--line);
}
.question-list details:last-child {
    border-bottom: 1px solid var(--line);
}
.question-list :deep(.faq-answer) {
    max-width: 64ch;
    margin-inline: 8px;
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
    .hero-artwork-scroll,
    .dumbbell-scroll-section {
        display: block;
        min-width: 0;
    }
    .hero-artwork-scroll.hero-art-is-pinned {
        min-height: calc(var(--hero-artwork-height) + 480px);
    }
    .hero-art-is-pinned :deep(.hero-athletes) {
        position: sticky;
        top: var(--home-header-height);
    }
    .dumbbell-scroll-section.dumbbell-is-pinned {
        min-height: calc(var(--dumbbell-artwork-height) + 480px);
    }
    .dumbbell-is-pinned :deep(.dumbbell-visual) {
        position: sticky;
        top: calc(var(--home-header-height) + 16px);
    }
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
    .hero-artwork-scroll.hero-art-is-pinned,
    .dumbbell-scroll-section.dumbbell-is-pinned {
        min-height: 0;
    }
    .hero-art-is-pinned :deep(.hero-athletes),
    .dumbbell-is-pinned :deep(.dumbbell-visual) {
        position: static;
    }
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
