<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AthleteVisual from '@/components/AthleteVisual.vue';
import SiteHeader from '@/components/SiteHeader.vue';
import { athleteImages } from '@/lib/athlete-images';

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
    <div class="public-page coach-home">
        <SiteHeader skip-target="#main-content" />
        <main id="main-content" tabindex="-1">
            <section class="home-hero" aria-labelledby="home-title">
                <div class="home-hero-copy">
                    <p class="home-label">
                        For bodybuilding and powerlifting coaches
                    </p>
                    <h1 id="home-title">
                        See how your clients are following the plan.
                    </h1>
                    <p class="home-intro">
                        Prescribe training and meals, review check-ins and
                        adjust upcoming sessions. Stage-Ready keeps your
                        clients’ logs beside the work you set.
                    </p>
                    <a class="button" href="#features">View coaching tools</a>
                </div>
                <AthleteVisual :src="athleteImages.hero" />
            </section>

            <section
                id="features"
                class="workspace-section home-section"
                aria-labelledby="features-title"
            >
                <div class="home-section-heading">
                    <p class="home-label">The coaching workspace</p>
                    <h2 id="features-title">
                        Review the plan alongside the results.
                    </h2>
                    <p>
                        Training, nutrition and check-ins share the same client
                        record, so you can review them together.
                    </p>
                </div>
                <div class="feature-list">
                    <article
                        v-for="feature in features"
                        :key="feature.category"
                    >
                        <div>
                            <p class="home-label">{{ feature.category }}</p>
                            <h3>{{ feature.title }}</h3>
                        </div>
                        <p>{{ feature.text }}</p>
                    </article>
                </div>
            </section>

            <section class="prep-section" aria-labelledby="prep-title">
                <AthleteVisual :src="athleteImages.detail" />
                <div class="prep-copy">
                    <p class="home-label">Bodybuilding and competition prep</p>
                    <h2 id="prep-title">The work between training sessions.</h2>
                    <p class="section-intro">
                        Keep posing practice, check-in feedback and competition
                        dates with the rest of your client’s plan.
                    </p>
                    <dl class="prep-details">
                        <div>
                            <dt>Cardio and posing prescriptions</dt>
                            <dd>
                                Set cardio, mandatory posing rounds and vacuum
                                sets and holds. Schedule increases in advance.
                            </dd>
                        </div>
                        <div>
                            <dt>Private posing check-ins</dt>
                            <dd>
                                Review up to thirteen optional pose photos. Give
                                a posing rating and feedback, with separate
                                flatness and fullness ratings during prep.
                            </dd>
                        </div>
                        <div>
                            <dt>Reviews and competition dates</dt>
                            <dd>
                                Write weekly reviews in prep and fortnightly
                                reviews off-season. Track shows by client, then
                                record placings and notes after the show.
                            </dd>
                        </div>
                    </dl>
                </div>
            </section>

            <section
                id="how-it-works"
                class="workflow-section home-section"
                aria-labelledby="workflow-title"
            >
                <div class="home-section-heading">
                    <p class="home-label">How it works</p>
                    <h2 id="workflow-title">From invitation to review.</h2>
                </div>
                <ol class="coaching-steps">
                    <li v-for="step in steps" :key="step.title">
                        <h3>{{ step.title }}</h3>
                        <p>{{ step.text }}</p>
                    </li>
                </ol>
            </section>

            <section
                class="home-questions home-section"
                aria-labelledby="questions-title"
            >
                <div class="home-section-heading">
                    <h2 id="questions-title">Before you invite a client</h2>
                </div>
                <div class="question-list">
                    <details
                        v-for="question in questions"
                        :key="question.title"
                    >
                        <summary>{{ question.title }}</summary>
                        <p>{{ question.text }}</p>
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
}
.home-hero {
    display: grid;
    grid-template-columns: minmax(0, 1.2fr) minmax(0, 1fr);
    align-items: center;
    min-height: 600px;
    padding-left: var(--home-gutter);
}
.home-hero-copy {
    min-width: 0;
    padding-block: 64px;
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
.home-hero :deep(.athlete-visual) {
    align-self: stretch;
    min-height: 520px;
}
.coach-home :deep(.athlete-caption) {
    display: none;
}
.home-section {
    padding: 80px var(--home-gutter);
    border-top: 1px solid var(--line);
}
#features,
#how-it-works {
    scroll-margin-top: 24px;
}
.workspace-section,
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
.feature-list article {
    display: grid;
    grid-template-columns: minmax(0, 0.9fr) minmax(0, 1.1fr);
    gap: 32px;
    padding: 32px 0;
    border-top: 1px solid var(--line);
}
.feature-list article:first-child {
    padding-top: 0;
    border-top: 0;
}
.feature-list article:last-child {
    padding-bottom: 0;
}
.feature-list .home-label {
    margin-bottom: 8px;
}
.feature-list h3,
.coaching-steps h3 {
    margin: 0;
    font-size: 21px;
    font-weight: 500;
    line-height: 1.4;
    letter-spacing: -0.02em;
}
.feature-list article > p,
.coaching-steps p,
.section-intro,
.prep-details dd,
.question-list p {
    margin: 0;
    font-size: 15px;
    line-height: 1.8;
}
.prep-section {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(0, 1.1fr);
    align-items: center;
    border-top: 1px solid var(--line);
}
.prep-section :deep(.athlete-visual) {
    min-height: 560px;
}
.prep-copy {
    min-width: 0;
    padding: 64px var(--home-gutter) 64px 32px;
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
}
.coaching-steps li::marker {
    font-size: 14px;
    font-weight: 600;
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
.question-list summary {
    min-height: 56px;
    padding: 20px 8px;
    cursor: pointer;
    font-size: 16px;
    line-height: 1.6;
}
.question-list p {
    max-width: 64ch;
    padding: 0 8px 24px;
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
    .workspace-section,
    .home-questions {
        grid-template-columns: minmax(0, 0.8fr) minmax(0, 1.2fr);
        gap: 32px;
    }
    .feature-list article {
        grid-template-columns: 1fr;
        gap: 16px;
    }
    .coaching-steps {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        row-gap: 32px;
    }
}
@media (max-width: 720px) {
    .home-hero,
    .workspace-section,
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
    .home-hero :deep(.athlete-visual) {
        min-height: 300px;
    }
    .home-section {
        padding-block: 48px;
    }
    .prep-section :deep(.athlete-visual) {
        min-height: 240px;
    }
    .prep-copy {
        padding: 0 var(--home-gutter) 48px;
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
</style>
